<?php

namespace App\Entity;

use App\Enum\Filiale;
use App\Enum\WebsiteOwnerType;
use App\Enum\WebsiteStatus;
use App\Repository\WebsiteRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Registre des noms de domaine / sites web gérés par la holding (cahier des
 * charges, TyroliumWebSiteController). Un site appartient soit à une filiale,
 * soit à un client : dans ce cas il peut être rattaché à une Prestation, à un
 * compte Useritium, ou seulement à un nom ($clientName) — même logique de repli
 * que Prestation. La liaison avec les serveurs SolidServ viendra plus tard.
 *
 * La cohérence propriétaire/filiale/client est validée par le controller.
 */
#[ORM\Entity(repositoryClass: WebsiteRepository::class)]
#[ORM\Table(name: 'website')]
#[UniqueEntity(fields: ['domainName'], message: 'Ce nom de domaine est déjà dans le registre.')]
class Website
{
    public const GIT_REPOSITORY_PATTERN = '/^(https?:\/\/[^\s]+|ssh:\/\/[^\s]+|git@[^\s:]+:[^\s]+)$/';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['website:read'])]
    private ?int $id = null;

    /**
     * Nom de domaine principal, en minuscules, sans schéma ni chemin.
     */
    #[ORM\Column(type: 'string', length: 253, unique: true)]
    #[Groups(['website:read'])]
    #[Assert\NotBlank(message: 'Le nom de domaine est obligatoire.')]
    #[Assert\Regex(
        pattern: '/^(?=.{1,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/',
        message: 'Nom de domaine invalide (ex: exemple.fr, sans http:// ni chemin).',
    )]
    private ?string $domainName = null;

    #[ORM\Column(type: 'string', length: 255)]
    #[Groups(['website:read'])]
    #[Assert\NotBlank(message: 'Le nom du site est obligatoire.')]
    #[Assert\Length(max: 255)]
    private ?string $name = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['website:read'])]
    private ?string $description = null;

    #[ORM\Column(type: 'string', length: 20, enumType: WebsiteOwnerType::class)]
    #[Groups(['website:read'])]
    private WebsiteOwnerType $ownerType = WebsiteOwnerType::CLIENT;

    /**
     * Renseignée uniquement si ownerType = filiale.
     */
    #[ORM\Column(type: 'string', length: 20, nullable: true, enumType: Filiale::class)]
    #[Groups(['website:read'])]
    private ?Filiale $filiale = null;

    #[ORM\ManyToOne(targetEntity: Prestation::class)]
    #[ORM\JoinColumn(name: 'prestation_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    #[Groups(['website:read'])]
    private ?Prestation $prestation = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    #[Groups(['website:read'])]
    private ?User $user = null;

    /**
     * Serveur du parc qui héberge le site (un serveur peut en héberger plusieurs).
     * Supprimer le serveur détache simplement le site.
     */
    #[ORM\ManyToOne(targetEntity: Server::class)]
    #[ORM\JoinColumn(name: 'server_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    #[Groups(['website:read'])]
    private ?Server $server = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Groups(['website:read'])]
    #[Assert\Length(max: 255)]
    private ?string $clientName = null;

    #[ORM\Column(type: 'string', length: 20, enumType: WebsiteStatus::class, options: ['default' => 'active'])]
    #[Groups(['website:read'])]
    private WebsiteStatus $status = WebsiteStatus::ACTIVE;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    #[Groups(['website:read'])]
    #[Assert\Length(max: 100)]
    private ?string $registrar = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Groups(['website:read'])]
    private ?\DateTimeImmutable $domainExpiresAt = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    #[Groups(['website:read'])]
    private bool $autoRenew = false;

    /**
     * Certificat SSL : échéance, renouvellement automatique ou non, et émetteur
     * (ex: "Let's Encrypt"). Indépendant de l'échéance du nom de domaine.
     */
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Groups(['website:read'])]
    private ?\DateTimeImmutable $sslExpiresAt = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    #[Groups(['website:read'])]
    private bool $sslAutoRenew = false;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    #[Groups(['website:read'])]
    #[Assert\Length(max: 100)]
    private ?string $sslProvider = null;

    /**
     * URL du dépôt git du site (https://..., ssh://... ou git@hote:chemin).
     */
    #[ORM\Column(type: 'string', length: 500, nullable: true)]
    #[Groups(['website:read'])]
    #[Assert\Length(max: 500)]
    #[Assert\Regex(
        pattern: self::GIT_REPOSITORY_PATTERN,
        message: 'Dépôt git invalide (https://..., ssh://... ou git@hote:chemin).',
    )]
    private ?string $gitRepository = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'created_by_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    #[Groups(['website:read'])]
    private ?User $createdBy = null;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['website:read'])]
    private \DateTimeImmutable $createdAt;

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
        $this->domainName = strtolower(trim($domainName));

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = trim($name);

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

    public function getOwnerType(): WebsiteOwnerType
    {
        return $this->ownerType;
    }

    public function setOwnerType(WebsiteOwnerType $ownerType): static
    {
        $this->ownerType = $ownerType;

        return $this;
    }

    public function getFiliale(): ?Filiale
    {
        return $this->filiale;
    }

    public function setFiliale(?Filiale $filiale): static
    {
        $this->filiale = $filiale;

        return $this;
    }

    public function getPrestation(): ?Prestation
    {
        return $this->prestation;
    }

    public function setPrestation(?Prestation $prestation): static
    {
        $this->prestation = $prestation;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getServer(): ?Server
    {
        return $this->server;
    }

    public function setServer(?Server $server): static
    {
        $this->server = $server;

        return $this;
    }

    public function getClientName(): ?string
    {
        return $this->clientName;
    }

    public function setClientName(?string $clientName): static
    {
        $this->clientName = $clientName;

        return $this;
    }

    public function getStatus(): WebsiteStatus
    {
        return $this->status;
    }

    public function setStatus(WebsiteStatus $status): static
    {
        $this->status = $status;

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

    public function getDomainExpiresAt(): ?\DateTimeImmutable
    {
        return $this->domainExpiresAt;
    }

    public function setDomainExpiresAt(?\DateTimeImmutable $domainExpiresAt): static
    {
        $this->domainExpiresAt = $domainExpiresAt;

        return $this;
    }

    public function isAutoRenew(): bool
    {
        return $this->autoRenew;
    }

    public function setAutoRenew(bool $autoRenew): static
    {
        $this->autoRenew = $autoRenew;

        return $this;
    }

    public function getSslExpiresAt(): ?\DateTimeImmutable
    {
        return $this->sslExpiresAt;
    }

    public function setSslExpiresAt(?\DateTimeImmutable $sslExpiresAt): static
    {
        $this->sslExpiresAt = $sslExpiresAt;

        return $this;
    }

    public function isSslAutoRenew(): bool
    {
        return $this->sslAutoRenew;
    }

    public function setSslAutoRenew(bool $sslAutoRenew): static
    {
        $this->sslAutoRenew = $sslAutoRenew;

        return $this;
    }

    public function getSslProvider(): ?string
    {
        return $this->sslProvider;
    }

    public function setSslProvider(?string $sslProvider): static
    {
        $this->sslProvider = $sslProvider;

        return $this;
    }

    public function getGitRepository(): ?string
    {
        return $this->gitRepository;
    }

    public function setGitRepository(?string $gitRepository): static
    {
        $this->gitRepository = $gitRepository;

        return $this;
    }

    /**
     * Jours restants avant l'échéance du domaine (négatif = déjà expiré), null si aucune échéance.
     */
    #[Groups(['website:read'])]
    public function getDaysUntilExpiry(): ?int
    {
        return self::daysUntil($this->domainExpiresAt);
    }

    /**
     * Jours restants avant l'échéance du certificat SSL (négatif = déjà expiré), null si aucune échéance.
     */
    #[Groups(['website:read'])]
    public function getSslDaysUntilExpiry(): ?int
    {
        return self::daysUntil($this->sslExpiresAt);
    }

    private static function daysUntil(?\DateTimeImmutable $date): ?int
    {
        if (null === $date) {
            return null;
        }

        return (int) (new \DateTimeImmutable('today'))->diff($date->setTime(0, 0))->format('%r%a');
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
