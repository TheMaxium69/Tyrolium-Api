<?php

namespace App\Controller\Tyrolium;

use App\Entity\Permission;
use App\Entity\User;
use App\Entity\UserPermission;
use App\Enum\AccessLevel;
use App\Repository\PermissionRepository;
use App\Repository\UserPermissionRepository;
use App\Repository\UserRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * RBAC des employés Tyrolium — voir .doc/permissions.md pour le design
 * complet. Réutilise le compte Useritium existant (User), pas d'entité
 * Employee séparée (décision de Maxime, 25/09/2026).
 *
 * Deux étages distincts (décision de Maxime, 25/09/2026) :
 *  1. AccessLevel (User::$accessLevel) : user (public/client) / interne
 *     (employé) / owner (Maxime, jamais via API — voir App\Enum\AccessLevel).
 *     Les permissions granulaires ci-dessous ne comptent que si interne+.
 *  2. Permission/UserPermission : droits granulaires, uniquement pour les
 *     comptes déjà passés interne à l'étage 1.
 *
 * Tout ce controller est réservé à ROLE_OWNER, sauf la simple consultation du
 * catalogue (ROLE_INTERNE suffit) — jamais accessible à un compte public.
 */
class TyroliumPermissionController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PermissionRepository $permissionRepository,
        private readonly UserPermissionRepository $userPermissionRepository,
        private readonly UserRepository $userRepository,
        private readonly ValidatorInterface $validator,
    ) {
    }

    #[IsGranted('ROLE_INTERNE')]
    #[Route('/tyrolium/permission/get-all-permission', name: 'tyrolium_permission_get_all_permission', methods: ['GET'])]
    public function getAllPermission(): JsonResponse
    {
        $permissions = array_map(
            fn (Permission $permission): array => $this->serializePermission($permission),
            $this->permissionRepository->findAll(),
        );

        return apiSuccess(data: $permissions);
    }

    #[IsGranted('ROLE_OWNER')]
    #[Route('/tyrolium/permission/get-user-permission/{userId}', name: 'tyrolium_permission_get_user_permission', methods: ['GET'])]
    public function getUserPermission(int $userId): JsonResponse
    {
        $user = $this->userRepository->find($userId);

        if (null === $user) {
            return apiError('Utilisateur introuvable.', 404);
        }

        return apiSuccess(data: $this->serializeUserAccess($user));
    }

    /**
     * "Qui suis-je" — permet à un front (Angular) de rafraîchir ce qu'il sait
     * de ses propres droits sans forcer une reconnexion : contrairement au
     * JWT (rôles figés à l'émission, voir .doc/permissions.md section 3),
     * cette route recalcule `roles` à chaque appel depuis la DB, à jour en
     * temps réel. Accessible à n'importe quel compte connecté, y compris
     * accessLevel=user (consulter ses propres infos n'a rien de sensible).
     */
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[Route('/tyrolium/permission/get-my-permission', name: 'tyrolium_permission_get_my_permission', methods: ['GET'])]
    public function getMyPermission(#[CurrentUser] User $user): JsonResponse
    {
        return apiSuccess(data: $this->serializeUserAccess($user));
    }

    #[IsGranted('ROLE_OWNER')]
    #[Route('/tyrolium/permission/post-create-permission', name: 'tyrolium_permission_post_create_permission', methods: ['POST'])]
    public function postCreatePermission(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true) ?? [];

        $permission = new Permission();
        if (isset($payload['name']) && is_string($payload['name'])) {
            $permission->setName($payload['name']);
        }
        if (isset($payload['label']) && is_string($payload['label'])) {
            $permission->setLabel($payload['label']);
        }

        $violations = $this->validator->validate($permission);
        if (count($violations) > 0) {
            return apiValidationError($violations, 'Permission invalide.');
        }

        try {
            $this->entityManager->persist($permission);
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            return apiError('Une permission avec ce nom existe déjà.', 409);
        }

        return apiSuccess(data: $this->serializePermission($permission), message: 'Permission créée.', code: 201);
    }

    /**
     * Étage 1 : accorde l'accès interne (AccessLevel::INTERNE) — condition
     * nécessaire avant que la moindre permission granulaire ait le moindre
     * effet pour ce compte (voir User::getRoles()).
     */
    #[IsGranted('ROLE_OWNER')]
    #[Route('/tyrolium/permission/post-grant-interne-access', name: 'tyrolium_permission_post_grant_interne_access', methods: ['POST'])]
    public function postGrantInterneAccess(Request $request): JsonResponse
    {
        $user = $this->resolveTargetUser($request);
        if ($user instanceof JsonResponse) {
            return $user;
        }

        if (AccessLevel::OWNER === $user->getAccessLevel()) {
            return apiError("Impossible de modifier le niveau d'accès d'un owner via l'API.", 403);
        }

        $user->setAccessLevel(AccessLevel::INTERNE);
        $this->entityManager->flush();

        return apiSuccess(message: 'Accès interne accordé — aucune permission granulaire tant qu\'aucune n\'est explicitement attribuée.');
    }

    /**
     * Coupe l'accès interne — et donc TOUTES les permissions granulaires
     * d'un coup (voir User::getRoles()), immédiatement (invalidateAllTokens,
     * pas seulement au prochain login). Les lignes UserPermission existantes
     * ne sont volontairement pas supprimées : si ce compte redevient interne
     * plus tard, ses permissions précédentes réapparaissent telles quelles —
     * comportement voulu (voir .doc/permissions.md), à reconsidérer si un
     * jour un besoin de suppression définitive apparaît.
     */
    #[IsGranted('ROLE_OWNER')]
    #[Route('/tyrolium/permission/post-revoke-interne-access', name: 'tyrolium_permission_post_revoke_interne_access', methods: ['POST'])]
    public function postRevokeInterneAccess(Request $request): JsonResponse
    {
        $user = $this->resolveTargetUser($request);
        if ($user instanceof JsonResponse) {
            return $user;
        }

        if (AccessLevel::OWNER === $user->getAccessLevel()) {
            return apiError("Impossible de modifier le niveau d'accès d'un owner via l'API.", 403);
        }

        $user->setAccessLevel(AccessLevel::USER);
        $user->invalidateAllTokens();
        $this->entityManager->flush();

        return apiSuccess(message: 'Accès interne révoqué, toutes les sessions actives de cet utilisateur sont invalidées immédiatement.');
    }

    /**
     * Étage 2 : accorde une permission granulaire précise. Sans effet réel
     * tant que le compte cible n'est pas passé interne à l'étage 1 (voir
     * User::getRoles()) — pas bloqué ici volontairement : accorder par
     * avance à quelqu'un pas encore interne est un usage légitime (ex:
     * préparer l'arrivée d'un employé avant son premier jour).
     */
    #[IsGranted('ROLE_OWNER')]
    #[Route('/tyrolium/permission/post-grant-permission', name: 'tyrolium_permission_post_grant_permission', methods: ['POST'])]
    public function postGrantPermission(Request $request, #[CurrentUser] User $grantedBy): JsonResponse
    {
        $payload = json_decode($request->getContent(), true) ?? [];
        $permissionId = $payload['permissionId'] ?? null;

        $user = $this->resolveTargetUser($request);
        if ($user instanceof JsonResponse) {
            return $user;
        }

        if (!is_int($permissionId) && !(is_string($permissionId) && ctype_digit($permissionId))) {
            return apiError('permissionId manquant ou invalide.', 400);
        }

        $permission = $this->permissionRepository->find((int) $permissionId);
        if (null === $permission) {
            return apiError('Permission introuvable.', 404);
        }

        $userPermission = new UserPermission();
        $userPermission->setUser($user);
        $userPermission->setPermission($permission);
        $userPermission->setGrantedBy($grantedBy);

        try {
            $this->entityManager->persist($userPermission);
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            return apiError('Cet utilisateur a déjà cette permission.', 409);
        }

        return apiSuccess(data: $this->serializeUserPermission($userPermission), message: 'Permission accordée.', code: 201);
    }

    /**
     * Permission "parapluie" : accorder $permissionId implique désormais
     * aussi $impliedPermissionId (et tout ce qu'elle implique elle-même,
     * récursivement — voir Permission::collectRoles()). N'accorde rien à
     * personne tout de suite, ne fait que déclarer la relation dans le
     * catalogue ; l'effet se voit au prochain grant/accès de quelqu'un qui a
     * $permissionId.
     */
    #[IsGranted('ROLE_OWNER')]
    #[Route('/tyrolium/permission/post-link-implied-permission', name: 'tyrolium_permission_post_link_implied_permission', methods: ['POST'])]
    public function postLinkImpliedPermission(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true) ?? [];
        $permissionId = $payload['permissionId'] ?? null;
        $impliedPermissionId = $payload['impliedPermissionId'] ?? null;

        if (!is_int($permissionId) && !(is_string($permissionId) && ctype_digit($permissionId))) {
            return apiError('permissionId manquant ou invalide.', 400);
        }
        if (!is_int($impliedPermissionId) && !(is_string($impliedPermissionId) && ctype_digit($impliedPermissionId))) {
            return apiError('impliedPermissionId manquant ou invalide.', 400);
        }
        if ((int) $permissionId === (int) $impliedPermissionId) {
            return apiError("Une permission ne peut pas s'impliquer elle-même.", 400);
        }

        $permission = $this->permissionRepository->find((int) $permissionId);
        if (null === $permission) {
            return apiError('Permission introuvable.', 404);
        }

        $implied = $this->permissionRepository->find((int) $impliedPermissionId);
        if (null === $implied) {
            return apiError('Permission impliquée introuvable.', 404);
        }

        $permission->addImpliedPermission($implied);
        $this->entityManager->flush();

        return apiSuccess(data: $this->serializePermission($permission), message: 'Permission liée.');
    }

    #[IsGranted('ROLE_OWNER')]
    #[Route('/tyrolium/permission/delete-implied-permission/{permissionId}/{impliedPermissionId}', name: 'tyrolium_permission_delete_implied_permission', methods: ['DELETE'])]
    public function deleteImpliedPermission(int $permissionId, int $impliedPermissionId): JsonResponse
    {
        $permission = $this->permissionRepository->find($permissionId);
        if (null === $permission) {
            return apiError('Permission introuvable.', 404);
        }

        $implied = $this->permissionRepository->find($impliedPermissionId);
        if (null === $implied) {
            return apiError('Permission impliquée introuvable.', 404);
        }

        $permission->removeImpliedPermission($implied);
        $this->entityManager->flush();

        return apiSuccess(data: $this->serializePermission($permission), message: 'Lien retiré.');
    }

    #[IsGranted('ROLE_OWNER')]
    #[Route('/tyrolium/permission/delete-user-permission/{id}', name: 'tyrolium_permission_delete_user_permission', methods: ['DELETE'])]
    public function deleteUserPermission(int $id): JsonResponse
    {
        $userPermission = $this->userPermissionRepository->find($id);

        if (null === $userPermission) {
            return apiError('Attribution de permission introuvable.', 404);
        }

        $affectedUser = $userPermission->getUser();
        $this->entityManager->remove($userPermission);
        // Immédiat, pas juste au prochain login — même raison que
        // postRevokeInterneAccess ci-dessus.
        $affectedUser?->invalidateAllTokens();
        $this->entityManager->flush();

        return apiSuccess(message: 'Permission révoquée, sessions actives de cet utilisateur invalidées immédiatement.');
    }

    /**
     * @return User|JsonResponse User résolu depuis payload['userId'], ou une
     *                           réponse d'erreur JSON déjà prête à renvoyer
     */
    private function resolveTargetUser(Request $request): User|JsonResponse
    {
        $payload = json_decode($request->getContent(), true) ?? [];
        $userId = $payload['userId'] ?? null;

        if (!is_int($userId) && !(is_string($userId) && ctype_digit($userId))) {
            return apiError('userId manquant ou invalide.', 400);
        }

        $user = $this->userRepository->find((int) $userId);
        if (null === $user) {
            return apiError('Utilisateur introuvable.', 404);
        }

        return $user;
    }

    /**
     * accessLevel + attributions brutes (user_permission) + rôles Symfony
     * réellement effectifs à cet instant (getRoles(), permissions parapluie
     * déjà dépliées) — c'est ce dernier champ qu'un front doit utiliser pour
     * ses guards/affichage, pas décoder un JWT potentiellement périmé.
     *
     * @return array<string, mixed>
     */
    private function serializeUserAccess(User $user): array
    {
        $granted = array_map(
            fn (UserPermission $userPermission): array => $this->serializeUserPermission($userPermission),
            $user->getPermissions()->toArray(),
        );

        return [
            'accessLevel' => $user->getAccessLevel()->value,
            'permissions' => $granted,
            'roles' => $user->getRoles(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializePermission(Permission $permission): array
    {
        return [
            'id' => $permission->getId(),
            'name' => $permission->getName(),
            'label' => $permission->getLabel(),
            // Un seul niveau (pas récursif) — évite tout risque de boucle
            // dans la sérialisation JSON même si le catalogue a un cycle.
            'impliedPermissions' => array_map(
                static fn (Permission $p): array => ['id' => $p->getId(), 'name' => $p->getName()],
                $permission->getImpliedPermissions()->toArray(),
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeUserPermission(UserPermission $userPermission): array
    {
        $permission = $userPermission->getPermission()
            ?? throw new \LogicException('Une UserPermission persistée doit toujours avoir une Permission (colonne NOT NULL).');

        return [
            'id' => $userPermission->getId(),
            'permission' => $this->serializePermission($permission),
            'grantedBy' => $userPermission->getGrantedBy()?->getUsername(),
            'grantedAt' => $userPermission->getGrantedAt()->format(DATE_ATOM),
        ];
    }
}
