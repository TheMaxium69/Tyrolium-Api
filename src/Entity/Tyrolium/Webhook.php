<?php

namespace App\Entity\Tyrolium;

use App\Repository\Tyrolium\WebhookRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: WebhookRepository::class)]
#[ORM\Table(name: 'tyrolium_webhook')]
class Webhook
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['webhook:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['webhook:read'])]
    private ?string $name = null;

    #[ORM\Column(length: 500)]
    #[Groups(['webhook:read'])]
    private ?string $url = null;

    #[ORM\Column(length: 180, nullable: true)]
    #[Groups(['webhook:read'])]
    private ?string $secret = null;

    /**
     * @var array<string>
     */
    #[ORM\Column(type: 'json')]
    #[Groups(['webhook:read'])]
    private array $events = [];

    #[ORM\Column(length: 180, nullable: true)]
    #[Groups(['webhook:read'])]
    private ?string $projectTag = null;

    #[ORM\Column(options: ['default' => true])]
    #[Groups(['webhook:read'])]
    private bool $isActive = true;

    #[ORM\Column]
    #[Groups(['webhook:read'])]
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

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(string $url): static
    {
        $this->url = $url;

        return $this;
    }

    public function getSecret(): ?string
    {
        return $this->secret;
    }

    public function setSecret(?string $secret): static
    {
        $this->secret = $secret;

        return $this;
    }

    /**
     * @return array<string>
     */
    public function getEvents(): array
    {
        return $this->events;
    }

    /**
     * @param array<string> $events
     */
    public function setEvents(array $events): static
    {
        $this->events = $events;

        return $this;
    }

    public function getProjectTag(): ?string
    {
        return $this->projectTag;
    }

    public function setProjectTag(?string $projectTag): static
    {
        $this->projectTag = $projectTag;

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
     * @return array{
     *     id: int|null,
     *     name: string|null,
     *     url: string|null,
     *     secret: string|null,
     *     events: array<string>,
     *     projectTag: string|null,
     *     isActive: bool,
     *     createdAt: string|null
     * }
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'url' => $this->url,
            'secret' => $this->secret,
            'events' => $this->events,
            'projectTag' => $this->projectTag,
            'isActive' => $this->isActive,
            'createdAt' => $this->createdAt?->format(\DateTimeInterface::ATOM),
        ];
    }
}
