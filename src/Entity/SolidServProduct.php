<?php

namespace App\Entity;

use App\Enum\OffreVisibility;
use App\Repository\SolidServProductRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Catalogue des produits SolidServ (hébergement / serveurs) — pour SolidServ
 * l'équivalent de ce qu'est Offre pour Tyrolium. Les instances de serveurs des
 * clients (SolidServDashboardController, à venir) se rattacheront à un produit,
 * comme une Prestation se rattache à une Offre.
 *
 * Visibilité : "listed" = visible publiquement sur solidserv.fr, "custom" =
 * sur-mesure, dédié à un contrat précis (ex: Cabinet Marthelot).
 * Les liaisons Stripe / Lemon Squeezy viendront avec TyroliumCompta.
 */
#[ORM\Entity(repositoryClass: SolidServProductRepository::class)]
#[ORM\Table(name: 'solidserv_product')]
#[UniqueEntity(fields: ['tagName'], message: 'Un produit avec ce tagName existe déjà.')]
class SolidServProduct
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['solidserv_product:read', 'solidserv_product:identifier'])]
    private ?int $id = null;

    /**
     * Identifiant technique (ex: "vps_start_1"), format libre, unique.
     */
    #[ORM\Column(type: 'string', length: 100, unique: true)]
    #[Groups(['solidserv_product:read', 'solidserv_product:identifier'])]
    #[Assert\NotBlank(message: 'Le tagName est obligatoire.')]
    #[Assert\Length(max: 100)]
    private ?string $tagName = null;

    #[ORM\Column(type: 'string', length: 255)]
    #[Groups(['solidserv_product:read', 'solidserv_product:identifier'])]
    #[Assert\NotBlank(message: 'Le displayName est obligatoire.')]
    #[Assert\Length(max: 255)]
    private ?string $displayName = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['solidserv_product:read'])]
    private ?string $description = null;

    #[ORM\Column(type: 'string', length: 20, enumType: OffreVisibility::class)]
    #[Groups(['solidserv_product:read'])]
    private OffreVisibility $visibility = OffreVisibility::LISTED;

    /**
     * Grille tarifaire, en centimes. Null = pas de tarif pour cette période
     * (un produit sur-mesure est souvent négocié au devis).
     */
    #[ORM\Column(type: 'integer', nullable: true)]
    #[Groups(['solidserv_product:read'])]
    #[Assert\PositiveOrZero(message: 'Le prix mensuel ne peut pas être négatif.')]
    private ?int $priceMonthly = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    #[Groups(['solidserv_product:read'])]
    #[Assert\PositiveOrZero(message: 'Le prix annuel ne peut pas être négatif.')]
    private ?int $priceYearly = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    #[Groups(['solidserv_product:read'])]
    #[Assert\Positive(message: 'Le nombre de cœurs doit être supérieur à 0.')]
    private ?int $cpuCores = null;

    /**
     * En Mo.
     */
    #[ORM\Column(type: 'integer', nullable: true)]
    #[Groups(['solidserv_product:read'])]
    #[Assert\Positive(message: 'La RAM doit être supérieure à 0.')]
    private ?int $ramMb = null;

    /**
     * En Go.
     */
    #[ORM\Column(type: 'integer', nullable: true)]
    #[Groups(['solidserv_product:read'])]
    #[Assert\Positive(message: 'Le stockage doit être supérieur à 0.')]
    private ?int $storageGb = null;

    /**
     * Unités disponibles. Null = illimité, 0 = rupture de stock.
     */
    #[ORM\Column(type: 'integer', nullable: true)]
    #[Groups(['solidserv_product:read'])]
    #[Assert\PositiveOrZero(message: 'Le stock ne peut pas être négatif.')]
    private ?int $stock = null;

    /**
     * Ordre d'affichage dans la grille (plus petit = en premier).
     */
    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    #[Groups(['solidserv_product:read'])]
    private int $position = 0;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    #[Groups(['solidserv_product:read'])]
    private bool $isActive = true;

    /**
     * Offre du catalogue Tyrolium qui vend ce produit, si elle existe.
     */
    #[ORM\ManyToOne(targetEntity: Offre::class)]
    #[ORM\JoinColumn(name: 'offre_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    #[Groups(['solidserv_product:read'])]
    private ?Offre $offre = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'created_by_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    #[Groups(['solidserv_product:read'])]
    private ?User $createdBy = null;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['solidserv_product:read'])]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTagName(): ?string
    {
        return $this->tagName;
    }

    public function setTagName(string $tagName): static
    {
        $this->tagName = trim($tagName);

        return $this;
    }

    public function getDisplayName(): ?string
    {
        return $this->displayName;
    }

    public function setDisplayName(string $displayName): static
    {
        $this->displayName = trim($displayName);

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

    public function getVisibility(): OffreVisibility
    {
        return $this->visibility;
    }

    public function setVisibility(OffreVisibility $visibility): static
    {
        $this->visibility = $visibility;

        return $this;
    }

    public function getPriceMonthly(): ?int
    {
        return $this->priceMonthly;
    }

    public function setPriceMonthly(?int $priceMonthly): static
    {
        $this->priceMonthly = $priceMonthly;

        return $this;
    }

    public function getPriceYearly(): ?int
    {
        return $this->priceYearly;
    }

    public function setPriceYearly(?int $priceYearly): static
    {
        $this->priceYearly = $priceYearly;

        return $this;
    }

    public function getCpuCores(): ?int
    {
        return $this->cpuCores;
    }

    public function setCpuCores(?int $cpuCores): static
    {
        $this->cpuCores = $cpuCores;

        return $this;
    }

    public function getRamMb(): ?int
    {
        return $this->ramMb;
    }

    public function setRamMb(?int $ramMb): static
    {
        $this->ramMb = $ramMb;

        return $this;
    }

    public function getStorageGb(): ?int
    {
        return $this->storageGb;
    }

    public function setStorageGb(?int $storageGb): static
    {
        $this->storageGb = $storageGb;

        return $this;
    }

    public function getStock(): ?int
    {
        return $this->stock;
    }

    public function setStock(?int $stock): static
    {
        $this->stock = $stock;

        return $this;
    }

    /**
     * Disponible à la commande côté stock (illimité ou au moins une unité) ; n'inclut pas isActive.
     */
    #[Groups(['solidserv_product:read'])]
    public function isInStock(): bool
    {
        return null === $this->stock || $this->stock > 0;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function getOffre(): ?Offre
    {
        return $this->offre;
    }

    public function setOffre(?Offre $offre): static
    {
        $this->offre = $offre;

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
