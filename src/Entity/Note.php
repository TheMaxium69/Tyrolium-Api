<?php

namespace App\Entity;

use App\Repository\NoteRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Note personnelle d'un compte Useritium (façon Google Keep), propre au dashboard :
 * jamais visible d'un autre compte, supprimée avec lui (ON DELETE CASCADE).
 *
 * Deux types : `text` (corps libre dans $content) ou `checklist` (liste de cases dans
 * $items, chaque entrée {text, checked}). Le type est fixé à la création. Une note
 * est soit active, soit archivée ($isArchived), soit à la corbeille ($trashedAt) ;
 * épinglée et archivée s'excluent. La corbeille est purgée après TRASH_RETENTION_DAYS
 * jours par la commande app:note:purge-trash. Les règles de validation vivent dans
 * UseritiumNoteController.
 */
#[ORM\Entity(repositoryClass: NoteRepository::class)]
#[ORM\Table(name: 'note')]
#[ORM\Index(name: 'idx_note_user_state', columns: ['user_id', 'is_archived', 'trashed_at'])]
class Note
{
    public const TYPE_TEXT = 'text';
    public const TYPE_CHECKLIST = 'checklist';
    public const TYPES = [self::TYPE_TEXT, self::TYPE_CHECKLIST];

    /** Palette de Google Keep ; `default` = pas de couleur. */
    public const COLORS = ['default', 'red', 'orange', 'yellow', 'green', 'teal', 'blue', 'darkblue', 'purple', 'pink', 'brown', 'gray'];

    public const MAX_NOTES_PER_USER = 1000;
    public const MAX_TITLE_LENGTH = 255;
    public const MAX_CONTENT_LENGTH = 20000;
    public const MAX_ITEMS = 200;
    public const MAX_ITEM_LENGTH = 500;
    public const MAX_LABELS_PER_NOTE = 20;
    public const TRASH_RETENTION_DAYS = 30;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $title = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $content = null;

    #[ORM\Column(type: 'string', length: 20, options: ['default' => self::TYPE_TEXT])]
    private string $type = self::TYPE_TEXT;

    /**
     * @var list<array{text: string, checked: bool}>
     */
    #[ORM\Column(type: 'json')]
    private array $items = [];

    /**
     * Texte des cases mis à plat (une par ligne), recalculé par setItems() : sert
     * uniquement à la recherche, pour qu'elle ignore casse et accents comme sur le titre
     * et le corps (un JSON converti en texte se compare en casse stricte).
     */
    #[ORM\Column(name: 'items_text', type: 'text', nullable: true)]
    private ?string $itemsText = null;

    #[ORM\Column(type: 'string', length: 20, options: ['default' => 'default'])]
    private string $color = 'default';

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $isPinned = false;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $isArchived = false;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $trashedAt = null;

    /**
     * @var Collection<int, NoteLabel>
     */
    #[ORM\ManyToMany(targetEntity: NoteLabel::class, inversedBy: 'notes')]
    #[ORM\JoinTable(name: 'note_note_label')]
    #[ORM\JoinColumn(name: 'note_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'label_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private Collection $labels;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    /**
     * Dernière modification du contenu (titre, corps, cases, couleur) — épingler,
     * archiver ou étiqueter ne la change pas.
     */
    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->labels = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(?string $content): static
    {
        $this->content = $content;

        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    /**
     * @return list<array{text: string, checked: bool}>
     */
    public function getItems(): array
    {
        return $this->items;
    }

    /**
     * @param list<array{text: string, checked: bool}> $items
     */
    public function setItems(array $items): static
    {
        $this->items = $items;
        $this->itemsText = [] === $items ? null : implode("\n", array_column($items, 'text'));

        return $this;
    }

    public function getColor(): string
    {
        return $this->color;
    }

    public function setColor(string $color): static
    {
        $this->color = $color;

        return $this;
    }

    public function isPinned(): bool
    {
        return $this->isPinned;
    }

    public function setIsPinned(bool $isPinned): static
    {
        $this->isPinned = $isPinned;

        return $this;
    }

    public function isArchived(): bool
    {
        return $this->isArchived;
    }

    public function setIsArchived(bool $isArchived): static
    {
        $this->isArchived = $isArchived;

        return $this;
    }

    public function getTrashedAt(): ?\DateTimeImmutable
    {
        return $this->trashedAt;
    }

    public function setTrashedAt(?\DateTimeImmutable $trashedAt): static
    {
        $this->trashedAt = $trashedAt;

        return $this;
    }

    public function isTrashed(): bool
    {
        return null !== $this->trashedAt;
    }

    /**
     * @return Collection<int, NoteLabel>
     */
    public function getLabels(): Collection
    {
        return $this->labels;
    }

    public function addLabel(NoteLabel $label): static
    {
        if (!$this->labels->contains($label)) {
            $this->labels->add($label);
        }

        return $this;
    }

    public function removeLabel(NoteLabel $label): static
    {
        $this->labels->removeElement($label);

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function touch(): static
    {
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    /**
     * Vrai si la note ne contient rien (ni titre, ni corps, ni case).
     */
    public function isEmpty(): bool
    {
        return (null === $this->title || '' === $this->title)
            && (null === $this->content || '' === $this->content)
            && [] === $this->items;
    }
}
