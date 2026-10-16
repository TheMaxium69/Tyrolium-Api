<?php

namespace App\Entity;

use App\Repository\TaskRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Tâche personnelle d'un compte Useritium (dashboard). Une seule entité sert aux
 * deux parties du produit :
 *  - la liste de base : tâches racines sans projet ($project null) ;
 *  - le kanban : une carte est une tâche racine rangée dans une colonne d'un
 *    TaskProject ($project et $column renseignés ensemble).
 * Une sous-tâche a un $parent (une tâche racine) et hérite de son contexte : elle
 * n'a elle-même ni projet, ni colonne, ni étiquette, ni récurrence, et on ne peut
 * pas imbriquer plus d'un niveau.
 *
 * $position est l'index (0, 1, 2…) dans le conteneur de la tâche : les sous-tâches
 * d'un parent, les cartes d'une colonne, ou la liste de base. Il est renuméroté par
 * TaskPlacement à chaque déplacement. Voir UseritiumTaskController pour les règles.
 */
#[ORM\Entity(repositoryClass: TaskRepository::class)]
#[ORM\Table(name: 'task')]
#[ORM\Index(name: 'idx_task_user_scope', columns: ['user_id', 'parent_id', 'project_id', 'is_done'])]
#[ORM\Index(name: 'idx_task_column_position', columns: ['column_id', 'position'])]
class Task
{
    public const PRIORITY_NONE = 0;
    public const PRIORITY_LOW = 1;
    public const PRIORITY_MEDIUM = 2;
    public const PRIORITY_HIGH = 3;
    public const PRIORITY_URGENT = 4;

    public const REPEAT_NONE = 'none';
    public const REPEAT_DAILY = 'daily';
    public const REPEAT_WEEKLY = 'weekly';
    public const REPEAT_MONTHLY = 'monthly';
    public const REPEAT_YEARLY = 'yearly';
    public const REPEATS = [self::REPEAT_NONE, self::REPEAT_DAILY, self::REPEAT_WEEKLY, self::REPEAT_MONTHLY, self::REPEAT_YEARLY];

    /** Fuseau qui définit « aujourd'hui » pour les échéances (le serveur tourne en UTC). */
    public const TIMEZONE = 'Europe/Paris';

    public const MAX_TASKS_PER_USER = 5000;
    public const MAX_TASKS_PER_PROJECT = 500;
    public const MAX_SUBTASKS = 50;
    public const MAX_TITLE_LENGTH = 255;
    public const MAX_DESCRIPTION_LENGTH = 10000;
    public const MAX_LABELS_PER_TASK = 10;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: self::class)]
    #[ORM\JoinColumn(name: 'parent_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    private ?self $parent = null;

    #[ORM\Column(type: 'string', length: 255)]
    private string $title = '';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $isDone = false;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $completedAt = null;

    /** 0 aucune, 1 basse, 2 moyenne, 3 haute, 4 urgente. */
    #[ORM\Column(type: 'smallint', options: ['default' => 0])]
    private int $priority = self::PRIORITY_NONE;

    /** Échéance (jour entier, sans heure). */
    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $dueDate = null;

    /** Colonne `repeat_rule` : `repeat` est un mot réservé de MySQL. */
    #[ORM\Column(name: 'repeat_rule', type: 'string', length: 10, options: ['default' => self::REPEAT_NONE])]
    private string $repeat = self::REPEAT_NONE;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $position = 0;

    #[ORM\ManyToOne(targetEntity: TaskProject::class)]
    #[ORM\JoinColumn(name: 'project_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?TaskProject $project = null;

    #[ORM\ManyToOne(targetEntity: TaskColumn::class)]
    #[ORM\JoinColumn(name: 'column_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?TaskColumn $column = null;

    /**
     * @var Collection<int, TaskLabel>
     */
    #[ORM\ManyToMany(targetEntity: TaskLabel::class, inversedBy: 'tasks')]
    #[ORM\JoinTable(name: 'task_task_label')]
    #[ORM\JoinColumn(name: 'task_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'label_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private Collection $labels;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

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

    public function getParent(): ?self
    {
        return $this->parent;
    }

    public function setParent(?self $parent): static
    {
        $this->parent = $parent;

        return $this;
    }

    public function isSubtask(): bool
    {
        return null !== $this->parent;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

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

    public function isDone(): bool
    {
        return $this->isDone;
    }

    /**
     * Pose l'état « terminée » et sa date ; ne touche ni à la colonne ni à la
     * récurrence (voir UseritiumTaskController::applyDone).
     */
    public function setDone(bool $done): static
    {
        if ($done === $this->isDone) {
            return $this;
        }

        $this->isDone = $done;
        $this->completedAt = $done ? new \DateTimeImmutable() : null;

        return $this;
    }

    public function getCompletedAt(): ?\DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function setPriority(int $priority): static
    {
        $this->priority = $priority;

        return $this;
    }

    public function getDueDate(): ?\DateTimeImmutable
    {
        return $this->dueDate;
    }

    public function setDueDate(?\DateTimeImmutable $dueDate): static
    {
        $this->dueDate = $dueDate;

        return $this;
    }

    public function getRepeat(): string
    {
        return $this->repeat;
    }

    public function setRepeat(string $repeat): static
    {
        $this->repeat = $repeat;

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

    public function getProject(): ?TaskProject
    {
        return $this->project;
    }

    public function setProject(?TaskProject $project): static
    {
        $this->project = $project;

        return $this;
    }

    public function getColumn(): ?TaskColumn
    {
        return $this->column;
    }

    public function setColumn(?TaskColumn $column): static
    {
        $this->column = $column;

        return $this;
    }

    /**
     * @return Collection<int, TaskLabel>
     */
    public function getLabels(): Collection
    {
        return $this->labels;
    }

    public function addLabel(TaskLabel $label): static
    {
        if (!$this->labels->contains($label)) {
            $this->labels->add($label);
        }

        return $this;
    }

    public function removeLabel(TaskLabel $label): static
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
     * Aujourd'hui dans le fuseau métier (Task::TIMEZONE), à minuit.
     */
    public static function today(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('today', new \DateTimeZone(self::TIMEZONE));
    }

    /**
     * Vrai si la tâche est ouverte et son échéance est passée (avant aujourd'hui).
     */
    public function isOverdue(): bool
    {
        return !$this->isDone
            && null !== $this->dueDate
            && $this->dueDate->format('Y-m-d') < self::today()->format('Y-m-d');
    }

    /**
     * Prochaine échéance d'une tâche récurrente : on avance depuis l'échéance actuelle
     * par pas d'un jour / une semaine / un mois / un an jusqu'à atteindre aujourd'hui
     * ou plus tard (une tâche en retard de plusieurs périodes ne rejoue pas le passé).
     * Un jour de mois absent est ramené au dernier du mois (31 janvier + 1 mois =
     * 28/29 février), sans déborder sur le mois suivant.
     */
    public function nextDueDate(): ?\DateTimeImmutable
    {
        if (null === $this->dueDate || self::REPEAT_NONE === $this->repeat) {
            return null;
        }

        $today = self::today()->format('Y-m-d');
        $anchorDay = (int) $this->dueDate->format('j');
        $next = $this->dueDate;
        $steps = 0;

        do {
            ++$steps;
            $next = match ($this->repeat) {
                self::REPEAT_DAILY => $next->modify('+1 day'),
                self::REPEAT_WEEKLY => $next->modify('+7 days'),
                self::REPEAT_MONTHLY => self::addMonthsClamped($this->dueDate, $steps, $anchorDay),
                default => self::addMonthsClamped($this->dueDate, 12 * $steps, $anchorDay),
            };
        } while ($next->format('Y-m-d') < $today && $steps < 5000);

        return $next;
    }

    /**
     * $base + $months mois, en gardant $anchorDay quand le mois cible l'a, sinon son dernier jour.
     */
    private static function addMonthsClamped(\DateTimeImmutable $base, int $months, int $anchorDay): \DateTimeImmutable
    {
        $firstOfMonth = $base->modify('first day of this month')->modify(sprintf('+%d months', $months));
        $lastDay = (int) $firstOfMonth->format('t');

        return $firstOfMonth->setDate(
            (int) $firstOfMonth->format('Y'),
            (int) $firstOfMonth->format('n'),
            min($anchorDay, $lastDay),
        );
    }
}
