<?php

namespace App\Entity;

use App\Repository\ApiKeyPermissionRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Attribution d'une Permission à une ApiKey — même rôle que UserPermission,
 * mais pour les clés API. Voir .doc/permissions.md.
 */
#[ORM\Entity(repositoryClass: ApiKeyPermissionRepository::class)]
#[ORM\Table(name: 'api_key_permission')]
#[ORM\UniqueConstraint(name: 'uniq_api_key_permission', columns: ['api_key_id', 'permission_id'])]
class ApiKeyPermission
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: ApiKey::class, inversedBy: 'permissions')]
    #[ORM\JoinColumn(name: 'api_key_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?ApiKey $apiKey = null;

    #[ORM\ManyToOne(targetEntity: Permission::class)]
    #[ORM\JoinColumn(name: 'permission_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Permission $permission = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'granted_by_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?User $grantedBy = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $grantedAt;

    public function __construct()
    {
        $this->grantedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getApiKey(): ?ApiKey
    {
        return $this->apiKey;
    }

    public function setApiKey(ApiKey $apiKey): static
    {
        $this->apiKey = $apiKey;

        return $this;
    }

    public function getPermission(): ?Permission
    {
        return $this->permission;
    }

    public function setPermission(Permission $permission): static
    {
        $this->permission = $permission;

        return $this;
    }

    public function getGrantedBy(): ?User
    {
        return $this->grantedBy;
    }

    public function setGrantedBy(?User $grantedBy): static
    {
        $this->grantedBy = $grantedBy;

        return $this;
    }

    public function getGrantedAt(): \DateTimeImmutable
    {
        return $this->grantedAt;
    }
}
