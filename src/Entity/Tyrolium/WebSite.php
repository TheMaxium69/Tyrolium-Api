<?php

namespace App\Entity\Tyrolium;

use App\Entity\User;
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
    #[Assert\NotBlank(message: 'Le champ domainName est obligatoire.')]
    #[Assert\Hostname(message: 'Le nom de domaine "{{ value }}" n\'est pas un nom de domaine valide.')]
    private ?string $domainName = null;


    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['website:read'])]
    private ?string $label = null;


    // ManyToOne quand table entreprise sera créer
    #[ORM\ManyToOne(targetEntity: 'App\Entity\Entreprise')]
    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['website:read'])]
    #[Assert\NotBlank(message: 'Le champ owner est obligatoire.')]
    private ?string $owner = null;


    // ManyToOne quand table entreprise sera créer
    #[ORM\ManyToOne(targetEntity: 'App\Entity\Entreprise')]
    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['website:read'])]
    private ?string $registrar = null;


    // ManyToOne quand table server sera créer
    #[ORM\ManyToOne(targetEntity: 'App\Entity\SolidServ\Server')]
    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['website:read'])]
    private ?string $server = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['website:read'])]
    private ?\DateTimeImmutable $sslExpiresAt = null;

    #[ORM\Column(options: ['default' => true])]
    #[Groups(['website:read'])]
    private bool $isAutoSSLRenew = true;

    #[ORM\Column(length: 50, options: ['default' => 'active'])]
    #[Groups(['website:read'])]
    private string $status = 'active'; // 'active', 'expired', 'pending_renewal', 'suspended'

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['website:read'])]
    private ?string $content = null;

    #[ORM\Column]
    #[Groups(['website:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['website:read'])]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToOne(inversedBy: 'updateBy')]
    #[Groups(['website:read'])]
    private ?User $createdBy = null;

    #[ORM\ManyToOne(inversedBy: 'name')]
    #[Groups(['website:read'])]
    private ?User $updateBy = null;

    #[ORM\Column]
    #[Groups(['website:read'])]
    private ?bool $isAutoDomainRenew = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['website:read'])]
    private ?\DateTime $buyAt = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['website:read'])]
    private ?\DateTime $frequency = null;

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

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(?string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function getOwner(): ?string
    {
        return $this->owner;
    }

    public function setOwner(?string $owner): static
    {
        $this->owner = $owner;

        return $this;
    }

    public function getRegistrar(): ?string
    {
        return $this->registrar;
    }

    public function setRegistrar(?string $registrar): static
    {
        $this->registrar = $registrar;

        return $this;
    }

    public function getServer(): ?string
    {
        return $this->server;
    }

    public function setServer(?string $server): static
    {
        $this->server = $server;

        return $this;
    }

    public function getSSLExpiresAt(): ?\DateTimeImmutable
    {
        return $this->sslExpiresAt;
    }

    public function setSSLExpiresAt(?\DateTimeImmutable $sslExpiresAt): static
    {
        $this->sslExpiresAt = $sslExpiresAt;

        return $this;
    }

    public function isAutoSSLRenew(): bool
    {
        return $this->isAutoSSLRenew;
    }

    public function setisAutoSSLRenew(bool $isAutoSSLRenew): static
    {
        $this->isAutoSSLRenew = $isAutoSSLRenew;

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

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(?string $content): static
    {
        $this->content = $content;

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
     *     label: string|null,
     *     owner: string|null,
     *     registrar: string|null,
     *     server: string|null,
     *     sslExpiresAt: string|null,
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
            'label' => $this->label,
            'owner' => $this->owner,
            'registrar' => $this->registrar,
            'server' => $this->server,
            'sslExpiresAt' => $this->sslExpiresAt?->format(\DateTimeInterface::ATOM),
            'isAutoRenew' => $this->isAutoRenew,
            'status' => $this->status,
            'content' => $this->content,
            'createdAt' => $this->createdAt?->format(\DateTimeInterface::ATOM),
            'updatedAt' => $this->updatedAt?->format(\DateTimeInterface::ATOM),
        ];
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $createdBy): static
    {
        $this->createdBy = $createdBy;

        return $this;
    }

    public function getUpdateBy(): ?User
    {
        return $this->updateBy;
    }

    public function setUpdateBy(?User $updateBy): static
    {
        $this->updateBy = $updateBy;

        return $this;
    }

    public function isAutoDomainRenew(): ?bool
    {
        return $this->isAutoDomainRenew;
    }

    public function setIsAutoDomainRenew(bool $isAutoDomainRenew): static
    {
        $this->isAutoDomainRenew = $isAutoDomainRenew;

        return $this;
    }

    public function getBuyAt(): ?\DateTimeImmutable
    {
        return $this->buyAt;
    }

    public function setBuyAt(?\DateTimeImmutable $buyAt): static
    {
        $this->buyAt = $buyAt;

        return $this;
    }

    public function getFrequency(): ?\DateTimeImmutable
    {
        return $this->frequency;
    }

    public function setFrequency(?\DateTimeImmutable $frequency): static
    {
        $this->frequency = $frequency;

        return $this;
    }
}
