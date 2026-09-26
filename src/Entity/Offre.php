<?php

namespace App\Entity;

use App\Enum\OffreVisibility;
use App\Repository\OffreRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Catalogue des offres Tyrolium (ex: "Site Web Premium") — voir
 * TyroliumPrestationController dans le cahier des charges. Le lien avec un
 * client précis (qui a souscrit, avec quel statut) vit dans Prestation, pas
 * ici — même séparation catalogue/attribution que Permission/UserPermission.
 *
 * Pas de collection inverse vers Prestation ici (même précédent que
 * Permission, qui n'a pas de collection inverse vers UserPermission) —
 * "combien de personnes ont acheté cette offre" se calcule via une requête
 * (PrestationRepository::count(['offre' => $offre])), pas une collection
 * chargée en mémoire.
 */
#[ORM\Entity(repositoryClass: OffreRepository::class)]
#[ORM\Table(name: 'offre')]
#[UniqueEntity(fields: ['tagName'], message: 'Une offre avec ce tagName existe déjà.')]
class Offre
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['offre:read'])]
    private ?int $id = null;

    /**
     * Identifiant technique (ex: "web_premium_1") — format volontairement
     * libre (décision de Maxime, 26/09/2026), juste unique.
     */
    #[ORM\Column(type: 'string', length: 100, unique: true)]
    #[Groups(['offre:read'])]
    #[Assert\NotBlank(message: 'Le tagName est obligatoire.')]
    private ?string $tagName = null;

    #[ORM\Column(type: 'string', length: 255)]
    #[Groups(['offre:read'])]
    #[Assert\NotBlank(message: 'Le displayName est obligatoire.')]
    private ?string $displayName = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['offre:read'])]
    private ?string $description = null;

    #[ORM\Column(type: 'string', length: 20, enumType: OffreVisibility::class)]
    #[Groups(['offre:read'])]
    private OffreVisibility $visibility = OffreVisibility::LISTED;

    /**
     * En centimes, nullable — une offre CUSTOM n'a pas de prix catalogue
     * fixe (négocié au devis, voir TyroliumCompta à venir).
     */
    #[ORM\Column(type: 'integer', nullable: true)]
    #[Groups(['offre:read'])]
    #[Assert\PositiveOrZero(message: 'Le prix ne peut pas être négatif.')]
    private ?int $price = null;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    #[Groups(['offre:read'])]
    private bool $isActive = true;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['offre:read'])]
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
        $this->tagName = $tagName;

        return $this;
    }

    public function getDisplayName(): ?string
    {
        return $this->displayName;
    }

    public function setDisplayName(string $displayName): static
    {
        $this->displayName = $displayName;

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

    public function getPrice(): ?int
    {
        return $this->price;
    }

    public function setPrice(?int $price): static
    {
        $this->price = $price;

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

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
