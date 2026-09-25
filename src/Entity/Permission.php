<?php

namespace App\Entity;

use App\Repository\PermissionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\MaxDepth;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Une entrée du catalogue RBAC — voir .doc/permissions.md. Nom au format
 * "filiale.perimetre.action" (ex: "tyrolium.website.view"), converti en rôle
 * Symfony "PERMS_TYROLIUM_WEBSITE_VIEW" via toRole(). Le préfixe "PERMS_" est
 * vérifié par une deuxième instance de RoleVoter enregistrée dans
 * config/services.yaml (`app.security.voter.permission`) — RoleVoter
 * n'accepte par défaut que "ROLE_" (déjà utilisé par
 * ROLE_USER/ROLE_INTERNE/ROLE_OWNER), d'où cette instance séparée plutôt
 * qu'un Voter custom à écrire. Un préfixe non enregistré (ex: l'ancien
 * "PERM_" sans le S) n'est voté par personne et se voit toujours refusé,
 * testé en réel le 25/09/2026.
 */
#[ORM\Entity(repositoryClass: PermissionRepository::class)]
#[ORM\Table(name: 'permission')]
#[UniqueEntity(fields: ['name'], message: 'Une permission avec ce nom existe déjà.')]
class Permission
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['permission:read'])]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 100, unique: true)]
    #[Groups(['permission:read'])]
    #[Assert\NotBlank(message: 'Le nom de la permission est obligatoire.')]
    #[Assert\Regex(
        pattern: '/^[a-z0-9]+(?:\.[a-z0-9]+)+$/',
        message: "Le nom doit être au format 'filiale.perimetre.action' en minuscules (ex: tyrolium.website.view). Le nombre de segments n'est pas figé (2 minimum), mais 3 est la convention recommandée.",
    )]
    private ?string $name = null;

    #[ORM\Column(type: 'string', length: 255)]
    #[Groups(['permission:read'])]
    #[Assert\NotBlank(message: 'Le libellé est obligatoire.')]
    private ?string $label = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    /**
     * Permissions "parapluie" — voir .doc/permissions.md : accorder CETTE
     * permission à un utilisateur lui donne aussi, implicitement, toutes
     * celles listées ici (récursivement), sans ligne UserPermission dédiée
     * pour chacune. Ex: "tyrolium.website.manage" implique
     * "tyrolium.website.{view,create,update,delete}".
     *
     * @var Collection<int, Permission>
     */
    #[ORM\ManyToMany(targetEntity: self::class)]
    #[ORM\JoinTable(name: 'permission_implication')]
    #[ORM\JoinColumn(name: 'permission_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'implied_permission_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[Groups(['permission:read'])]
    // MaxDepth(1) : affiche les permissions directement impliquées, mais pas
    // les leurs (pas de récursion infinie côté sérialisation — le catalogue
    // pourrait techniquement contenir un cycle, voir collectRoles() qui, lui,
    // s'en protège différemment). Nécessite le contexte ENABLE_MAX_DEPTH,
    // voir chaque appel à normalize() dans les controllers.
    #[MaxDepth(1)]
    private Collection $impliedPermissions;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->impliedPermissions = new ArrayCollection();
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

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function toRole(): string
    {
        return 'PERMS_'.strtoupper(str_replace('.', '_', (string) $this->name));
    }

    /**
     * @return Collection<int, Permission>
     */
    public function getImpliedPermissions(): Collection
    {
        return $this->impliedPermissions;
    }

    public function addImpliedPermission(Permission $permission): static
    {
        if (!$this->impliedPermissions->contains($permission)) {
            $this->impliedPermissions->add($permission);
        }

        return $this;
    }

    public function removeImpliedPermission(Permission $permission): static
    {
        $this->impliedPermissions->removeElement($permission);

        return $this;
    }

    /**
     * Cette permission + toutes celles impliquées, récursivement, converties
     * en rôles Symfony. $visited protège contre un cycle (A implique B qui
     * implique A) — clé = Permission::$name déjà rencontré.
     *
     * @param array<string, true> $visited
     *
     * @return list<string>
     */
    public function collectRoles(array &$visited = []): array
    {
        if (null !== $this->name && isset($visited[$this->name])) {
            return [];
        }
        if (null !== $this->name) {
            $visited[$this->name] = true;
        }

        $roles = [$this->toRole()];
        foreach ($this->impliedPermissions as $implied) {
            $roles = array_merge($roles, $implied->collectRoles($visited));
        }

        return $roles;
    }
}
