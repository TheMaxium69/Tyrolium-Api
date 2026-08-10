<?php

namespace App\Entity\Tyrolium;

use App\Repository\Tyrolium\AnalyticsInputRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: AnalyticsInputRepository::class)]
#[ORM\Table(name: 'tyrolium_analytics_input')]
class AnalyticsInput
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['analytics:input:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['analytics:input:read'])]
    private ?AnalyticsProject $project = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Groups(['analytics:input:read'])]
    private ?string $ip = null;

    #[ORM\Column(length: 255)]
    #[Groups(['analytics:input:read'])]
    private ?string $pageName = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Groups(['analytics:input:read'])]
    private ?string $uri = null;

    #[ORM\Column]
    #[Groups(['analytics:input:read'])]
    private ?bool $isLogin = null;

    #[ORM\Column]
    #[Groups(['analytics:input:read'])]
    private ?\DateTimeImmutable $createdAt = null;

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

    public function setProject(?AnalyticsProject $project): static
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

    public function isLogin(): ?bool
    {
        return $this->isLogin;
    }

    public function setIsLogin(bool $isLogin): static
    {
        $this->isLogin = $isLogin;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    /**
     * @return array{id: int|null, project: array<string, mixed>|null, ip: string|null, pageName: string|null, uri: string|null, isLogin: bool|null, createdAt: string|null}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'project' => $this->project?->toArray(),
            'ip' => $this->ip,
            'pageName' => $this->pageName,
            'uri' => $this->uri,
            'isLogin' => $this->isLogin,
            'createdAt' => $this->createdAt?->format(\DateTimeInterface::ATOM),
        ];
    }
}
