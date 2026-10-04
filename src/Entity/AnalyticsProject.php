<?php

namespace App\Entity;

use App\Repository\AnalyticsProjectRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\MaxDepth;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Un projet analytics = un site suivi, identifié par son tag (TyroTag-...),
 * que le script de tracking envoie à chaque visite (voir AnalyticsInput).
 */
#[ORM\Entity(repositoryClass: AnalyticsProjectRepository::class)]
#[ORM\Table(name: 'analytics_project')]
class AnalyticsProject
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['analytics:project:read', 'analytics:input:read'])]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 180, unique: true)]
    #[Groups(['analytics:project:read', 'analytics:input:read'])]
    private ?string $tag = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['analytics:project:read'])]
    private ?string $description = null;

    /**
     * @var list<string>
     */
    #[ORM\Column(type: 'json')]
    #[Groups(['analytics:project:read'])]
    #[Assert\Count(min: 1, minMessage: 'Au moins un nom de domaine est requis.')]
    private array $domainNames = [];

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'created_by_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    #[Groups(['analytics:project:read'])]
    #[MaxDepth(1)]
    private ?User $createdBy = null;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['analytics:project:read'])]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTag(): ?string
    {
        return $this->tag;
    }

    public function setTag(string $tag): static
    {
        $this->tag = $tag;

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

    /**
     * @return list<string>
     */
    public function getDomainNames(): array
    {
        return $this->domainNames;
    }

    /**
     * @param list<string> $domainNames
     */
    public function setDomainNames(array $domainNames): static
    {
        $this->domainNames = array_values($domainNames);

        return $this;
    }

    public function addDomainName(string $domainName): static
    {
        $this->domainNames[] = $domainName;

        return $this;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(User $createdBy): static
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
