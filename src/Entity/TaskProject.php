<?php

namespace App\Entity;

use App\Repository\TaskProjectRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Projet personnel d'un compte Useritium : un tableau kanban dont les colonnes
 * (TaskColumn) sont personnalisables et dont les cartes sont des Task. Supprimé avec
 * le compte. $position ordonne les projets dans le menu (renuméroté par
 * UseritiumTaskProjectController). Voir Task.php pour le modèle commun.
 */
#[ORM\Entity(repositoryClass: TaskProjectRepository::class)]
#[ORM\Table(name: 'task_project')]
class TaskProject
{
    /** Même palette que les notes ; `default` = pas de couleur. */
    public const COLORS = Note::COLORS;

    public const MAX_PROJECTS_PER_USER = 50;
    public const MAX_NAME_LENGTH = 100;
    public const MAX_DESCRIPTION_LENGTH = 2000;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(type: 'string', length: 100)]
    private string $name = '';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'string', length: 20, options: ['default' => 'default'])]
    private string $color = 'default';

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $isArchived = false;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $position = 0;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
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

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

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

    public function isArchived(): bool
    {
        return $this->isArchived;
    }

    public function setIsArchived(bool $isArchived): static
    {
        $this->isArchived = $isArchived;

        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;

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
}
