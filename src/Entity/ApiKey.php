<?php

namespace App\Entity;

use App\Enum\ApiKeyEnvironment;
use App\Repository\ApiKeyRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Identifiant d'un système tiers (TyroServ, Gamenium, site client...) — voir
 * .doc/permissions.md. Implémente UserInterface au même titre que `User` :
 * une clé API est authentifiée via le firewall `api` (header `X-Api-Key`,
 * voir ApiKeyTokenHandler) et porte ses propres permissions granulaires
 * (ApiKeyPermission, même mécanisme que UserPermission/Permission::toRole()),
 * mais **jamais** ROLE_USER/ROLE_INTERNE/ROLE_OWNER — ces rôles n'ont de sens
 * que pour un compte humain, une clé API ne peut donc jamais bénéficier du
 * bypass de App\Security\OwnerBypassVoter (qui ne regarde que ROLE_OWNER).
 *
 * La clé brute (`tyrokey_{live|test}_<64 hex>`) n'est **jamais** stockée —
 * seul son hash SHA-256 (`$keyHash`) l'est, recherché par égalité exacte en
 * DB dans ApiKeyRepository::findValidByRawKey() (pas un hash_equals() PHP :
 * on compare un digest SHA-256 déjà fixe et uniformément aléatoire, pas un
 * secret en clair — la classe de timing-attack que hash_equals() évite ne
 * s'applique pas ici). SHA-256 (pas bcrypt) est volontaire : la clé a déjà
 * 256 bits d'entropie générés par random_bytes(), contrairement à un mot de
 * passe humain — un hash rapide suffit, bcrypt coûterait cher sur chaque
 * requête API sans bénéfice réel.
 */
#[ORM\Entity(repositoryClass: ApiKeyRepository::class)]
#[ORM\Table(name: 'api_key')]
class ApiKey implements UserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $label = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $content = null;

    #[ORM\Column(type: 'string', length: 20, enumType: ApiKeyEnvironment::class)]
    private ApiKeyEnvironment $environment = ApiKeyEnvironment::TEST;

    /**
     * SHA-256 de la clé brute, jamais la clé elle-même — voir docblock de
     * la classe.
     */
    #[ORM\Column(type: 'string', length: 64, unique: true)]
    private ?string $keyHash = null;

    /**
     * Aperçu affichable après coup (ex: "tyrolium_live_a1b2c3d4…") — assez
     * pour reconnaître la clé dans une liste, pas assez pour l'utiliser.
     */
    #[ORM\Column(type: 'string', length: 40)]
    private ?string $keyPreview = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $expiresAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $revokedAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'created_by_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    private ?User $createdBy = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    /**
     * @var Collection<int, ApiKeyPermission>
     */
    #[ORM\OneToMany(targetEntity: ApiKeyPermission::class, mappedBy: 'apiKey', cascade: ['remove'], orphanRemoval: true)]
    private Collection $permissions;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->permissions = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(string $label): static
    {
        $this->label = $label;

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

    public function getEnvironment(): ApiKeyEnvironment
    {
        return $this->environment;
    }

    public function setEnvironment(ApiKeyEnvironment $environment): static
    {
        $this->environment = $environment;

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

    public function getKeyPreview(): ?string
    {
        return $this->keyPreview;
    }

    public function setKeyPreview(string $keyPreview): static
    {
        $this->keyPreview = $keyPreview;

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

    public function getRevokedAt(): ?\DateTimeImmutable
    {
        return $this->revokedAt;
    }

    public function revoke(): void
    {
        $this->revokedAt = new \DateTimeImmutable();
    }

    public function isRevoked(): bool
    {
        return null !== $this->revokedAt;
    }

    public function isExpired(): bool
    {
        return null !== $this->expiresAt && $this->expiresAt <= new \DateTimeImmutable();
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

    /**
     * @return Collection<int, ApiKeyPermission>
     */
    public function getPermissions(): Collection
    {
        return $this->permissions;
    }

    /**
     * Jamais ROLE_USER/ROLE_INTERNE/ROLE_OWNER — voir docblock de la classe.
     *
     * @return list<string>
     */
    public function getRoles(): array
    {
        $roles = [];
        foreach ($this->permissions as $apiKeyPermission) {
            $permission = $apiKeyPermission->getPermission()
                ?? throw new \LogicException('Une ApiKeyPermission persistée doit toujours avoir une Permission (colonne NOT NULL).');
            $roles = array_merge($roles, $permission->collectRoles());
        }

        return array_values(array_unique($roles));
    }

    public function getUserIdentifier(): string
    {
        return 'apikey:'.($this->id ?? throw new \LogicException('Cannot get the identifier of an ApiKey that has no id set.'));
    }
}
