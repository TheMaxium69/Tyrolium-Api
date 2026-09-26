<?php

namespace App\Entity;

use App\Enum\PrestationStatus;
use App\Repository\PrestationRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Lien entre une Offre (catalogue) et un client — voir Offre.php pour la
 * séparation catalogue/attribution. Un client peut ne pas avoir de compte
 * Useritium ($user null) : $clientName/$clientEmail servent alors de repli
 * (décision de Maxime, 26/09/2026). Au moins l'un des deux (user OU
 * clientName+clientEmail) doit être renseigné — pas contraint au niveau de
 * l'entité (laissé à la validation du controller, à écrire).
 */
#[ORM\Entity(repositoryClass: PrestationRepository::class)]
#[ORM\Table(name: 'prestation')]
class Prestation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['prestation:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Offre::class)]
    #[ORM\JoinColumn(name: 'offre_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    #[Groups(['prestation:read'])]
    private ?Offre $offre = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    #[Groups(['prestation:read'])]
    private ?User $user = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Groups(['prestation:read'])]
    private ?string $clientName = null;

    #[ORM\Column(type: 'string', length: 180, nullable: true)]
    #[Groups(['prestation:read'])]
    #[Assert\Email(message: "L'adresse email n'est pas valide.")]
    private ?string $clientEmail = null;

    #[ORM\Column(type: 'string', length: 20, enumType: PrestationStatus::class)]
    #[Groups(['prestation:read'])]
    private PrestationStatus $status = PrestationStatus::PENDING;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['prestation:read'])]
    private ?string $content = null;

    /**
     * Avancement de la prestation, 0-100 — affiché en barre de progression
     * sur le dashboard client.
     */
    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    #[Groups(['prestation:read'])]
    #[Assert\Range(min: 0, max: 100, notInRangeMessage: 'Le progress doit être compris entre 0 et 100.')]
    private int $progress = 0;

    /**
     * En centimes, nullable — le prix réellement appliqué à ce client (peut
     * différer du prix catalogue de l'Offre : remise, devis négocié...).
     */
    #[ORM\Column(type: 'integer', nullable: true)]
    #[Groups(['prestation:read'])]
    #[Assert\PositiveOrZero(message: 'Le prix ne peut pas être négatif.')]
    private ?int $price = null;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['prestation:read'])]
    private \DateTimeImmutable $startedAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Groups(['prestation:read'])]
    private ?\DateTimeImmutable $endedAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'created_by_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    #[Groups(['prestation:read'])]
    private ?User $createdBy = null;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['prestation:read'])]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->startedAt = new \DateTimeImmutable();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOffre(): ?Offre
    {
        return $this->offre;
    }

    public function setOffre(Offre $offre): static
    {
        $this->offre = $offre;

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

    public function getClientEmail(): ?string
    {
        return $this->clientEmail;
    }

    public function setClientEmail(?string $clientEmail): static
    {
        $this->clientEmail = $clientEmail;

        return $this;
    }

    public function getStatus(): PrestationStatus
    {
        return $this->status;
    }

    public function setStatus(PrestationStatus $status): static
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

    public function getProgress(): int
    {
        return $this->progress;
    }

    public function setProgress(int $progress): static
    {
        $this->progress = $progress;

        return $this;
    }

    public function getPrice(): ?int
    {
        return $this->price;
    }

    public function setPrice(?int $price): static
    {
        $this->price = $price;

        return $this;
    }

    public function getStartedAt(): \DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function setStartedAt(\DateTimeImmutable $startedAt): static
    {
        $this->startedAt = $startedAt;

        return $this;
    }

    public function getEndedAt(): ?\DateTimeImmutable
    {
        return $this->endedAt;
    }

    public function setEndedAt(?\DateTimeImmutable $endedAt): static
    {
        $this->endedAt = $endedAt;

        return $this;
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
