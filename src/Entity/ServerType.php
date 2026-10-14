<?php

namespace App\Entity;

use App\Repository\ServerTypeRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Type de serveur (dénomination stricte de l'infrastructure) : VPS, serveur
 * dédié, cloud, proxy, base de données, VPN, serveur interne... Chaque Server
 * appartient à un type, et son nom doit commencer par le préfixe du type
 * (ex: type "vps" -> vps201).
 *
 * namePrefix vaut le plus souvent l'abréviation, mais peut différer : le type
 * "db" nomme ses serveurs "database701".
 */
#[ORM\Entity(repositoryClass: ServerTypeRepository::class)]
#[ORM\Table(name: 'server_type')]
#[UniqueEntity(fields: ['abbreviation'], message: 'Un type avec cette abréviation existe déjà.')]
#[UniqueEntity(fields: ['namePrefix'], message: 'Un type avec ce préfixe de nommage existe déjà.')]
class ServerType
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['server_type:read', 'server_type:identifier'])]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 10, unique: true)]
    #[Groups(['server_type:read', 'server_type:identifier'])]
    #[Assert\NotBlank(message: "L'abréviation est obligatoire.")]
    #[Assert\Regex(pattern: '/^[a-z]{2,10}$/', message: "L'abréviation doit faire 2 à 10 lettres minuscules (ex: vps).")]
    private ?string $abbreviation = null;

    #[ORM\Column(type: 'string', length: 20, unique: true)]
    #[Groups(['server_type:read', 'server_type:identifier'])]
    #[Assert\NotBlank(message: 'Le préfixe de nommage est obligatoire.')]
    #[Assert\Regex(pattern: '/^[a-z]{2,20}$/', message: 'Le préfixe de nommage doit faire 2 à 20 lettres minuscules (ex: vps, database).')]
    private ?string $namePrefix = null;

    #[ORM\Column(type: 'string', length: 255)]
    #[Groups(['server_type:read', 'server_type:identifier'])]
    #[Assert\NotBlank(message: 'Le nom est obligatoire.')]
    #[Assert\Length(max: 255)]
    private ?string $name = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['server_type:read'])]
    private ?string $description = null;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['server_type:read'])]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAbbreviation(): ?string
    {
        return $this->abbreviation;
    }

    public function setAbbreviation(string $abbreviation): static
    {
        $this->abbreviation = strtolower(trim($abbreviation));

        return $this;
    }

    public function getNamePrefix(): ?string
    {
        return $this->namePrefix;
    }

    public function setNamePrefix(string $namePrefix): static
    {
        $this->namePrefix = strtolower(trim($namePrefix));

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = trim($name);

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

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
