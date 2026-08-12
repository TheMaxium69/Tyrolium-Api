<?php

namespace App\Entity;

use App\Repository\SolidServProductRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: SolidServProductRepository::class)]
#[ORM\Table(name: 'solid_serv_products')]
class SolidServProduct
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['product:read'])]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    #[Groups(['product:read'])]
    #[Assert\NotBlank(message: 'Le nom du produit est obligatoire.')]
    private ?string $name = null;

    #[ORM\Column(type: 'string', length: 255, unique: true)]
    #[Groups(['product:read'])]
    private ?string $slug = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['product:read'])]
    private ?string $description = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Groups(['product:read'])]
    #[Assert\NotBlank(message: 'Le prix mensuel est obligatoire.')]
    private ?string $priceMonthly = '0.00';

    #[ORM\Column(type: 'boolean')]
    #[Groups(['product:read'])]
    private bool $isPublic = true;

    #[ORM\Column(type: 'boolean')]
    #[Groups(['product:read'])]
    private bool $isListed = true;

    #[ORM\Column(type: 'boolean')]
    #[Groups(['product:read'])]
    private bool $isActive = true;

    #[ORM\Column(type: 'integer', nullable: true)]
    #[Groups(['product:read'])]
    private ?int $stock = null;

    #[ORM\Column(type: 'boolean')]
    #[Groups(['product:read'])]
    private bool $isOutOfStock = false;

    /**
     * Spécifications techniques au format JSON (vCPU, RAM, Disque, etc.)
     *
     * @var array<string, mixed>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups(['product:read'])]
    private ?array $specs = [];

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['product:read'])]
    private ?\DateTimeImmutable $createdAt = null;

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
        $this->name = $name;

        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;

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

    public function getPriceMonthly(): ?string
    {
        return $this->priceMonthly;
    }

    public function setPriceMonthly(string $priceMonthly): static
    {
        $this->priceMonthly = $priceMonthly;

        return $this;
    }

    public function isPublic(): bool
    {
        return $this->isPublic;
    }

    public function setIsPublic(bool $isPublic): static
    {
        $this->isPublic = $isPublic;

        return $this;
    }

    public function isListed(): bool
    {
        return $this->isListed;
    }

    public function setIsListed(bool $isListed): static
    {
        $this->isListed = $isListed;

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

    public function getStock(): ?int
    {
        return $this->stock;
    }

    public function setStock(?int $stock): static
    {
        $this->stock = $stock;

        return $this;
    }

    public function isOutOfStock(): bool
    {
        return $this->isOutOfStock;
    }

    public function setIsOutOfStock(bool $isOutOfStock): static
    {
        $this->isOutOfStock = $isOutOfStock;

        return $this;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getSpecs(): ?array
    {
        return $this->specs;
    }

    /**
     * @param array<string, mixed>|null $specs
     */
    public function setSpecs(?array $specs): static
    {
        $this->specs = $specs;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }
}
