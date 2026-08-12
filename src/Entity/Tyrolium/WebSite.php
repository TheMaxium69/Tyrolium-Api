<?php

namespace App\Entity\Tyrolium;

use App\Entity\User;
use App\Repository\Tyrolium\WebSiteRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

enum WebSiteStatus: string
{
    case ACTIVE = 'active';
    case EXPIRED = 'expired';
    case PENDING_RENEWAL = 'pending_renewal';
    case SUSPENDED = 'suspended';
}

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

    // ManyToOne quand table entreprise sera créée
    // #[ORM\ManyToOne(targetEntity: 'App\Entity\Entreprise')]
    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['website:read'])]
    #[Assert\NotBlank(message: 'Le champ owner est obligatoire.')]
    private ?string $owner = null;

    // ManyToOne quand table entreprise sera créée
    // #[ORM\ManyToOne(targetEntity: 'App\Entity\Entreprise')]
    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['website:read'])]
    private ?string $registrar = null;

    // ManyToOne quand table server sera créée
    // #[ORM\ManyToOne(targetEntity: 'App\Entity\SolidServ\Server')]
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
    private WebSiteStatus $status = WebSiteStatus::ACTIVE;
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['website:read'])]
    private ?string $content = null;

    #[ORM\Column]
    #[Groups(['website:read'])]
    private ?\DateTimeImmutable $createAt = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['website:read'])]
    private ?\DateTimeImmutable $updateAt = null;

    #[ORM\ManyToOne]
    #[Groups(['website:read'])]
    private ?User $createBy = null;

    #[ORM\ManyToOne]
    #[Groups(['website:read'])]
    private ?User $updateBy = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['website:read'])]
    private ?bool $isAutoDomainRenew = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['website:read'])]
    private ?\DateTimeImmutable $buyAt = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['website:read'])]
    private ?int $frequency = null;

    public function __construct()
    {
        $this->createAt = new \DateTimeImmutable();
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

    public function setIsAutoSSLRenew(bool $isAutoSSLRenew): static
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

    public function getCreateAt(): ?\DateTimeImmutable
    {
        return $this->createAt;
    }

    public function setCreateAt(\DateTimeImmutable $createAt): static
    {
        $this->createAt = $createAt;

        return $this;
    }

    public function getUpdateAt(): ?\DateTimeImmutable
    {
        return $this->updateAt;
    }

    public function setUpdateAt(?\DateTimeImmutable $updateAt): static
    {
        $this->updateAt = $updateAt;

        return $this;
    }

    public function getCreateBy(): ?User
    {
        return $this->createBy;
    }

    public function setCreateBy(?User $createBy): static
    {
        $this->createBy = $createBy;

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

    public function setIsAutoDomainRenew(?bool $isAutoDomainRenew): static
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

    public function getFrequency(): ?int
    {
        return $this->frequency;
    }

    public function setFrequency(?int $frequency): static
    {
        $this->frequency = $frequency;

        return $this;
    }

    /**
     * @return array{
     *     id: int|null,
     *     domainName: string|null,
     *     label: string|null,
     *     owner: string|null,
     *     registrar: string|null,
     *     server: string|null,
     *     sslExpiresAt: string|null,
     *     isAutoSSLRenew: bool,
     *     isAutoDomainRenew: bool|null,
     *     status: string,
     *     content: string|null,
     *     createAt: string|null,
     *     updateAt: string|null,
     *     createBy: int|null,
     *     updateBy: int|null,
     *     frequency: int|null,
     *     buyAt: string|null
     * }
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'domainName' => $this->domainName,
            'label' => $this->label,
            'owner' => $this->owner,
            'registrar' => $this->registrar,
            'server' => $this->server,
            'sslExpiresAt' => $this->sslExpiresAt?->format(\DateTimeInterface::ATOM),
            'isAutoSSLRenew' => $this->isAutoSSLRenew,
            'isAutoDomainRenew' => $this->isAutoDomainRenew,
            'status' => $this->status,
            'content' => $this->content,
            'createAt' => $this->createAt?->format(\DateTimeInterface::ATOM),
            'updateAt' => $this->updateAt?->format(\DateTimeInterface::ATOM),
            'createBy' => $this->createBy?->getId(),
            'updateBy' => $this->updateBy?->getId(),
            'frequency' => $this->frequency,
            'buyAt' => $this->buyAt?->format(\DateTimeInterface::ATOM),
        ];
    }
}
