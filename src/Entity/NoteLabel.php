<?php

namespace App\Entity;

use App\Repository\NoteLabelRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Étiquette personnelle d'un compte pour classer ses notes (voir Note.php). Unique
 * par compte, sans tenir compte de la casse (collation MySQL). Supprimer une
 * étiquette la détache des notes sans les supprimer.
 */
#[ORM\Entity(repositoryClass: NoteLabelRepository::class)]
#[ORM\Table(name: 'note_label')]
#[ORM\UniqueConstraint(name: 'uniq_note_label_user_name', columns: ['user_id', 'name'])]
class NoteLabel
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
    private ?string $name = null;

    /**
     * @var Collection<int, Note>
     */
    #[ORM\ManyToMany(targetEntity: Note::class, mappedBy: 'labels')]
    private Collection $notes;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->notes = new ArrayCollection();
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

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
