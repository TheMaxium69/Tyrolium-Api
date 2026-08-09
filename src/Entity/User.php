<?php

namespace App\Entity;

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
     * @var Collection<int, UserEmail>
     */
    #[ORM\OneToMany(targetEntity: UserEmail::class, mappedBy: 'user', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[Groups(['user:read'])]
    private Collection $emails;

    public function __construct()
    {
        $this->emails = new ArrayCollection();
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
}
