<?php

namespace App\Entity;

use App\Repository\UserEmailRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserEmailRepository::class)]
#[ORM\Table(name: 'user_email')]
#[ORM\UniqueConstraint(name: 'uniq_user_email', columns: ['user_id', 'email'])]
#[ORM\UniqueConstraint(name: 'uniq_default_email', columns: ['default_email_if_set'])]
class UserEmail
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['user:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'emails')]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(type: 'string', length: 180)]
    #[Groups(['user:read'])]
    #[Assert\NotBlank(message: "L'email est obligatoire.")]
    #[Assert\Email(message: "L'adresse email n'est pas valide.")]
    #[Assert\Length(max: 180, maxMessage: "L'email ne peut pas dépasser 180 caractères.")]
    private ?string $email = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    #[Groups(['user:read'])]
    private bool $isDefault = false;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    #[Groups(['user:read'])]
    private bool $isVerified = false;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Groups(['user:read'])]
    private ?\DateTimeImmutable $verifiedAt = null;

    #[ORM\Column(type: 'string', length: 64, nullable: true, unique: true)]
    private ?string $verificationToken = null;

    /**
     * Colonne générée par MySQL (GENERATED ALWAYS AS (IF(is_default, email, NULL)) STORED),
     * jamais écrite par l'ORM — sert uniquement à porter la contrainte unique globale
     * "un seul email default par utilisateur, tous comptes confondus". Voir la migration
     * Version20260809142006. insertable/updatable à false : Doctrine ne doit jamais tenter
     * d'écrire dedans, MySQL s'en charge seul.
     */
    #[ORM\Column(name: 'default_email_if_set', type: 'string', length: 180, nullable: true, insertable: false, updatable: false)]
    private ?string $defaultEmailIfSet = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $verificationTokenExpiresAt = null;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['user:read'])]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function isDefault(): bool
    {
        return $this->isDefault;
    }

    public function setIsDefault(bool $isDefault): static
    {
        $this->isDefault = $isDefault;

        return $this;
    }

    public function isVerified(): bool
    {
        return $this->isVerified;
    }

    public function setIsVerified(bool $isVerified): static
    {
        $this->isVerified = $isVerified;

        return $this;
    }

    public function getVerifiedAt(): ?\DateTimeImmutable
    {
        return $this->verifiedAt;
    }

    public function setVerifiedAt(?\DateTimeImmutable $verifiedAt): static
    {
        $this->verifiedAt = $verifiedAt;

        return $this;
    }

    public function getVerificationToken(): ?string
    {
        return $this->verificationToken;
    }

    public function setVerificationToken(?string $verificationToken): static
    {
        $this->verificationToken = $verificationToken;

        return $this;
    }

    public function getVerificationTokenExpiresAt(): ?\DateTimeImmutable
    {
        return $this->verificationTokenExpiresAt;
    }

    public function setVerificationTokenExpiresAt(?\DateTimeImmutable $verificationTokenExpiresAt): static
    {
        $this->verificationTokenExpiresAt = $verificationTokenExpiresAt;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * Génère un nouveau token de vérification à usage unique, valide 24h, et
     * remplace tout token précédent (ex: relance de vérification).
     */
    public function generateVerificationToken(): string
    {
        $token = bin2hex(random_bytes(32));

        $this->verificationToken = $token;
        $this->verificationTokenExpiresAt = new \DateTimeImmutable('+24 hours');

        return $token;
    }

    public function isVerificationTokenValid(): bool
    {
        return null !== $this->verificationToken
            && null !== $this->verificationTokenExpiresAt
            && $this->verificationTokenExpiresAt > new \DateTimeImmutable();
    }

    public function markAsVerified(): void
    {
        $this->isVerified = true;
        $this->verifiedAt = new \DateTimeImmutable();
        $this->verificationToken = null;
        $this->verificationTokenExpiresAt = null;
    }
}
