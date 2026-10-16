<?php

namespace App\Entity;

use App\Repository\TaskColumnRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Colonne d'un projet kanban (TaskProject), entièrement personnalisable : nom, couleur,
 * ordre, limite de travail en cours indicative ($wipLimit, jamais bloquante côté API) et
 * drapeau $isDone — déposer une carte dans une colonne « terminée » la marque terminée,
 * l'en sortir la rouvre. Supprimée avec son projet.
 */
#[ORM\Entity(repositoryClass: TaskColumnRepository::class)]
#[ORM\Table(name: 'task_column')]
class TaskColumn
{
    public const MAX_COLUMNS_PER_PROJECT = 12;
    public const MAX_NAME_LENGTH = 50;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: TaskProject::class)]
    #[ORM\JoinColumn(name: 'project_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?TaskProject $project = null;

    #[ORM\Column(type: 'string', length: 50)]
    private string $name = '';

    #[ORM\Column(type: 'string', length: 20, options: ['default' => 'default'])]
    private string $color = 'default';

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $position = 0;

    /** Nombre de cartes conseillé au maximum dans la colonne, null = illimité. */
    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $wipLimit = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $isDone = false;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProject(): ?TaskProject
    {
        return $this->project;
    }

    public function setProject(TaskProject $project): static
    {
        $this->project = $project;

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

    public function getColor(): string
    {
        return $this->color;
    }

    public function setColor(string $color): static
    {
        $this->color = $color;

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

    public function getWipLimit(): ?int
    {
        return $this->wipLimit;
    }

    public function setWipLimit(?int $wipLimit): static
    {
        $this->wipLimit = $wipLimit;

        return $this;
    }

    public function isDone(): bool
    {
        return $this->isDone;
    }

    public function setIsDone(bool $isDone): static
    {
        $this->isDone = $isDone;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
