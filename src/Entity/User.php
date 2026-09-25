<?php

namespace App\Entity;

use App\Enum\AccessLevel;
use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'users')]
#[UniqueEntity(fields: ['username'], message: "Ce nom d'utilisateur est déjà utilisé.")]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['user:read'])]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 180, unique: true)]
    #[Groups(['user:read'])]
    #[Assert\NotBlank(message: "Le nom d'utilisateur est obligatoire.")]
    #[Assert\Length(min: 3, max: 180, minMessage: "Le nom d'utilisateur doit contenir au moins 3 caractères.", maxMessage: "Le nom d'utilisateur ne peut pas dépasser 180 caractères.")]
    private ?string $username = null;

    #[ORM\Column(type: 'string', length: 255)]
    #[Assert\NotBlank(message: "Le mot de passe est obligatoire.")]
    #[Assert\Length(min: 8, minMessage: "Le mot de passe doit contenir au moins 8 caractères.")]
    private ?string $password = null;

    #[ORM\Column(type: 'string', length: 64, nullable: true, unique: true)]
    private ?string $resetToken = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $resetTokenExpiresAt = null;

    /**
     * Tout JWT émis (claim "iat") avant cette date est rejeté — voir
     * UserProvider::loadUserByIdentifierAndPayload(). Null = aucune
     * restriction, comportement normal.
     */
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $tokensValidSince = null;

    /**
     * @var Collection<int, UserEmail>
     */
    #[ORM\OneToMany(targetEntity: UserEmail::class, mappedBy: 'user', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[Groups(['user:read'])]
    private Collection $emails;

    /**
     * @var Collection<int, UserPermission>
     */
    #[ORM\OneToMany(targetEntity: UserPermission::class, mappedBy: 'user', cascade: ['remove'], orphanRemoval: true)]
    private Collection $permissions;

    /**
     * Porte d'entrée du système de permissions — voir App\Enum\AccessLevel.
     * `user` (défaut) : jamais aucune permission, quoi qu'il y ait dans
     * `permissions` ci-dessus. `owner` n'est jamais mis via ce champ par
     * l'API/CLI, uniquement en DB directement par Maxime.
     */
    #[ORM\Column(type: 'string', length: 20, enumType: AccessLevel::class, options: ['default' => 'user'])]
    private AccessLevel $accessLevel = AccessLevel::USER;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->emails = new ArrayCollection();
        $this->permissions = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(string $username): static
    {
        $this->username = $username;

        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    public function getUserIdentifier(): string
    {
        if (null === $this->username || '' === $this->username) {
            throw new \LogicException('Cannot get the identifier of a user that has no username set.');
        }

        return $this->username;
    }

    /**
     * ROLE_USER toujours présent (client/public). ROLE_INTERNE + les rôles
     * "PERMS_..." des permissions RBAC accordées (Permission::toRole()) ne
     * sont ajoutés que si $accessLevel n'est plus USER — voir
     * App\Enum\AccessLevel et .doc/permissions.md : les lignes de la
     * collection `permissions` d'un compte accessLevel=user ne comptent
     * jamais, même si elles existent en DB (ex: après une rétrogradation
     * interne → user, sans suppression des UserPermission historiques).
     * ROLE_OWNER est ajouté en plus mais ne suffit pas à lui seul pour le
     * bypass absolu : voir App\Security\OwnerBypassVoter, RoleVoter exige
     * une correspondance exacte par attribut, pas de hiérarchie implicite.
     *
     * @return list<string>
     */
    public function getRoles(): array
    {
        $roles = ['ROLE_USER'];

        if (AccessLevel::USER !== $this->accessLevel) {
            $roles[] = 'ROLE_INTERNE';

            foreach ($this->permissions as $userPermission) {
                $permission = $userPermission->getPermission()
                    ?? throw new \LogicException('Une UserPermission persistée doit toujours avoir une Permission (colonne NOT NULL).');
                // collectRoles() déplie aussi les permissions "parapluie"
                // implicitement liées (Permission::$impliedPermissions).
                $roles = array_merge($roles, $permission->collectRoles());
            }
        }

        if (AccessLevel::OWNER === $this->accessLevel) {
            $roles[] = 'ROLE_OWNER';
        }

        return array_values(array_unique($roles));
    }

    public function getAccessLevel(): AccessLevel
    {
        return $this->accessLevel;
    }

    public function setAccessLevel(AccessLevel $accessLevel): static
    {
        $this->accessLevel = $accessLevel;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getResetToken(): ?string
    {
        return $this->resetToken;
    }

    public function getResetTokenExpiresAt(): ?\DateTimeImmutable
    {
        return $this->resetTokenExpiresAt;
    }

    /**
     * Génère un nouveau token de réinitialisation de mot de passe, valide 1h,
     * et remplace tout token précédent.
     */
    public function generateResetToken(): string
    {
        $token = bin2hex(random_bytes(32));

        $this->resetToken = $token;
        $this->resetTokenExpiresAt = new \DateTimeImmutable('+1 hour');

        return $token;
    }

    public function isResetTokenValid(): bool
    {
        return null !== $this->resetToken
            && null !== $this->resetTokenExpiresAt
            && $this->resetTokenExpiresAt > new \DateTimeImmutable();
    }

    public function clearResetToken(): void
    {
        $this->resetToken = null;
        $this->resetTokenExpiresAt = null;
    }

    public function getTokensValidSince(): ?\DateTimeImmutable
    {
        return $this->tokensValidSince;
    }

    /**
     * "Déconnecter de tous les appareils" : tout JWT déjà émis devient
     * instantanément invalide au prochain appel API, quel que soit
     * l'appareil, sans avoir à attendre son expiration naturelle.
     */
    public function invalidateAllTokens(): void
    {
        // La colonne MySQL est un DATETIME sans fraction de seconde (pas de
        // DATETIME(6)) : la précision réelle est la seconde, pas mieux. Voir
        // UserProvider::loadUserByIdentifierAndPayload() qui compare avec
        // "<=" (pas "<") pour cette raison — un token émis la même seconde
        // que cet appel doit être rejeté lui aussi.
        $this->tokensValidSince = new \DateTimeImmutable();
    }

    /**
     * @return Collection<int, UserEmail>
     */
    public function getEmails(): Collection
    {
        return $this->emails;
    }

    public function addEmail(UserEmail $email): static
    {
        if (!$this->emails->contains($email)) {
            $this->emails->add($email);
            $email->setUser($this);
        }

        return $this;
    }

    public function removeEmail(UserEmail $email): static
    {
        $this->emails->removeElement($email);

        return $this;
    }

    public function getDefaultEmail(): ?UserEmail
    {
        foreach ($this->emails as $email) {
            if ($email->isDefault()) {
                return $email;
            }
        }

        return null;
    }

    public function hasVerifiedDefaultEmail(): bool
    {
        $defaultEmail = $this->getDefaultEmail();

        return null !== $defaultEmail && $defaultEmail->isVerified();
    }

    /**
     * @return Collection<int, UserPermission>
     */
    public function getPermissions(): Collection
    {
        return $this->permissions;
    }
}
