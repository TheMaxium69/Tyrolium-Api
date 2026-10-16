<?php

namespace App\Controller\Useritium;

use App\Entity\Task;
use App\Entity\TaskColumn;
use App\Entity\TaskLabel;
use App\Entity\TaskProject;
use App\Entity\User;
use App\Helper\Pagination;
use App\Repository\TaskColumnRepository;
use App\Repository\TaskLabelRepository;
use App\Repository\TaskProjectRepository;
use App\Repository\TaskRepository;
use App\Service\TaskPlacement;
use App\Service\TaskPresenter;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Tâches personnelles du dashboard Useritium, pour tout compte connecté : la liste de
 * base (tâches sans projet, avec sous-tâches, échéance, priorité, étiquettes,
 * récurrence) et les cartes des projets kanban, qui sont les mêmes tâches rangées dans
 * une colonne (les projets et colonnes sont dans UseritiumTaskProjectController). Chaque
 * route est limitée aux données du compte courant ; celles d'un autre compte répondent
 * 404 (ou 422 quand elles sont référencées dans un corps de requête). Voir Task.php.
 *
 * Erreurs : 400 = JSON ou type de champ incorrect, 422 = valeur refusée, 409 = conflit
 * d'état ou quota atteint.
 */
class UseritiumTaskController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TaskRepository $taskRepository,
        private readonly TaskProjectRepository $projectRepository,
        private readonly TaskColumnRepository $columnRepository,
        private readonly TaskLabelRepository $labelRepository,
        private readonly TaskPlacement $placement,
        private readonly TaskPresenter $presenter,
    ) {
    }

    // ------------------------------------------------------------------
    // Tâches
    // ------------------------------------------------------------------

    /**
     * Tâches racines (les sous-tâches sont dans `subtasks`), paginées. Filtres :
     * `project` = none (liste de base, défaut) | any | id ; `status` = open (défaut) |
     * done | all ; `due` = today | overdue | upcoming (7 jours) | nodate ; `priority` 0-4 ;
     * `label` id ; `search` (titre, description, titre d'une sous-tâche) ; `sort` = manual
     * (défaut) | dueDate | priority | createdAt.
     */
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[Route('/useritium/task/get-all-task', name: 'useritium_task_get_all_task', methods: ['GET'])]
    public function getAllTask(Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $filters = [];

        $project = (string) $request->query->get('project', 'none');
        if ('none' === $project || 'any' === $project) {
            $filters['project'] = $project;
        } elseif (false !== ($projectId = filter_var($project, FILTER_VALIDATE_INT))) {
            $filters['project'] = $projectId;
        } else {
            return apiError('project doit valoir none, any ou un id de projet.', 400);
        }

        foreach (['status' => TaskRepository::STATUSES, 'due' => TaskRepository::DUES, 'sort' => TaskRepository::SORTS] as $key => $allowed) {
            $value = $request->query->get($key);
            if (null === $value || '' === $value) {
                continue;
            }
            if (!in_array($value, $allowed, true)) {
                return apiError($key.' invalide (valeurs : '.implode(', ', $allowed).').', 400);
            }
            $filters[$key] = (string) $value;
        }

        $priority = $request->query->get('priority');
        if (null !== $priority && '' !== $priority) {
            $priorityValue = filter_var($priority, FILTER_VALIDATE_INT);
            if (false === $priorityValue || $priorityValue < Task::PRIORITY_NONE || $priorityValue > Task::PRIORITY_URGENT) {
                return apiError('priority doit être un entier de 0 à 4.', 400);
            }
            $filters['priority'] = $priorityValue;
        }

        $label = $request->query->get('label');
        if (null !== $label && '' !== $label) {
            $labelId = filter_var($label, FILTER_VALIDATE_INT);
            if (false === $labelId) {
                return apiError('label doit être un id d\'étiquette.', 400);
            }
            $filters['labelId'] = $labelId;
        }

        $search = trim((string) $request->query->get('search', ''));
        if ('' !== $search) {
            $filters['search'] = mb_substr($search, 0, 100);
        }

        $result = Pagination::fromQueryBuilder($this->taskRepository->rootsQueryBuilder($user, $filters), $request);
        /** @var list<Task> $tasks */
        $tasks = $result['items'];

        return apiSuccess(data: $this->presenter->tasks($tasks), meta: ['pagination' => $result['pagination']]);
    }

    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[Route('/useritium/task/get-one-task/{id}', name: 'useritium_task_get_one_task', methods: ['GET'])]
    public function getOneTask(int $id, #[CurrentUser] User $user): JsonResponse
    {
        $task = $this->findOwnTask($id, $user);
        if (null === $task) {
            return apiError('Tâche introuvable.', 404);
        }

        return apiSuccess(data: $this->presenter->task($task));
    }

    /**
     * Compteurs de la barre latérale : liste de base (ouvertes / terminées) et échéances
     * (aujourd'hui, en retard, 7 jours à venir) sur toutes les tâches racines ouvertes.
     */
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[Route('/useritium/task/get-overview', name: 'useritium_task_get_overview', methods: ['GET'])]
    public function getOverview(#[CurrentUser] User $user): JsonResponse
    {
        return apiSuccess(data: $this->taskRepository->overview($user));
    }

    /**
     * Corps : title (obligatoire), description, priority 0-4, dueDate (YYYY-MM-DD), repeat
     * (none|daily|weekly|monthly|yearly, exige une échéance), labelIds, position, et au plus
     * un emplacement :
     *  - rien : liste de base ;
     *  - projectId et/ou columnId : carte d'un projet (colonne = la première par défaut) ;
     *  - parentId : sous-tâche d'une tâche racine (n'accepte alors ni projet, ni colonne,
     *    ni étiquette, ni récurrence).
     */
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[Route('/useritium/task/post-create-task', name: 'useritium_task_post_create_task', methods: ['POST'])]
    public function postCreateTask(Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return apiError('Corps JSON invalide.', 400);
        }
        if (!array_key_exists('title', $payload)) {
            return apiError('title est obligatoire.', 400);
        }

        $position = $this->readPosition($payload);
        if ($position instanceof JsonResponse) {
            return $position;
        }

        if ($this->taskRepository->count(['user' => $user]) >= Task::MAX_TASKS_PER_USER) {
            return apiError(sprintf('Limite de %d tâches atteinte.', Task::MAX_TASKS_PER_USER), 409);
        }

        $task = (new Task())->setUser($user);
        $column = null;

        if (isset($payload['parentId'])) {
            foreach (['projectId', 'columnId', 'labelIds', 'repeat'] as $forbidden) {
                if (array_key_exists($forbidden, $payload)) {
                    return apiError($forbidden.' n\'est pas disponible pour une sous-tâche.', 400);
                }
            }
            $parent = $this->resolveParent($payload['parentId'], $user);
            if ($parent instanceof JsonResponse) {
                return $parent;
            }
            if ($this->taskRepository->count(['parent' => $parent]) >= Task::MAX_SUBTASKS) {
                return apiError(sprintf('Une tâche ne peut pas avoir plus de %d sous-tâches.', Task::MAX_SUBTASKS), 409);
            }
            $task->setParent($parent);
        } elseif (isset($payload['projectId']) || isset($payload['columnId'])) {
            $column = $this->resolveColumn($payload, $user);
            if ($column instanceof JsonResponse) {
                return $column;
            }
            $project = $column->getProject();
            if ($project?->isArchived()) {
                return apiError('Ce projet est archivé : désarchive-le avant d\'y ajouter une tâche.', 409);
            }
            if ($this->taskRepository->count(['project' => $project]) >= Task::MAX_TASKS_PER_PROJECT) {
                return apiError(sprintf('Un projet ne peut pas avoir plus de %d tâches.', Task::MAX_TASKS_PER_PROJECT), 409);
            }
            $task->setProject($project)->setColumn($column)->setDone($column->isDone());
        }

        $error = $this->applyFields($task, $payload, $user);
        if (null !== $error) {
            return $error;
        }

        $this->entityManager->wrapInTransaction(function () use ($task, $position): void {
            $this->entityManager->persist($task);
            $this->placement->place($task, $position);
            $this->entityManager->flush();
        });

        return apiSuccess(data: $this->presenter->task($task), message: 'Tâche créée.', code: 201);
    }

    /**
     * Mise à jour partielle : title, description, priority, dueDate, repeat, labelIds, isDone.
     * Le projet, la colonne et l'ordre se changent avec post-move-task.
     *
     * Terminer une tâche récurrente (avec échéance) ne la ferme pas : son échéance avance à
     * la prochaine occurrence, ses sous-tâches sont rouvertes, et la réponse porte
     * meta.recurred = true. Terminer une carte l'envoie dans la première colonne « terminée »
     * du projet (s'il y en a une) ; la rouvrir la renvoie dans la première colonne ouverte.
     */
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[Route('/useritium/task/put-update-task/{id}', name: 'useritium_task_put_update_task', methods: ['PUT'])]
    public function putUpdateTask(int $id, Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $task = $this->findOwnTask($id, $user);
        if (null === $task) {
            return apiError('Tâche introuvable.', 404);
        }

        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return apiError('Corps JSON invalide.', 400);
        }

        if (array_key_exists('isDone', $payload) && !is_bool($payload['isDone'])) {
            return apiError('isDone doit être un booléen.', 400);
        }

        $error = $this->applyFields($task, $payload, $user);
        if (null !== $error) {
            return $error;
        }

        $recurred = false;
        if (array_key_exists('isDone', $payload)) {
            $done = $payload['isDone'];
            $this->entityManager->wrapInTransaction(function () use ($task, $done, &$recurred): void {
                $recurred = $this->applyDone($task, $done);
                $this->entityManager->flush();
            });
        } else {
            $this->entityManager->flush();
        }

        return apiSuccess(
            data: $this->presenter->task($task),
            message: $recurred ? 'Tâche récurrente reportée à sa prochaine échéance.' : 'Tâche mise à jour.',
            meta: $recurred ? ['recurred' => true] : null,
        );
    }

    /**
     * Déplace une tâche (glisser-déposer). Corps, selon la tâche :
     *  - tâche racine : `projectId` et/ou `columnId` pour la ranger dans un projet,
     *    `projectId: null` pour la remettre dans la liste de base, `position` (index cible,
     *    fin par défaut) ; sans emplacement, seule la position change dans son conteneur.
     *    Déposer dans une colonne « terminée » termine la carte, la déposer dans une autre
     *    colonne la rouvre ;
     *  - sous-tâche : `position` et, facultatif, `parentId` pour la rattacher à une autre tâche.
     */
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[Route('/useritium/task/post-move-task/{id}', name: 'useritium_task_post_move_task', methods: ['POST'])]
    public function postMoveTask(int $id, Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $task = $this->findOwnTask($id, $user);
        if (null === $task) {
            return apiError('Tâche introuvable.', 404);
        }

        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return apiError('Corps JSON invalide.', 400);
        }

        $position = $this->readPosition($payload);
        if ($position instanceof JsonResponse) {
            return $position;
        }

        if ($task->isSubtask()) {
            foreach (['projectId', 'columnId'] as $forbidden) {
                if (array_key_exists($forbidden, $payload)) {
                    return apiError($forbidden.' n\'est pas disponible pour une sous-tâche.', 400);
                }
            }
            $parent = $task->getParent();
            if (isset($payload['parentId'])) {
                $newParent = $this->resolveParent($payload['parentId'], $user);
                if ($newParent instanceof JsonResponse) {
                    return $newParent;
                }
                if ($newParent !== $parent && $this->taskRepository->count(['parent' => $newParent]) >= Task::MAX_SUBTASKS) {
                    return apiError(sprintf('Une tâche ne peut pas avoir plus de %d sous-tâches.', Task::MAX_SUBTASKS), 409);
                }
                $parent = $newParent;
            }
            if (null === $position && $parent === $task->getParent()) {
                return apiError('position ou parentId est obligatoire.', 400);
            }
            if (null === $parent) {
                return apiError('Sous-tâche sans parent.', 409);
            }

            $this->entityManager->wrapInTransaction(function () use ($task, $parent, $position): void {
                $this->placement->moveToParent($task, $parent, $position);
                $this->entityManager->flush();
            });

            return apiSuccess(data: $this->presenter->task($task), message: 'Sous-tâche déplacée.');
        }

        if (array_key_exists('parentId', $payload)) {
            return apiError('Une tâche racine ne peut pas devenir une sous-tâche.', 400);
        }

        $toBase = array_key_exists('projectId', $payload) && null === $payload['projectId'] && !isset($payload['columnId']);
        $target = null;
        if (!$toBase && (isset($payload['projectId']) || isset($payload['columnId']))) {
            $target = $this->resolveColumn($payload, $user);
            if ($target instanceof JsonResponse) {
                return $target;
            }
            $targetProject = $target->getProject();
            if ($targetProject?->isArchived()) {
                return apiError('Ce projet est archivé : désarchive-le avant d\'y déplacer une tâche.', 409);
            }
            if ($targetProject !== $task->getProject() && $this->taskRepository->count(['project' => $targetProject]) >= Task::MAX_TASKS_PER_PROJECT) {
                return apiError(sprintf('Un projet ne peut pas avoir plus de %d tâches.', Task::MAX_TASKS_PER_PROJECT), 409);
            }
        } elseif (!$toBase && null === $position) {
            return apiError('Indique une position, un projet / une colonne, ou projectId: null.', 400);
        }

        $this->entityManager->wrapInTransaction(function () use ($task, $toBase, $target, $position): void {
            if ($toBase) {
                $this->placement->moveToBase($task, $position);
            } elseif ($target instanceof TaskColumn) {
                $this->placement->moveToColumn($task, $target, $position);
            } elseif (null !== $task->getColumn()) {
                $this->placement->moveToColumn($task, $task->getColumn(), $position);
            } else {
                $this->placement->moveToBase($task, $position);
            }
            $this->entityManager->flush();
        });

        return apiSuccess(data: $this->presenter->task($task), message: 'Tâche déplacée.');
    }

    /**
     * Supprime la tâche et ses sous-tâches. Définitif (pas de corbeille).
     */
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[Route('/useritium/task/delete-task/{id}', name: 'useritium_task_delete_task', methods: ['DELETE'])]
    public function deleteTask(int $id, #[CurrentUser] User $user): JsonResponse
    {
        $task = $this->findOwnTask($id, $user);
        if (null === $task) {
            return apiError('Tâche introuvable.', 404);
        }

        $this->entityManager->remove($task);
        $this->entityManager->flush();

        return apiSuccess(message: 'Tâche supprimée.');
    }

    /**
     * Supprime les tâches racines terminées (et leurs sous-tâches) de la liste de base
     * (`?project=none`, défaut) ou d'un projet (`?project=id`).
     */
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[Route('/useritium/task/delete-completed-task', name: 'useritium_task_delete_completed_task', methods: ['DELETE'])]
    public function deleteCompletedTask(Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $scope = (string) $request->query->get('project', 'none');

        $qb = $this->taskRepository->createQueryBuilder('t')->delete()
            ->where('t.user = :user AND t.parent IS NULL AND t.isDone = true')->setParameter('user', $user);

        if ('none' === $scope) {
            $qb->andWhere('t.project IS NULL');
        } else {
            $projectId = filter_var($scope, FILTER_VALIDATE_INT);
            if (false === $projectId) {
                return apiError('project doit valoir none ou un id de projet.', 400);
            }
            $project = $this->projectRepository->findOneBy(['id' => $projectId, 'user' => $user]);
            if (null === $project) {
                return apiError('Projet introuvable.', 404);
            }
            $qb->andWhere('t.project = :project')->setParameter('project', $project);
        }

        return apiSuccess(data: ['deleted' => (int) $qb->getQuery()->execute()], message: 'Tâches terminées supprimées.');
    }

    // ------------------------------------------------------------------
    // Étiquettes
    // ------------------------------------------------------------------

    /**
     * Toutes les étiquettes du compte (au plus MAX_LABELS_PER_USER, donc non paginé), avec
     * le nombre de tâches racines ouvertes qui les portent.
     */
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[Route('/useritium/task/get-all-label', name: 'useritium_task_get_all_label', methods: ['GET'])]
    public function getAllLabel(#[CurrentUser] User $user): JsonResponse
    {
        $labels = $this->labelRepository->findBy(['user' => $user], ['name' => 'ASC']);
        $counts = $this->labelRepository->countOpenTasksByLabel($user);

        return apiSuccess(data: array_map(
            static fn (TaskLabel $label): array => [
                'id' => $label->getId(),
                'name' => $label->getName(),
                'color' => $label->getColor(),
                'taskCount' => $counts[$label->getId()] ?? 0,
            ],
            $labels,
        ));
    }

    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[Route('/useritium/task/post-create-label', name: 'useritium_task_post_create_label', methods: ['POST'])]
    public function postCreateLabel(Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return apiError('Corps JSON invalide.', 400);
        }

        $name = $this->readLabelName($payload['name'] ?? null);
        if ($name instanceof JsonResponse) {
            return $name;
        }

        $color = 'default';
        if (array_key_exists('color', $payload)) {
            if (!is_string($payload['color']) || !in_array($payload['color'], TaskProject::COLORS, true)) {
                return apiError('color invalide (valeurs : '.implode(', ', TaskProject::COLORS).').', 422);
            }
            $color = $payload['color'];
        }

        if ($this->labelRepository->count(['user' => $user]) >= TaskLabel::MAX_LABELS_PER_USER) {
            return apiError(sprintf('Limite de %d étiquettes atteinte.', TaskLabel::MAX_LABELS_PER_USER), 409);
        }

        $label = (new TaskLabel())->setUser($user)->setName($name)->setColor($color);
        $this->entityManager->persist($label);

        try {
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            return apiError('Tu as déjà une étiquette avec ce nom.', 409);
        }

        return apiSuccess(
            data: ['id' => $label->getId(), 'name' => $label->getName(), 'color' => $label->getColor(), 'taskCount' => 0],
            message: 'Étiquette créée.',
            code: 201,
        );
    }

    /**
     * Renomme et/ou recolore l'étiquette (champs facultatifs, au moins un).
     */
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[Route('/useritium/task/put-update-label/{id}', name: 'useritium_task_put_update_label', methods: ['PUT'])]
    public function putUpdateLabel(int $id, Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $label = $this->labelRepository->findOneBy(['id' => $id, 'user' => $user]);
        if (null === $label) {
            return apiError('Étiquette introuvable.', 404);
        }

        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload) || (!array_key_exists('name', $payload) && !array_key_exists('color', $payload))) {
            return apiError('name et/ou color sont obligatoires.', 400);
        }

        if (array_key_exists('name', $payload)) {
            $name = $this->readLabelName($payload['name']);
            if ($name instanceof JsonResponse) {
                return $name;
            }
            $label->setName($name);
        }
        if (array_key_exists('color', $payload)) {
            if (!is_string($payload['color']) || !in_array($payload['color'], TaskProject::COLORS, true)) {
                return apiError('color invalide (valeurs : '.implode(', ', TaskProject::COLORS).').', 422);
            }
            $label->setColor($payload['color']);
        }

        try {
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            return apiError('Tu as déjà une étiquette avec ce nom.', 409);
        }

        return apiSuccess(
            data: [
                'id' => $label->getId(),
                'name' => $label->getName(),
                'color' => $label->getColor(),
                'taskCount' => $this->labelRepository->countOpenTasksByLabel($user)[$label->getId()] ?? 0,
            ],
            message: 'Étiquette mise à jour.',
        );
    }

    /**
     * Supprime l'étiquette ; les tâches qui la portaient sont conservées.
     */
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[Route('/useritium/task/delete-label/{id}', name: 'useritium_task_delete_label', methods: ['DELETE'])]
    public function deleteLabel(int $id, #[CurrentUser] User $user): JsonResponse
    {
        $label = $this->labelRepository->findOneBy(['id' => $id, 'user' => $user]);
        if (null === $label) {
            return apiError('Étiquette introuvable.', 404);
        }

        $this->entityManager->remove($label);
        $this->entityManager->flush();

        return apiSuccess(message: 'Étiquette supprimée.');
    }

    // ------------------------------------------------------------------
    // Internes
    // ------------------------------------------------------------------

    private function findOwnTask(int $id, User $user): ?Task
    {
        return $this->taskRepository->findOneBy(['id' => $id, 'user' => $user]);
    }

    /**
     * Applique l'état « terminée » ; retourne vrai si la tâche récurrente a été reportée
     * au lieu d'être fermée. Ne flush pas.
     */
    private function applyDone(Task $task, bool $done): bool
    {
        if ($done === $task->isDone()) {
            return false;
        }

        if ($done && !$task->isSubtask() && Task::REPEAT_NONE !== $task->getRepeat() && null !== $task->getDueDate()) {
            $task->setDueDate($task->nextDueDate())->touch();
            foreach ($this->taskRepository->findBy(['parent' => $task]) as $subtask) {
                $subtask->setDone(false);
            }

            return true;
        }

        $column = $task->getColumn();
        if (null === $column || $task->isSubtask()) {
            $task->setDone($done);

            return false;
        }

        // Une carte suit sa colonne : on la range dans la première colonne de l'état voulu
        // (terminée / ouverte) du projet ; sans colonne de cet état, on change juste le drapeau.
        $project = $column->getProject();
        $target = null;
        if (null !== $project) {
            foreach ($this->columnRepository->findForProject($project) as $candidate) {
                if ($candidate->isDone() === $done) {
                    $target = $candidate;
                    break;
                }
            }
        }

        if (null !== $target && $target !== $column) {
            $this->placement->moveToColumn($task, $target);
        } else {
            $task->setDone($done);
        }

        return false;
    }

    /**
     * Valide puis applique les champs présents du corps sur la tâche (création et mise à
     * jour partagent les mêmes règles). Retourne la réponse d'erreur, ou null ; ne flush pas.
     *
     * @param array<mixed> $payload
     */
    private function applyFields(Task $task, array $payload, User $user): ?JsonResponse
    {
        $changed = false;

        if (array_key_exists('title', $payload)) {
            if (!is_string($payload['title'])) {
                return apiError('title doit être une chaîne.', 400);
            }
            $title = trim($payload['title']);
            if ('' === $title || mb_strlen($title) > Task::MAX_TITLE_LENGTH) {
                return apiError(sprintf('Le titre doit faire entre 1 et %d caractères.', Task::MAX_TITLE_LENGTH), 422);
            }
            $task->setTitle($title);
            $changed = true;
        }

        if (array_key_exists('description', $payload)) {
            if (null !== $payload['description'] && !is_string($payload['description'])) {
                return apiError('description doit être une chaîne ou null.', 400);
            }
            $description = $payload['description'] ?? '';
            if (mb_strlen($description) > Task::MAX_DESCRIPTION_LENGTH) {
                return apiError(sprintf('La description ne peut pas dépasser %d caractères.', Task::MAX_DESCRIPTION_LENGTH), 422);
            }
            $task->setDescription('' === trim($description) ? null : $description);
            $changed = true;
        }

        if (array_key_exists('priority', $payload)) {
            if (!is_int($payload['priority'])) {
                return apiError('priority doit être un entier de 0 à 4.', 400);
            }
            if ($payload['priority'] < Task::PRIORITY_NONE || $payload['priority'] > Task::PRIORITY_URGENT) {
                return apiError('priority doit être comprise entre 0 (aucune) et 4 (urgente).', 422);
            }
            $task->setPriority($payload['priority']);
            $changed = true;
        }

        if (array_key_exists('dueDate', $payload)) {
            $date = $this->parseDate($payload['dueDate']);
            if ($date instanceof JsonResponse) {
                return $date;
            }
            $task->setDueDate($date);
            $changed = true;
        }

        if (array_key_exists('repeat', $payload)) {
            if ($task->isSubtask()) {
                return apiError('repeat n\'est pas disponible pour une sous-tâche.', 400);
            }
            if (!is_string($payload['repeat']) || !in_array($payload['repeat'], Task::REPEATS, true)) {
                return apiError('repeat invalide (valeurs : '.implode(', ', Task::REPEATS).').', 422);
            }
            $task->setRepeat($payload['repeat']);
            $changed = true;
        }

        if (Task::REPEAT_NONE !== $task->getRepeat() && null === $task->getDueDate()) {
            return apiError('Une tâche récurrente doit avoir une échéance (dueDate).', 422);
        }

        if (array_key_exists('labelIds', $payload)) {
            if ($task->isSubtask()) {
                return apiError('labelIds n\'est pas disponible pour une sous-tâche.', 400);
            }
            $labels = $this->resolveLabels($payload['labelIds'], $user);
            if ($labels instanceof JsonResponse) {
                return $labels;
            }
            foreach ($task->getLabels()->toArray() as $current) {
                if (!in_array($current, $labels, true)) {
                    $task->removeLabel($current);
                }
            }
            foreach ($labels as $label) {
                $task->addLabel($label);
            }
        }

        if ($changed) {
            $task->touch();
        }

        return null;
    }

    private function parseDate(mixed $raw): \DateTimeImmutable|JsonResponse|null
    {
        if (null === $raw) {
            return null;
        }
        if (!is_string($raw)) {
            return apiError('dueDate doit être une date YYYY-MM-DD ou null.', 400);
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $raw);
        if (false === $date || $date->format('Y-m-d') !== $raw || (int) $date->format('Y') < 1970 || (int) $date->format('Y') > 2200) {
            return apiError('dueDate invalide (format YYYY-MM-DD, année 1970-2200).', 422);
        }

        return $date;
    }

    /**
     * @param array<mixed> $payload
     */
    private function readPosition(array $payload): int|JsonResponse|null
    {
        if (!array_key_exists('position', $payload) || null === $payload['position']) {
            return null;
        }
        if (!is_int($payload['position']) || $payload['position'] < 0) {
            return apiError('position doit être un entier positif ou nul.', 400);
        }

        return $payload['position'];
    }

    private function resolveParent(mixed $raw, User $user): Task|JsonResponse
    {
        if (!is_int($raw)) {
            return apiError('parentId doit être un entier.', 400);
        }

        $parent = $this->taskRepository->findOneBy(['id' => $raw, 'user' => $user]);
        if (null === $parent) {
            return apiError('Tâche parente introuvable.', 422);
        }
        if ($parent->isSubtask()) {
            return apiError('Une sous-tâche ne peut pas avoir de sous-tâches.', 422);
        }

        return $parent;
    }

    /**
     * Colonne cible d'après `columnId` et/ou `projectId` (première colonne du projet si
     * seul le projet est donné) ; vérifie qu'elles sont au compte et cohérentes.
     *
     * @param array<mixed> $payload
     */
    private function resolveColumn(array $payload, User $user): TaskColumn|JsonResponse
    {
        foreach (['projectId', 'columnId'] as $key) {
            if (isset($payload[$key]) && !is_int($payload[$key])) {
                return apiError($key.' doit être un entier.', 400);
            }
        }

        if (isset($payload['columnId'])) {
            $column = $this->columnRepository->findOneOwned($payload['columnId'], $user);
            if (null === $column) {
                return apiError('Colonne introuvable.', 422);
            }
            if (isset($payload['projectId']) && $column->getProject()?->getId() !== $payload['projectId']) {
                return apiError('Cette colonne n\'appartient pas à ce projet.', 422);
            }

            return $column;
        }

        $project = $this->projectRepository->findOneBy(['id' => $payload['projectId'] ?? 0, 'user' => $user]);
        if (null === $project) {
            return apiError('Projet introuvable.', 422);
        }
        $columns = $this->columnRepository->findForProject($project);
        if ([] === $columns) {
            return apiError('Ce projet n\'a aucune colonne.', 409);
        }

        return $columns[0];
    }

    /**
     * @return list<TaskLabel>|JsonResponse
     */
    private function resolveLabels(mixed $raw, User $user): array|JsonResponse
    {
        if (!is_array($raw) || !array_is_list($raw)) {
            return apiError('labelIds doit être une liste d\'ids d\'étiquettes.', 400);
        }

        $ids = [];
        foreach ($raw as $id) {
            if (!is_int($id)) {
                return apiError('labelIds ne doit contenir que des entiers.', 400);
            }
            $ids[$id] = $id;
        }
        if (count($ids) > Task::MAX_LABELS_PER_TASK) {
            return apiError(sprintf('Une tâche ne peut pas avoir plus de %d étiquettes.', Task::MAX_LABELS_PER_TASK), 422);
        }
        if ([] === $ids) {
            return [];
        }

        $labels = $this->labelRepository->findBy(['id' => array_values($ids), 'user' => $user]);
        if (count($labels) !== count($ids)) {
            return apiError('Une des étiquettes est introuvable.', 422);
        }

        return $labels;
    }

    private function readLabelName(mixed $raw): string|JsonResponse
    {
        if (!is_string($raw)) {
            return apiError('name est obligatoire (chaîne).', 400);
        }

        $name = trim($raw);
        if ('' === $name || mb_strlen($name) > TaskLabel::MAX_NAME_LENGTH) {
            return apiError(sprintf('Le nom doit faire entre 1 et %d caractères.', TaskLabel::MAX_NAME_LENGTH), 422);
        }

        return $name;
    }
}
