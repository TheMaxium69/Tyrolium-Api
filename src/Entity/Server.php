<?php

namespace App\Entity;

use App\Enum\ServerStatus;
use App\Repository\ServerRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Une machine du parc (cahier des charges, SolidServDashboardController) : serveur
 * interne, VPS, dédié, cloud, proxy, base de données, VPN... Pour SolidServ c'est
 * l'équivalent de Prestation : un Server s'appuie sur un SolidServProduct et
 * appartient à un client — ou n'a ni produit ni client s'il est interne.
 *
 * Le nom suit la dénomination du type : préfixe du ServerType + numéro (vps201).
 * Cette cohérence est validée par le controller. Le paiement n'est pas géré ici.
 */
#[ORM\Entity(repositoryClass: ServerRepository::class)]
#[ORM\Table(name: 'server')]
#[UniqueEntity(fields: ['name'], message: 'Un serveur avec ce nom existe déjà.')]
class Server
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['server:read', 'server:identifier'])]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 60, unique: true)]
    #[Groups(['server:read', 'server:identifier'])]
    #[Assert\NotBlank(message: 'Le nom est obligatoire.')]
    #[Assert\Regex(pattern: '/^[a-z]+[0-9]+$/', message: 'Le nom doit être le préfixe du type suivi d\'un numéro (ex: vps201).')]
    private ?string $name = null;

    #[ORM\ManyToOne(targetEntity: ServerType::class)]
    #[ORM\JoinColumn(name: 'type_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    #[Groups(['server:read', 'server:identifier'])]
    private ?ServerType $type = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    #[Groups(['server:read', 'server:identifier'])]
    #[Assert\Length(max: 100)]
    private ?string $alias = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['server:read'])]
    private ?string $description = null;

    /**
     * Emplacement physique ou data center (ex: "Gravelines - France").
     */
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Groups(['server:read'])]
    #[Assert\Length(max: 255)]
    private ?string $location = null;

    #[ORM\Column(type: 'string', length: 253, nullable: true)]
    #[Groups(['server:read', 'server:identifier'])]
    #[Assert\Regex(
        pattern: '/^(?=.{1,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/',
        message: 'Hostname invalide (ex: vps201.tyrolium.fr, sans http:// ni chemin).',
    )]
    private ?string $hostname = null;

    /**
     * Projet / filiale que le serveur sert (ex: "TyroServ", "SolidServ"), libre.
     */
    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    #[Groups(['server:read'])]
    #[Assert\Length(max: 50)]
    private ?string $project = null;

    #[ORM\Column(type: 'string', length: 20, enumType: ServerStatus::class, options: ['default' => 'active'])]
    #[Groups(['server:read', 'server:identifier'])]
    private ServerStatus $status = ServerStatus::ACTIVE;

    /**
     * Date de mise en service, null si inconnue.
     */
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Groups(['server:read'])]
    private ?\DateTimeImmutable $startedAt = null;

    #[ORM\ManyToOne(targetEntity: SolidServProduct::class)]
    #[ORM\JoinColumn(name: 'product_id', referencedColumnName: 'id', nullable: true, onDelete: 'RESTRICT')]
    #[Groups(['server:read'])]
    private ?SolidServProduct $product = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    #[Groups(['server:read'])]
    private ?User $user = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Groups(['server:read'])]
    #[Assert\Length(max: 255)]
    private ?string $clientName = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'created_by_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    #[Groups(['server:read'])]
    private ?User $createdBy = null;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['server:read'])]
    private \DateTimeImmutable $createdAt;

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
        $this->name = strtolower(trim($name));

        return $this;
    }

    public function getType(): ?ServerType
    {
        return $this->type;
    }

    public function setType(ServerType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getAlias(): ?string
    {
        return $this->alias;
    }

    public function setAlias(?string $alias): static
    {
        $this->alias = $alias;

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

    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function setLocation(?string $location): static
    {
        $this->location = $location;

        return $this;
    }

    public function getHostname(): ?string
    {
        return $this->hostname;
    }

    public function setHostname(?string $hostname): static
    {
        $this->hostname = null === $hostname ? null : strtolower(trim($hostname));

        return $this;
    }

    public function getProject(): ?string
    {
        return $this->project;
    }

    public function setProject(?string $project): static
    {
        $this->project = $project;

        return $this;
    }

    public function getStatus(): ServerStatus
    {
        return $this->status;
    }

    public function setStatus(ServerStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getStartedAt(): ?\DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function setStartedAt(?\DateTimeImmutable $startedAt): static
    {
        $this->startedAt = $startedAt;

        return $this;
    }

    public function getProduct(): ?SolidServProduct
    {
        return $this->product;
    }

    public function setProduct(?SolidServProduct $product): static
    {
        $this->product = $product;

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

    public function getClientName(): ?string
    {
        return $this->clientName;
    }

    public function setClientName(?string $clientName): static
    {
        $this->clientName = $clientName;

        return $this;
    }

    /**
     * Vrai si le serveur appartient à un client (compte Useritium ou simple nom), faux s'il est interne.
     */
    #[Groups(['server:read'])]
    #[SerializedName('hasClient')]
    public function hasClient(): bool
    {
        return null !== $this->user || null !== $this->clientName;
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
