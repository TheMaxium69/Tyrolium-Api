<?php

namespace App\Entity;

use App\Entity\Tyrolium\WebSite;
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
     * @var Collection<int, WebSite>
     */
    #[ORM\OneToMany(targetEntity: WebSite::class, mappedBy: 'createdBy')]
    private Collection $updateBy;

    /**
     * @var Collection<int, WebSite>
     */
    #[ORM\OneToMany(targetEntity: WebSite::class, mappedBy: 'updateBy')]
    private Collection $name;

    public function __construct()
    {
        $this->emails = new ArrayCollection();
        $this->updateBy = new ArrayCollection();
        $this->name = new ArrayCollection();
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
     * @return list<string>
     */
    public function getRoles(): array
    {
        // TODO: backed by a real column once role differentiation (RBAC) is needed.
        return ['ROLE_USER'];
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
     * @return Collection<int, WebSite>
     */
    public function getUpdateBy(): Collection
    {
        return $this->updateBy;
    }

    public function addUpdateBy(WebSite $updateBy): static
    {
        if (!$this->updateBy->contains($updateBy)) {
            $this->updateBy->add($updateBy);
            $updateBy->setCreatedBy($this);
        }

        return $this;
    }

    public function removeUpdateBy(WebSite $updateBy): static
    {
        if ($this->updateBy->removeElement($updateBy)) {
            // set the owning side to null (unless already changed)
            if ($updateBy->getCreatedBy() === $this) {
                $updateBy->setCreatedBy(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, WebSite>
     */
    public function getName(): Collection
    {
        return $this->name;
    }

    public function addName(WebSite $name): static
    {
        if (!$this->name->contains($name)) {
            $this->name->add($name);
            $name->setUpdateBy($this);
        }

        return $this;
    }

    public function removeName(WebSite $name): static
    {
        if ($this->name->removeElement($name)) {
            // set the owning side to null (unless already changed)
            if ($name->getUpdateBy() === $this) {
                $name->setUpdateBy(null);
            }
        }

        return $this;
    }
}
