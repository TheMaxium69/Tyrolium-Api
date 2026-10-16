<?php

namespace App\Entity;

use App\Repository\TaskLabelRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Étiquette personnelle pour classer ses tâches (voir Task.php), avec une couleur.
 * Distincte de NoteLabel : les deux modules restent indépendants. Unique par compte
 * (sans tenir compte de la casse). La supprimer la détache des tâches sans les supprimer.
 */
#[ORM\Entity(repositoryClass: TaskLabelRepository::class)]
#[ORM\Table(name: 'task_label')]
#[ORM\UniqueConstraint(name: 'uniq_task_label_user_name', columns: ['user_id', 'name'])]
class TaskLabel
{
    public const MAX_LABELS_PER_USER = 50;
    public const MAX_NAME_LENGTH = 50;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(type: 'string', length: 50)]
    private string $name = '';

    #[ORM\Column(type: 'string', length: 20, options: ['default' => 'default'])]
    private string $color = 'default';

    /**
     * @var Collection<int, Task>
     */
    #[ORM\ManyToMany(targetEntity: Task::class, mappedBy: 'labels')]
    private Collection $tasks;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->tasks = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
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

    public function getColor(): string
    {
        return $this->color;
    }

    public function setColor(string $color): static
    {
        $this->color = $color;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
