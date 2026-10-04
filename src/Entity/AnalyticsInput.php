<?php

namespace App\Entity;

use App\Repository\AnalyticsInputRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\MaxDepth;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Une visite de page remontée par le script de tracking d'un AnalyticsProject.
 * Chaque chargement de page = une ligne (pas de dédoublonnage).
 */
#[ORM\Entity(repositoryClass: AnalyticsInputRepository::class)]
#[ORM\Table(name: 'analytics_input')]
#[ORM\Index(name: 'idx_analytics_input_project_created', columns: ['project_id', 'created_at'])]
class AnalyticsInput
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['analytics:input:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: AnalyticsProject::class)]
    #[ORM\JoinColumn(name: 'project_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[Groups(['analytics:input:read'])]
    #[MaxDepth(1)]
    private ?AnalyticsProject $project = null;

    /**
     * IP en clair, choix explicite de Maxime (donnée personnelle, RGPD à garder en tête).
     */
    #[ORM\Column(type: 'string', length: 45)]
    #[Groups(['analytics:input:read'])]
    #[Assert\NotBlank(message: "L'IP est obligatoire.")]
    #[Assert\Length(max: 45)]
    private ?string $ip = null;

    #[ORM\Column(type: 'string', length: 255)]
    #[Groups(['analytics:input:read'])]
    #[Assert\NotBlank(message: 'Le nom de page est obligatoire.')]
    #[Assert\Length(max: 255)]
    private ?string $pageName = null;

    #[ORM\Column(type: 'text')]
    #[Groups(['analytics:input:read'])]
    #[Assert\NotBlank(message: "L'URI est obligatoire.")]
    private ?string $uri = null;

    #[ORM\Column(type: 'boolean')]
    #[Groups(['analytics:input:read'])]
    private bool $isLogin = false;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['analytics:input:read'])]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProject(): ?AnalyticsProject
    {
        return $this->project;
    }

    public function setProject(AnalyticsProject $project): static
    {
        $this->project = $project;

        return $this;
    }

    public function getIp(): ?string
    {
        return $this->ip;
    }

    public function setIp(string $ip): static
    {
        $this->ip = $ip;

        return $this;
    }

    public function getPageName(): ?string
    {
        return $this->pageName;
    }

    public function setPageName(string $pageName): static
    {
        $this->pageName = $pageName;

        return $this;
    }

    public function getUri(): ?string
    {
        return $this->uri;
    }

    public function setUri(string $uri): static
    {
        $this->uri = $uri;

        return $this;
    }

    public function isLogin(): bool
    {
        return $this->isLogin;
    }

    public function setIsLogin(bool $isLogin): static
    {
        $this->isLogin = $isLogin;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
