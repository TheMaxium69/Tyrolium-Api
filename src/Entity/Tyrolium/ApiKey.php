<?php

namespace App\Entity\Tyrolium;

use App\Repository\Tyrolium\ApiKeyRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: ApiKeyRepository::class)]
#[ORM\Table(name: 'tyrolium_api_key')]
class ApiKey
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['apikey:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['apikey:read'])]
    private ?string $name = null;

    #[ORM\Column(length: 64)]
    #[Groups(['apikey:read'])]
    private ?string $keyPrefix = null;

    #[ORM\Column(length: 180, unique: true)]
    private ?string $keyHash = null;

    #[ORM\Column(length: 180, nullable: true)]
    #[Groups(['apikey:read'])]
    private ?string $projectTag = null;

    /**
     * @var array<string>
     */
    #[ORM\Column(type: 'json')]
    #[Groups(['apikey:read'])]
    private array $scopes = [];

    #[ORM\Column(options: ['default' => true])]
    #[Groups(['apikey:read'])]
    private bool $isActive = true;

    #[ORM\Column(nullable: true)]
    #[Groups(['apikey:read'])]
    private ?\DateTimeImmutable $lastUsedAt = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['apikey:read'])]
    private ?\DateTimeImmutable $expiresAt = null;

    #[ORM\Column]
    #[Groups(['apikey:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getKeyPrefix(): ?string
    {
        return $this->keyPrefix;
    }

    public function setKeyPrefix(string $keyPrefix): static
    {
        $this->keyPrefix = $keyPrefix;

        return $this;
    }

    public function getKeyHash(): ?string
    {
        return $this->keyHash;
    }

    public function setKeyHash(string $keyHash): static
    {
        $this->keyHash = $keyHash;

        return $this;
    }

    public function getProjectTag(): ?string
    {
        return $this->projectTag;
    }

    public function setProjectTag(?string $projectTag): static
    {
        $this->projectTag = $projectTag;

        return $this;
    }

    /**
     * @return array<string>
     */
    public function getScopes(): array
    {
        return $this->scopes;
    }

    /**
     * @param array<string> $scopes
     */
    public function setScopes(array $scopes): static
    {
        $this->scopes = $scopes;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function getLastUsedAt(): ?\DateTimeImmutable
    {
        return $this->lastUsedAt;
    }

    public function setLastUsedAt(?\DateTimeImmutable $lastUsedAt): static
    {
        $this->lastUsedAt = $lastUsedAt;

        return $this;
    }

    public function getExpiresAt(): ?\DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(?\DateTimeImmutable $expiresAt): static
    {
        $this->expiresAt = $expiresAt;

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
     * @return array{
     *     id: int|null,
     *     name: string|null,
     *     keyPrefix: string|null,
     *     projectTag: string|null,
     *     scopes: array<string>,
     *     isActive: bool,
     *     lastUsedAt: string|null,
     *     expiresAt: string|null,
     *     createdAt: string|null
     * }
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'keyPrefix' => $this->keyPrefix,
            'projectTag' => $this->projectTag,
            'scopes' => $this->scopes,
            'isActive' => $this->isActive,
            'lastUsedAt' => $this->lastUsedAt?->format(\DateTimeInterface::ATOM),
            'expiresAt' => $this->expiresAt?->format(\DateTimeInterface::ATOM),
            'createdAt' => $this->createdAt?->format(\DateTimeInterface::ATOM),
        ];
    }
}
