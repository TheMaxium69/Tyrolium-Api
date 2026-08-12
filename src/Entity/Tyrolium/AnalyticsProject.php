<?php

namespace App\Entity\Tyrolium;

use App\Repository\Tyrolium\AnalyticsProjectRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: AnalyticsProjectRepository::class)]
#[ORM\Table(name: 'tyrolium_analytics_project')]
class AnalyticsProject
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['analytics:project:read', 'analytics:input:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    #[Groups(['analytics:project:read', 'analytics:input:read'])]
    private ?string $tag = null;

    /**
     * @var array<string>
     */
    #[ORM\Column(type: 'json')]
    #[Groups(['analytics:project:read'])]
    private array $domainNames = [];

    #[ORM\Column]
    #[Groups(['analytics:project:read'])]
    private ?int $useritiumId = null;

    #[ORM\Column]
    #[Groups(['analytics:project:read'])]
    private ?\DateTimeImmutable $createdAt = null;

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

    /**
     * @return array<string>
     */
    public function getDomainNames(): array
    {
        return $this->domainNames;
    }

    /**
     * @param array<string> $domainNames
     */
    public function setDomainNames(array $domainNames): static
    {
        $this->domainNames = $domainNames;

        return $this;
    }

    public function getUseritiumId(): ?int
    {
        return $this->useritiumId;
    }

    public function setUseritiumId(int $useritiumId): static
    {
        $this->useritiumId = $useritiumId;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    /**
     * @return array{id: int|null, tag: string|null, domainNames: array<string>, useritiumId: int|null, createdAt: string|null}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'tag' => $this->tag,
            'domainNames' => $this->domainNames,
            'useritiumId' => $this->useritiumId,
            'createdAt' => $this->createdAt?->format(\DateTimeInterface::ATOM),
        ];
    }
}
