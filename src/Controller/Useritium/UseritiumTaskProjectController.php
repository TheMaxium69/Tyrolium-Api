<?php

namespace App\Controller\Useritium;

use App\Entity\Task;
use App\Entity\TaskColumn;
use App\Entity\TaskProject;
use App\Entity\User;
use App\Repository\TaskColumnRepository;
use App\Repository\TaskProjectRepository;
use App\Repository\TaskRepository;
use App\Service\TaskPlacement;
use App\Service\TaskPresenter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Projets kanban du dashboard Useritium, pour tout compte connecté : un projet a des
 * colonnes personnalisables (nom, couleur, ordre, limite indicative, colonne
 * « terminée ») et ses cartes sont des Task (créées, modifiées et déplacées par
 * UseritiumTaskController). Chaque route est limitée aux projets du compte courant ;
 * ceux d'un autre compte répondent 404. Voir Task.php, TaskProject.php, TaskColumn.php.
 *
 * Erreurs : 400 = JSON ou type de champ incorrect, 422 = valeur refusée, 409 = conflit
 * d'état ou quota atteint.
 */
class UseritiumTaskProjectController extends AbstractController
{
    /** Colonnes d'un projet créé sans `columns`. */
    private const DEFAULT_COLUMNS = [
        ['name' => 'À faire', 'isDone' => false],
        ['name' => 'En cours', 'isDone' => false],
        ['name' => 'Terminé', 'isDone' => true],
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TaskProjectRepository $projectRepository,
        private readonly TaskColumnRepository $columnRepository,
        private readonly TaskRepository $taskRepository,
        private readonly TaskPlacement $placement,
        private readonly TaskPresenter $presenter,
    ) {
    }

    // ------------------------------------------------------------------
    // Projets
    // ------------------------------------------------------------------

    /**
     * Projets du compte dans l'ordre du menu (au plus MAX_PROJECTS_PER_USER, donc non
     * paginé), avec leurs compteurs de tâches. `?archived=true` liste les archivés.
     */
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[Route('/useritium/task-project/get-all-project', name: 'useritium_task_project_get_all_project', methods: ['GET'])]
    public function getAllProject(Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $rawArchived = $request->query->get('archived');
        $archived = filter_var($rawArchived, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if (null !== $rawArchived && '' !== $rawArchived && null === $archived) {
            return apiError('archived doit être true ou false.', 400);
        }

        $counts = $this->taskRepository->countRootsByProject($user);

        return apiSuccess(data: array_map(
            fn (TaskProject $project): array => $this->presenter->project($project, $counts[$project->getId()] ?? ['open' => 0, 'done' => 0]),
            $this->projectRepository->findForUser($user, true === $archived),
        ));
    }

    /**
     * Un projet avec ses colonnes (et le nombre de cartes de chacune), sans les cartes.
     */
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[Route('/useritium/task-project/get-one-project/{id}', name: 'useritium_task_project_get_one_project', methods: ['GET'])]
    public function getOneProject(int $id, #[CurrentUser] User $user): JsonResponse
    {
        $project = $this->findOwnProject($id, $user);
        if (null === $project) {
            return apiError('Projet introuvable.', 404);
        }

        return apiSuccess(data: $this->normalizeProject($project, $user));
    }

    /**
     * Le tableau kanban complet en un appel : le projet et, pour chaque colonne dans
     * l'ordre, ses cartes (tâches racines, dans l'ordre de leur position) avec leurs
     * étiquettes et leur avancement de sous-tâches (subtaskCount / subtaskDoneCount) mais
     * sans la liste des sous-tâches : get-one-task la donne pour une carte ouverte.
     */
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[Route('/useritium/task-project/get-board/{id}', name: 'useritium_task_project_get_board', methods: ['GET'])]
    public function getBoard(int $id, #[CurrentUser] User $user): JsonResponse
    {
        $project = $this->findOwnProject($id, $user);
        if (null === $project) {
            return apiError('Projet introuvable.', 404);
        }

        $columns = $this->columnRepository->findForProject($project);
        $cards = $this->presenter->tasks($this->taskRepository->findProjectCards($project), false);

        $byColumn = [];
        foreach ($cards as $card) {
            $byColumn[(int) $card['columnId']][] = $card;
        }

        $board = [];
        foreach ($columns as $column) {
            $tasks = $byColumn[$column->getId()] ?? [];
            $board[] = $this->presenter->column($column, [(int) $column->getId() => count($tasks)]) + ['tasks' => $tasks];
        }

        $counts = $this->taskRepository->countRootsByProject($user)[$project->getId()] ?? ['open' => 0, 'done' => 0];

        return apiSuccess(data: ['project' => $this->presenter->project($project, $counts), 'columns' => $board]);
    }

    /**
     * Corps : name (obligatoire), description, color, et `columns` : liste de
     * {name, color?, wipLimit?, isDone?} (1 à MAX_COLUMNS_PER_PROJECT) — par défaut
     * « À faire », « En cours », « Terminé » (cette dernière est une colonne terminée).
     */
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[Route('/useritium/task-project/post-create-project', name: 'useritium_task_project_post_create_project', methods: ['POST'])]
    public function postCreateProject(Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return apiError('Corps JSON invalide.', 400);
        }
        if (!array_key_exists('name', $payload)) {
            return apiError('name est obligatoire.', 400);
        }

        if ($this->projectRepository->count(['user' => $user]) >= TaskProject::MAX_PROJECTS_PER_USER) {
            return apiError(sprintf('Limite de %d projets atteinte.', TaskProject::MAX_PROJECTS_PER_USER), 409);
        }

        $project = (new TaskProject())->setUser($user)->setPosition($this->projectRepository->nextPosition($user));
        $error = $this->applyProjectFields($project, $payload);
        if (null !== $error) {
            return $error;
        }

        $columnSpecs = self::DEFAULT_COLUMNS;
        if (array_key_exists('columns', $payload)) {
            $columnSpecs = $payload['columns'];
            if (!is_array($columnSpecs) || !array_is_list($columnSpecs) || [] === $columnSpecs) {
                return apiError('columns doit être une liste non vide de colonnes.', 400);
            }
            if (count($columnSpecs) > TaskColumn::MAX_COLUMNS_PER_PROJECT) {
                return apiError(sprintf('Un projet ne peut pas avoir plus de %d colonnes.', TaskColumn::MAX_COLUMNS_PER_PROJECT), 422);
            }
        }

        $columns = [];
        foreach ($columnSpecs as $index => $spec) {
            if (!is_array($spec)) {
                return apiError(sprintf('columns[%d] doit être un objet.', $index), 400);
            }
            $column = (new TaskColumn())->setProject($project)->setPosition($index);
            $error = $this->applyColumnFields($column, $spec, true);
            if (null !== $error) {
                return $error;
            }
            $columns[] = $column;
        }

        $this->entityManager->wrapInTransaction(function () use ($project, $columns): void {
            $this->entityManager->persist($project);
            foreach ($columns as $column) {
                $this->entityManager->persist($column);
            }
            $this->entityManager->flush();
        });

        return apiSuccess(data: $this->normalizeProject($project, $user), message: 'Projet créé.', code: 201);
    }

    /**
     * Mise à jour partielle : name, description, color, isArchived. Un projet archivé
     * n'accepte plus de nouvelle carte (et disparaît du menu par défaut).
     */
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[Route('/useritium/task-project/put-update-project/{id}', name: 'useritium_task_project_put_update_project', methods: ['PUT'])]
    public function putUpdateProject(int $id, Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $project = $this->findOwnProject($id, $user);
        if (null === $project) {
            return apiError('Projet introuvable.', 404);
        }

        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return apiError('Corps JSON invalide.', 400);
        }

        $error = $this->applyProjectFields($project, $payload);
        if (null !== $error) {
            return $error;
        }

        $this->entityManager->flush();

        return apiSuccess(data: $this->normalizeProject($project, $user), message: 'Projet mis à jour.');
    }

    /**
     * Change la place du projet dans le menu : `position` = index cible parmi les projets
     * du même état (actifs ou archivés).
     */
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[Route('/useritium/task-project/post-move-project/{id}', name: 'useritium_task_project_post_move_project', methods: ['POST'])]
    public function postMoveProject(int $id, Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $project = $this->findOwnProject($id, $user);
        if (null === $project) {
            return apiError('Projet introuvable.', 404);
        }

        $position = $this->readRequiredPosition($request);
        if ($position instanceof JsonResponse) {
            return $position;
        }

        $siblings = array_values(array_filter(
            $this->projectRepository->findForUser($user, $project->isArchived()),
            static fn (TaskProject $sibling): bool => $sibling !== $project,
        ));
        array_splice($siblings, min($position, count($siblings)), 0, [$project]);
        foreach ($siblings as $index => $sibling) {
            $sibling->setPosition($index);
        }
        $this->entityManager->flush();

        return apiSuccess(data: $this->normalizeProject($project, $user), message: 'Projet déplacé.');
    }

    /**
     * Supprime le projet et ses colonnes. S'il contient des tâches, `?tasks=` est obligatoire :
     * `delete` les supprime avec leurs sous-tâches, `keep` les remet dans la liste de base
     * (à la suite, dans l'ordre colonne puis position) ; sans lui, 409.
     */
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[Route('/useritium/task-project/delete-project/{id}', name: 'useritium_task_project_delete_project', methods: ['DELETE'])]
    public function deleteProject(int $id, Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $project = $this->findOwnProject($id, $user);
        if (null === $project) {
            return apiError('Projet introuvable.', 404);
        }

        $mode = $request->query->get('tasks');
        if (null !== $mode && '' !== $mode && !in_array($mode, ['delete', 'keep'], true)) {
            return apiError('tasks doit valoir delete ou keep.', 400);
        }

        $cards = $this->taskRepository->findProjectCards($project);
        if ([] !== $cards && (null === $mode || '' === $mode)) {
            return apiError(sprintf('Ce projet contient %d tâche(s) : précise ?tasks=delete (les supprimer) ou ?tasks=keep (les garder dans ta liste).', count($cards)), 409);
        }

        $this->entityManager->wrapInTransaction(function () use ($project, $user, $cards, $mode): void {
            if ('keep' === $mode && [] !== $cards) {
                $columnOrder = [];
                foreach ($this->columnRepository->findForProject($project) as $column) {
                    $columnOrder[$column->getId()] = $column->getPosition();
                }
                usort($cards, static fn (Task $a, Task $b): int => [$columnOrder[$a->getColumn()?->getId()] ?? 0, $a->getPosition(), $a->getId()]
                    <=> [$columnOrder[$b->getColumn()?->getId()] ?? 0, $b->getPosition(), $b->getId()]);
                $this->placement->appendMany($user, $cards, null);
            } else {
                foreach ($cards as $card) {
                    $this->entityManager->remove($card);
                }
            }
            $this->entityManager->flush();
            $this->entityManager->remove($project);
            $this->entityManager->flush();
        });

        return apiSuccess(message: 'Projet supprimé.');
    }

    // ------------------------------------------------------------------
    // Colonnes
    // ------------------------------------------------------------------

    /**
     * Corps : name (obligatoire), color, wipLimit (entier ≥ 1 ou null), isDone, position
     * (index cible, à droite des autres par défaut). Au plus MAX_COLUMNS_PER_PROJECT.
     */
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[Route('/useritium/task-project/post-create-column/{projectId}', name: 'useritium_task_project_post_create_column', methods: ['POST'])]
    public function postCreateColumn(int $projectId, Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $project = $this->findOwnProject($projectId, $user);
        if (null === $project) {
            return apiError('Projet introuvable.', 404);
        }

        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return apiError('Corps JSON invalide.', 400);
        }

        $position = null;
        if (array_key_exists('position', $payload) && null !== $payload['position']) {
            if (!is_int($payload['position']) || $payload['position'] < 0) {
                return apiError('position doit être un entier positif ou nul.', 400);
            }
            $position = $payload['position'];
        }

        $columns = $this->columnRepository->findForProject($project);
        if (count($columns) >= TaskColumn::MAX_COLUMNS_PER_PROJECT) {
            return apiError(sprintf('Un projet ne peut pas avoir plus de %d colonnes.', TaskColumn::MAX_COLUMNS_PER_PROJECT), 409);
        }

        $column = (new TaskColumn())->setProject($project);
        $error = $this->applyColumnFields($column, $payload, true);
        if (null !== $error) {
            return $error;
        }

        array_splice($columns, null === $position ? count($columns) : min($position, count($columns)), 0, [$column]);
        $this->entityManager->wrapInTransaction(function () use ($column, $columns): void {
            $this->entityManager->persist($column);
            $this->renumberColumns($columns);
            $this->entityManager->flush();
        });

        return apiSuccess(data: $this->presenter->column($column), message: 'Colonne créée.', code: 201);
    }

    /**
     * Mise à jour partielle : name, color, wipLimit, isDone. Changer isDone aligne les cartes
     * déjà dans la colonne (terminées si la colonne le devient, rouvertes sinon).
     */
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[Route('/useritium/task-project/put-update-column/{id}', name: 'useritium_task_project_put_update_column', methods: ['PUT'])]
    public function putUpdateColumn(int $id, Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $column = $this->columnRepository->findOneOwned($id, $user);
        if (null === $column) {
            return apiError('Colonne introuvable.', 404);
        }

        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return apiError('Corps JSON invalide.', 400);
        }

        $wasDone = $column->isDone();
        $error = $this->applyColumnFields($column, $payload, false);
        if (null !== $error) {
            return $error;
        }

        $this->entityManager->wrapInTransaction(function () use ($column, $wasDone): void {
            if ($column->isDone() !== $wasDone) {
                $this->taskRepository->createQueryBuilder('t')->update()
                    ->set('t.isDone', ':done')->set('t.completedAt', ':completedAt')
                    ->where('t.column = :column AND t.parent IS NULL AND t.isDone = :previous')
                    ->setParameter('done', $column->isDone())
                    ->setParameter('completedAt', $column->isDone() ? new \DateTimeImmutable() : null, 'datetime_immutable')
                    ->setParameter('column', $column)
                    ->setParameter('previous', !$column->isDone())
                    ->getQuery()->execute();
            }
            $this->entityManager->flush();
        });

        $project = $column->getProject();

        return apiSuccess(
            data: $this->presenter->column($column, null === $project ? [] : $this->taskRepository->countCardsByColumn($project)),
            message: 'Colonne mise à jour.',
        );
    }

    /**
     * Change la place de la colonne : `position` = index cible (0 = tout à gauche).
     */
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[Route('/useritium/task-project/post-move-column/{id}', name: 'useritium_task_project_post_move_column', methods: ['POST'])]
    public function postMoveColumn(int $id, Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $column = $this->columnRepository->findOneOwned($id, $user);
        if (null === $column) {
            return apiError('Colonne introuvable.', 404);
        }

        $position = $this->readRequiredPosition($request);
        if ($position instanceof JsonResponse) {
            return $position;
        }

        $project = $column->getProject();
        if (null === $project) {
            return apiError('Colonne sans projet.', 409);
        }

        $siblings = array_values(array_filter(
            $this->columnRepository->findForProject($project),
            static fn (TaskColumn $sibling): bool => $sibling !== $column,
        ));
        array_splice($siblings, min($position, count($siblings)), 0, [$column]);
        $this->renumberColumns($siblings);
        $this->entityManager->flush();

        return apiSuccess(data: $this->presenter->column($column), message: 'Colonne déplacée.');
    }

    /**
     * Supprime la colonne. Si elle contient des cartes, `?moveTo=<id d'une autre colonne du
     * même projet>` est obligatoire (409 sinon) : elles sont ajoutées à la fin de cette
     * colonne et en prennent l'état (terminées / ouvertes). Un projet garde au moins une colonne.
     */
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[Route('/useritium/task-project/delete-column/{id}', name: 'useritium_task_project_delete_column', methods: ['DELETE'])]
    public function deleteColumn(int $id, Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $column = $this->columnRepository->findOneOwned($id, $user);
        if (null === $column) {
            return apiError('Colonne introuvable.', 404);
        }

        $project = $column->getProject();
        if (null === $project) {
            return apiError('Colonne sans projet.', 409);
        }

        $columns = $this->columnRepository->findForProject($project);
        if (count($columns) <= 1) {
            return apiError('Un projet doit garder au moins une colonne.', 409);
        }

        $cards = array_values(array_filter(
            $this->taskRepository->findProjectCards($project),
            static fn (Task $card): bool => $card->getColumn() === $column,
        ));

        $target = null;
        $rawMoveTo = $request->query->get('moveTo');
        if (null !== $rawMoveTo && '' !== $rawMoveTo) {
            $moveToId = filter_var($rawMoveTo, FILTER_VALIDATE_INT);
            if (false === $moveToId) {
                return apiError('moveTo doit être un id de colonne.', 400);
            }
            $target = $this->columnRepository->findOneOwned($moveToId, $user);
            if (null === $target || $target->getProject() !== $project || $target === $column) {
                return apiError('moveTo doit être une autre colonne du même projet.', 422);
            }
        }
        if ([] !== $cards && null === $target) {
            return apiError(sprintf('Cette colonne contient %d carte(s) : précise ?moveTo=<id d\'une autre colonne>.', count($cards)), 409);
        }

        $this->entityManager->wrapInTransaction(function () use ($column, $columns, $cards, $target, $user): void {
            if ($target instanceof TaskColumn && [] !== $cards) {
                $this->placement->appendMany($user, $cards, $target);
                $this->entityManager->flush();
            }
            $this->entityManager->remove($column);
            $this->entityManager->flush();
            $this->renumberColumns(array_values(array_filter($columns, static fn (TaskColumn $c): bool => $c !== $column)));
            $this->entityManager->flush();
        });

        return apiSuccess(message: 'Colonne supprimée.');
    }

    // ------------------------------------------------------------------
    // Internes
    // ------------------------------------------------------------------

    private function findOwnProject(int $id, User $user): ?TaskProject
    {
        return $this->projectRepository->findOneBy(['id' => $id, 'user' => $user]);
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeProject(TaskProject $project, User $user): array
    {
        $counts = $this->taskRepository->countRootsByProject($user)[$project->getId()] ?? ['open' => 0, 'done' => 0];
        $cardCounts = $this->taskRepository->countCardsByColumn($project);

        return $this->presenter->project($project, $counts) + [
            'columns' => array_map(
                fn (TaskColumn $column): array => $this->presenter->column($column, $cardCounts),
                $this->columnRepository->findForProject($project),
            ),
        ];
    }

    /**
     * @param list<TaskColumn> $columns dans l'ordre voulu
     */
    private function renumberColumns(array $columns): void
    {
        foreach ($columns as $index => $column) {
            if ($column->getPosition() !== $index) {
                $column->setPosition($index);
            }
        }
    }

    private function readRequiredPosition(Request $request): int|JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload) || !is_int($payload['position'] ?? null) || $payload['position'] < 0) {
            return apiError('position est obligatoire (entier positif ou nul).', 400);
        }

        return $payload['position'];
    }

    /**
     * @param array<mixed> $payload
     */
    private function applyProjectFields(TaskProject $project, array $payload): ?JsonResponse
    {
        if (array_key_exists('name', $payload)) {
            if (!is_string($payload['name'])) {
                return apiError('name doit être une chaîne.', 400);
            }
            $name = trim($payload['name']);
            if ('' === $name || mb_strlen($name) > TaskProject::MAX_NAME_LENGTH) {
                return apiError(sprintf('Le nom doit faire entre 1 et %d caractères.', TaskProject::MAX_NAME_LENGTH), 422);
            }
            $project->setName($name);
        }

        if (array_key_exists('description', $payload)) {
            if (null !== $payload['description'] && !is_string($payload['description'])) {
                return apiError('description doit être une chaîne ou null.', 400);
            }
            $description = $payload['description'] ?? '';
            if (mb_strlen($description) > TaskProject::MAX_DESCRIPTION_LENGTH) {
                return apiError(sprintf('La description ne peut pas dépasser %d caractères.', TaskProject::MAX_DESCRIPTION_LENGTH), 422);
            }
            $project->setDescription('' === trim($description) ? null : $description);
        }

        if (array_key_exists('color', $payload)) {
            if (!is_string($payload['color']) || !in_array($payload['color'], TaskProject::COLORS, true)) {
                return apiError('color invalide (valeurs : '.implode(', ', TaskProject::COLORS).').', 422);
            }
            $project->setColor($payload['color']);
        }

        if (array_key_exists('isArchived', $payload)) {
            if (!is_bool($payload['isArchived'])) {
                return apiError('isArchived doit être un booléen.', 400);
            }
            $project->setIsArchived($payload['isArchived']);
        }

        $project->touch();

        return null;
    }

    /**
     * @param array<mixed> $payload
     */
    private function applyColumnFields(TaskColumn $column, array $payload, bool $creating): ?JsonResponse
    {
        if ($creating && !array_key_exists('name', $payload)) {
            return apiError('name est obligatoire pour une colonne.', 400);
        }

        if (array_key_exists('name', $payload)) {
            if (!is_string($payload['name'])) {
                return apiError('name doit être une chaîne.', 400);
            }
            $name = trim($payload['name']);
            if ('' === $name || mb_strlen($name) > TaskColumn::MAX_NAME_LENGTH) {
                return apiError(sprintf('Le nom d\'une colonne doit faire entre 1 et %d caractères.', TaskColumn::MAX_NAME_LENGTH), 422);
            }
            $column->setName($name);
        }

        if (array_key_exists('color', $payload)) {
            if (!is_string($payload['color']) || !in_array($payload['color'], TaskProject::COLORS, true)) {
                return apiError('color invalide (valeurs : '.implode(', ', TaskProject::COLORS).').', 422);
            }
            $column->setColor($payload['color']);
        }

        if (array_key_exists('wipLimit', $payload)) {
            if (null !== $payload['wipLimit'] && !is_int($payload['wipLimit'])) {
                return apiError('wipLimit doit être un entier ou null.', 400);
            }
            if (null !== $payload['wipLimit'] && ($payload['wipLimit'] < 1 || $payload['wipLimit'] > 999)) {
                return apiError('wipLimit doit être compris entre 1 et 999 (ou null pour aucune limite).', 422);
            }
            $column->setWipLimit($payload['wipLimit']);
        }

        if (array_key_exists('isDone', $payload)) {
            if (!is_bool($payload['isDone'])) {
                return apiError('isDone doit être un booléen.', 400);
            }
            $column->setIsDone($payload['isDone']);
        }

        return null;
    }
}
