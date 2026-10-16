<?php

namespace App\Controller\Useritium;

use App\Entity\Note;
use App\Entity\NoteLabel;
use App\Entity\User;
use App\Helper\Pagination;
use App\Repository\NoteLabelRepository;
use App\Repository\NoteRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Notes personnelles du dashboard Useritium (façon Google Keep), pour tout compte
 * connecté. Chaque route est limitée aux notes et étiquettes du compte courant (le
 * filtre est imposé côté serveur) ; celles d'un autre compte répondent 404. Voir
 * Note.php pour le modèle (types, états actif / archivé / corbeille, quotas).
 *
 * Erreurs : 400 = JSON ou type de champ incorrect, 422 = valeur refusée par une
 * règle, 409 = conflit d'état ou quota atteint.
 */
class UseritiumNoteController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly NoteRepository $noteRepository,
        private readonly NoteLabelRepository $labelRepository,
    ) {
    }

    // ------------------------------------------------------------------
    // Notes
    // ------------------------------------------------------------------

    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[Route('/useritium/note/get-all-note', name: 'useritium_note_get_all_note', methods: ['GET'])]
    public function getAllNote(Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $view = (string) $request->query->get('view', NoteRepository::VIEW_ACTIVE);
        if (!in_array($view, NoteRepository::VIEWS, true)) {
            return apiError('view invalide (valeurs : '.implode(', ', NoteRepository::VIEWS).').', 400);
        }

        $filters = [];

        $rawLabel = $request->query->get('label');
        if (null !== $rawLabel && '' !== $rawLabel) {
            $labelId = filter_var($rawLabel, FILTER_VALIDATE_INT);
            if (false === $labelId) {
                return apiError('label doit être un id d\'étiquette.', 400);
            }
            $filters['labelId'] = $labelId;
        }

        $color = $request->query->get('color');
        if (null !== $color && '' !== $color) {
            if (!in_array($color, Note::COLORS, true)) {
                return apiError('color invalide (valeurs : '.implode(', ', Note::COLORS).').', 400);
            }
            $filters['color'] = (string) $color;
        }

        $type = $request->query->get('type');
        if (null !== $type && '' !== $type) {
            if (!in_array($type, Note::TYPES, true)) {
                return apiError('type invalide (valeurs : '.implode(', ', Note::TYPES).').', 400);
            }
            $filters['type'] = (string) $type;
        }

        $search = trim((string) $request->query->get('search', ''));
        if ('' !== $search) {
            $filters['search'] = mb_substr($search, 0, 100);
        }

        $result = Pagination::fromQueryBuilder($this->noteRepository->viewQueryBuilder($user, $view, $filters), $request);

        /** @var list<Note> $items */
        $items = $result['items'];

        return apiSuccess(
            data: array_map(fn (Note $note): array => $this->normalizeNote($note), $items),
            meta: ['pagination' => $result['pagination']],
        );
    }

    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[Route('/useritium/note/get-one-note/{id}', name: 'useritium_note_get_one_note', methods: ['GET'])]
    public function getOneNote(int $id, #[CurrentUser] User $user): JsonResponse
    {
        $note = $this->findOwnNote($id, $user);
        if (null === $note) {
            return apiError('Note introuvable.', 404);
        }

        return apiSuccess(data: $this->normalizeNote($note));
    }

    /**
     * Corps : title, content (type text), items [{text, checked}] (type checklist),
     * type (text par défaut), color, isPinned, isArchived, labelIds. Une note
     * entièrement vide est refusée (422).
     */
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[Route('/useritium/note/post-create-note', name: 'useritium_note_post_create_note', methods: ['POST'])]
    public function postCreateNote(Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return apiError('Corps JSON invalide.', 400);
        }

        if ($this->noteRepository->count(['user' => $user]) >= Note::MAX_NOTES_PER_USER) {
            return apiError(sprintf('Limite de %d notes atteinte (la corbeille compte).', Note::MAX_NOTES_PER_USER), 409);
        }

        $note = (new Note())->setUser($user);

        if (array_key_exists('type', $payload)) {
            if (!is_string($payload['type']) || !in_array($payload['type'], Note::TYPES, true)) {
                return apiError('type invalide (valeurs : '.implode(', ', Note::TYPES).').', 422);
            }
            $note->setType($payload['type']);
        }

        $error = $this->applyPayload($note, $payload, $user);
        if (null !== $error) {
            return $error;
        }

        $this->entityManager->persist($note);
        $this->entityManager->flush();

        return apiSuccess(data: $this->normalizeNote($note), message: 'Note créée.', code: 201);
    }

    /**
     * Mise à jour partielle : seuls les champs présents changent. Le type est fixé à la
     * création. Une note à la corbeille n'est pas modifiable (409, la restaurer d'abord).
     */
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[Route('/useritium/note/put-update-note/{id}', name: 'useritium_note_put_update_note', methods: ['PUT'])]
    public function putUpdateNote(int $id, Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $note = $this->findOwnNote($id, $user);
        if (null === $note) {
            return apiError('Note introuvable.', 404);
        }

        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return apiError('Corps JSON invalide.', 400);
        }

        if ($note->isTrashed()) {
            return apiError('Cette note est à la corbeille : restaure-la avant de la modifier.', 409);
        }

        if (array_key_exists('type', $payload) && $payload['type'] !== $note->getType()) {
            return apiError('Le type d\'une note ne peut pas être modifié.', 400);
        }

        $error = $this->applyPayload($note, $payload, $user);
        if (null !== $error) {
            return $error;
        }

        $this->entityManager->flush();

        return apiSuccess(data: $this->normalizeNote($note), message: 'Note mise à jour.');
    }

    /**
     * Envoie la note à la corbeille (suppression douce, désépinglée). Idempotent.
     */
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[Route('/useritium/note/delete-note/{id}', name: 'useritium_note_delete_note', methods: ['DELETE'])]
    public function deleteNote(int $id, #[CurrentUser] User $user): JsonResponse
    {
        $note = $this->findOwnNote($id, $user);
        if (null === $note) {
            return apiError('Note introuvable.', 404);
        }

        if (!$note->isTrashed()) {
            $note->setTrashedAt(new \DateTimeImmutable())->setIsPinned(false);
            $this->entityManager->flush();
        }

        return apiSuccess(data: $this->normalizeNote($note), message: 'Note envoyée à la corbeille.');
    }

    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[Route('/useritium/note/post-restore-note/{id}', name: 'useritium_note_post_restore_note', methods: ['POST'])]
    public function postRestoreNote(int $id, #[CurrentUser] User $user): JsonResponse
    {
        $note = $this->findOwnNote($id, $user);
        if (null === $note) {
            return apiError('Note introuvable.', 404);
        }

        if (!$note->isTrashed()) {
            return apiError('Cette note n\'est pas à la corbeille.', 409);
        }

        $note->setTrashedAt(null);
        $this->entityManager->flush();

        return apiSuccess(data: $this->normalizeNote($note), message: 'Note restaurée.');
    }

    /**
     * Suppression définitive, uniquement depuis la corbeille.
     */
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[Route('/useritium/note/delete-note-forever/{id}', name: 'useritium_note_delete_note_forever', methods: ['DELETE'])]
    public function deleteNoteForever(int $id, #[CurrentUser] User $user): JsonResponse
    {
        $note = $this->findOwnNote($id, $user);
        if (null === $note) {
            return apiError('Note introuvable.', 404);
        }

        if (!$note->isTrashed()) {
            return apiError('Seule une note à la corbeille peut être supprimée définitivement.', 409);
        }

        $this->entityManager->remove($note);
        $this->entityManager->flush();

        return apiSuccess(message: 'Note supprimée définitivement.');
    }

    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[Route('/useritium/note/delete-empty-trash', name: 'useritium_note_delete_empty_trash', methods: ['DELETE'])]
    public function deleteEmptyTrash(#[CurrentUser] User $user): JsonResponse
    {
        $deleted = (int) $this->noteRepository->createQueryBuilder('n')
            ->delete()
            ->where('n.user = :user AND n.trashedAt IS NOT NULL')->setParameter('user', $user)
            ->getQuery()
            ->execute();

        return apiSuccess(data: ['deleted' => $deleted], message: 'Corbeille vidée.');
    }

    // ------------------------------------------------------------------
    // Étiquettes
    // ------------------------------------------------------------------

    /**
     * Toutes les étiquettes du compte (au plus MAX_LABELS_PER_USER, donc non paginé),
     * avec le nombre de notes hors corbeille qui les portent.
     */
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[Route('/useritium/note/get-all-label', name: 'useritium_note_get_all_label', methods: ['GET'])]
    public function getAllLabel(#[CurrentUser] User $user): JsonResponse
    {
        $labels = $this->labelRepository->findBy(['user' => $user], ['name' => 'ASC']);
        $counts = $this->labelRepository->countNotesByLabel($user);

        return apiSuccess(data: array_map(
            static fn (NoteLabel $label): array => [
                'id' => $label->getId(),
                'name' => $label->getName(),
                'noteCount' => $counts[$label->getId()] ?? 0,
            ],
            $labels,
        ));
    }

    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[Route('/useritium/note/post-create-label', name: 'useritium_note_post_create_label', methods: ['POST'])]
    public function postCreateLabel(Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $name = $this->readLabelName($request);
        if ($name instanceof JsonResponse) {
            return $name;
        }

        if ($this->labelRepository->count(['user' => $user]) >= NoteLabel::MAX_LABELS_PER_USER) {
            return apiError(sprintf('Limite de %d étiquettes atteinte.', NoteLabel::MAX_LABELS_PER_USER), 409);
        }

        $label = (new NoteLabel())->setUser($user)->setName($name);
        $this->entityManager->persist($label);

        try {
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            return apiError('Tu as déjà une étiquette avec ce nom.', 409);
        }

        return apiSuccess(
            data: ['id' => $label->getId(), 'name' => $label->getName(), 'noteCount' => 0],
            message: 'Étiquette créée.',
            code: 201,
        );
    }

    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[Route('/useritium/note/put-update-label/{id}', name: 'useritium_note_put_update_label', methods: ['PUT'])]
    public function putUpdateLabel(int $id, Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $label = $this->labelRepository->findOneBy(['id' => $id, 'user' => $user]);
        if (null === $label) {
            return apiError('Étiquette introuvable.', 404);
        }

        $name = $this->readLabelName($request);
        if ($name instanceof JsonResponse) {
            return $name;
        }

        $label->setName($name);

        try {
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            return apiError('Tu as déjà une étiquette avec ce nom.', 409);
        }

        return apiSuccess(
            data: [
                'id' => $label->getId(),
                'name' => $label->getName(),
                'noteCount' => $this->labelRepository->countNotesByLabel($user)[$label->getId()] ?? 0,
            ],
            message: 'Étiquette renommée.',
        );
    }

    /**
     * Supprime l'étiquette ; les notes qui la portaient sont conservées.
     */
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[Route('/useritium/note/delete-label/{id}', name: 'useritium_note_delete_label', methods: ['DELETE'])]
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

    private function findOwnNote(int $id, User $user): ?Note
    {
        return $this->noteRepository->findOneBy(['id' => $id, 'user' => $user]);
    }

    /**
     * @return string|JsonResponse le nom nettoyé, ou la réponse d'erreur à renvoyer
     */
    private function readLabelName(Request $request): string|JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload) || !is_string($payload['name'] ?? null)) {
            return apiError('name est obligatoire (chaîne).', 400);
        }

        $name = trim($payload['name']);
        if ('' === $name || mb_strlen($name) > NoteLabel::MAX_NAME_LENGTH) {
            return apiError(sprintf('Le nom doit faire entre 1 et %d caractères.', NoteLabel::MAX_NAME_LENGTH), 422);
        }

        return $name;
    }

    /**
     * Valide puis applique les champs présents du corps sur la note (création et mise
     * à jour partagent les mêmes règles). Retourne la réponse d'erreur, ou null si tout
     * est bon ; rien n'est flushé ici.
     *
     * @param array<mixed> $payload
     */
    private function applyPayload(Note $note, array $payload, User $user): ?JsonResponse
    {
        $contentChanged = false;

        if (array_key_exists('title', $payload)) {
            if (null !== $payload['title'] && !is_string($payload['title'])) {
                return apiError('title doit être une chaîne ou null.', 400);
            }
            $title = null === $payload['title'] ? '' : trim($payload['title']);
            if (mb_strlen($title) > Note::MAX_TITLE_LENGTH) {
                return apiError(sprintf('Le titre ne peut pas dépasser %d caractères.', Note::MAX_TITLE_LENGTH), 422);
            }
            $note->setTitle('' === $title ? null : $title);
            $contentChanged = true;
        }

        if (array_key_exists('content', $payload)) {
            if (null !== $payload['content'] && !is_string($payload['content'])) {
                return apiError('content doit être une chaîne ou null.', 400);
            }
            $content = $payload['content'] ?? '';
            if (mb_strlen($content) > Note::MAX_CONTENT_LENGTH) {
                return apiError(sprintf('Le contenu ne peut pas dépasser %d caractères.', Note::MAX_CONTENT_LENGTH), 422);
            }
            $note->setContent('' === trim($content) ? null : $content);
            $contentChanged = true;
        }

        if (array_key_exists('items', $payload)) {
            $items = $this->parseItems($payload['items']);
            if ($items instanceof JsonResponse) {
                return $items;
            }
            $note->setItems($items);
            $contentChanged = true;
        }

        if (array_key_exists('color', $payload)) {
            if (!is_string($payload['color']) || !in_array($payload['color'], Note::COLORS, true)) {
                return apiError('color invalide (valeurs : '.implode(', ', Note::COLORS).').', 422);
            }
            $note->setColor($payload['color']);
            $contentChanged = true;
        }

        foreach (['isPinned', 'isArchived'] as $flag) {
            if (array_key_exists($flag, $payload) && !is_bool($payload[$flag])) {
                return apiError($flag.' doit être un booléen.', 400);
            }
        }
        if (true === ($payload['isPinned'] ?? null) && true === ($payload['isArchived'] ?? null)) {
            return apiError('Une note ne peut pas être épinglée et archivée à la fois.', 400);
        }
        // Comme Keep : épingler désarchive, archiver désépingle.
        if (array_key_exists('isPinned', $payload)) {
            $note->setIsPinned($payload['isPinned']);
            if ($payload['isPinned']) {
                $note->setIsArchived(false);
            }
        }
        if (array_key_exists('isArchived', $payload)) {
            $note->setIsArchived($payload['isArchived']);
            if ($payload['isArchived']) {
                $note->setIsPinned(false);
            }
        }

        if (array_key_exists('labelIds', $payload)) {
            $labels = $this->resolveLabels($payload['labelIds'], $user);
            if ($labels instanceof JsonResponse) {
                return $labels;
            }
            foreach ($note->getLabels()->toArray() as $current) {
                if (!in_array($current, $labels, true)) {
                    $note->removeLabel($current);
                }
            }
            foreach ($labels as $label) {
                $note->addLabel($label);
            }
        }

        // Cohérence type <-> contenu, sur l'état final de la note.
        if (Note::TYPE_TEXT === $note->getType() && [] !== $note->getItems()) {
            return apiError('Une note de type text n\'a pas de cases (items).', 400);
        }
        if (Note::TYPE_CHECKLIST === $note->getType() && null !== $note->getContent()) {
            return apiError('Une note de type checklist n\'a pas de corps (content) : utilise items.', 400);
        }

        if ($note->isEmpty()) {
            return apiError('La note est vide : renseigne au moins un titre, un contenu ou une case.', 422);
        }

        if ($contentChanged) {
            $note->touch();
        }

        return null;
    }

    /**
     * @return list<array{text: string, checked: bool}>|JsonResponse
     */
    private function parseItems(mixed $raw): array|JsonResponse
    {
        if (!is_array($raw) || !array_is_list($raw)) {
            return apiError('items doit être une liste de {text, checked}.', 400);
        }
        if (count($raw) > Note::MAX_ITEMS) {
            return apiError(sprintf('Une note ne peut pas avoir plus de %d cases.', Note::MAX_ITEMS), 422);
        }

        $items = [];
        foreach ($raw as $index => $item) {
            if (!is_array($item) || !is_string($item['text'] ?? null)) {
                return apiError(sprintf('items[%d] doit avoir un text (chaîne).', $index), 400);
            }
            if (array_key_exists('checked', $item) && !is_bool($item['checked'])) {
                return apiError(sprintf('items[%d].checked doit être un booléen.', $index), 400);
            }
            $text = trim($item['text']);
            if ('' === $text || mb_strlen($text) > Note::MAX_ITEM_LENGTH) {
                return apiError(sprintf('items[%d].text doit faire entre 1 et %d caractères.', $index, Note::MAX_ITEM_LENGTH), 422);
            }
            $items[] = ['text' => $text, 'checked' => $item['checked'] ?? false];
        }

        return $items;
    }

    /**
     * @return list<NoteLabel>|JsonResponse
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
        if (count($ids) > Note::MAX_LABELS_PER_NOTE) {
            return apiError(sprintf('Une note ne peut pas avoir plus de %d étiquettes.', Note::MAX_LABELS_PER_NOTE), 422);
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

    /**
     * @return array<string, mixed>
     */
    private function normalizeNote(Note $note): array
    {
        $trashedAt = $note->getTrashedAt();
        $labels = $note->getLabels()->toArray();
        usort($labels, static fn (NoteLabel $a, NoteLabel $b): int => strcasecmp((string) $a->getName(), (string) $b->getName()));

        return [
            'id' => $note->getId(),
            'title' => $note->getTitle(),
            'content' => $note->getContent(),
            'type' => $note->getType(),
            'items' => $note->getItems(),
            'color' => $note->getColor(),
            'isPinned' => $note->isPinned(),
            'isArchived' => $note->isArchived(),
            'trashedAt' => $trashedAt?->format(\DATE_ATOM),
            'purgeAt' => $trashedAt?->modify(sprintf('+%d days', Note::TRASH_RETENTION_DAYS))->format(\DATE_ATOM),
            'labels' => array_map(
                static fn (NoteLabel $label): array => ['id' => $label->getId(), 'name' => $label->getName()],
                $labels,
            ),
            'createdAt' => $note->getCreatedAt()->format(\DATE_ATOM),
            'updatedAt' => $note->getUpdatedAt()->format(\DATE_ATOM),
        ];
    }
}
