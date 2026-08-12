<?php

namespace App\Entity\Tyrolium;

use App\Repository\Tyrolium\WebSiteRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: WebSiteRepository::class)]
#[ORM\Table(name: 'tyrolium_website')]
class WebSite
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['website:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    #[Groups(['website:read'])]
    private ?string $domainName = null;

    #[ORM\Column(length: 255)]
    #[Groups(['website:read'])]
    private ?string $name = null;

    #[ORM\Column(length: 50)]
    #[Groups(['website:read'])]
    private ?string $ownerType = null; // 'filiale' ou 'client_prestation'

    #[ORM\Column(length: 255)]
    #[Groups(['website:read'])]
    private ?string $ownerName = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['website:read'])]
    private ?string $serverInstance = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['website:read'])]
    private ?\DateTimeImmutable $renewalAt = null;

    #[ORM\Column(options: ['default' => true])]
    #[Groups(['website:read'])]
    private bool $isAutoRenew = true;

    #[ORM\Column(length: 50, options: ['default' => 'active'])]
    #[Groups(['website:read'])]
    private string $status = 'active'; // 'active', 'expired', 'pending_renewal', 'suspended'

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['website:read'])]
    private ?string $notes = null;

    #[ORM\Column]
    #[Groups(['website:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['website:read'])]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDomainName(): ?string
    {
        return $this->domainName;
    }

    public function setDomainName(string $domainName): static
    {
        $this->domainName = $domainName;

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

    public function getOwnerType(): ?string
    {
        return $this->ownerType;
    }

    public function setOwnerType(string $ownerType): static
    {
        $this->ownerType = $ownerType;

        return $this;
    }

    public function getOwnerName(): ?string
    {
        return $this->ownerName;
    }

    public function setOwnerName(string $ownerName): static
    {
        $this->ownerName = $ownerName;

        return $this;
    }

    public function getServerInstance(): ?string
    {
        return $this->serverInstance;
    }

    public function setServerInstance(?string $serverInstance): static
    {
        $this->serverInstance = $serverInstance;

        return $this;
    }

    public function getRenewalAt(): ?\DateTimeImmutable
    {
        return $this->renewalAt;
    }

    public function setRenewalAt(?\DateTimeImmutable $renewalAt): static
    {
        $this->renewalAt = $renewalAt;

        return $this;
    }

    public function isAutoRenew(): bool
    {
        return $this->isAutoRenew;
    }

    public function setIsAutoRenew(bool $isAutoRenew): static
    {
        $this->isAutoRenew = $isAutoRenew;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;

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

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    /**
     * @return array{
     *     id: int|null,
     *     domainName: string|null,
     *     name: string|null,
     *     ownerType: string|null,
     *     ownerName: string|null,
     *     serverInstance: string|null,
     *     renewalAt: string|null,
     *     isAutoRenew: bool,
     *     status: string,
     *     notes: string|null,
     *     createdAt: string|null,
     *     updatedAt: string|null
     * }
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'domainName' => $this->domainName,
            'name' => $this->name,
            'ownerType' => $this->ownerType,
            'ownerName' => $this->ownerName,
            'serverInstance' => $this->serverInstance,
            'renewalAt' => $this->renewalAt?->format(\DateTimeInterface::ATOM),
            'isAutoRenew' => $this->isAutoRenew,
            'status' => $this->status,
            'notes' => $this->notes,
            'createdAt' => $this->createdAt?->format(\DateTimeInterface::ATOM),
            'updatedAt' => $this->updatedAt?->format(\DateTimeInterface::ATOM),
        ];
    }
}
