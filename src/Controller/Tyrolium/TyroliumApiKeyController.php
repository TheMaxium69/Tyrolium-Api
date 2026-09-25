<?php

namespace App\Controller\Tyrolium;

use App\Entity\ApiKey;
use App\Entity\ApiKeyPermission;
use App\Entity\Permission;
use App\Entity\User;
use App\Enum\ApiKeyEnvironment;
use App\Repository\ApiKeyPermissionRepository;
use App\Repository\ApiKeyRepository;
use App\Repository\PermissionRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Gestion des clés API (systèmes tiers : TyroServ, Gamenium, sites clients...)
 * — voir .doc/permissions.md et cahier des charges (TyroliumApiKeyController).
 * Réutilise le catalogue Permission existant, même mécanisme que pour les
 * employés (voir TyroliumPermissionController) — une clé a des permissions
 * granulaires (PERMS_...) mais jamais ROLE_INTERNE/ROLE_OWNER, voir ApiKey.php.
 * Tout ce controller est réservé à ROLE_OWNER : créer/gérer des clés API est
 * un acte d'administration au moins aussi sensible que gérer les employés.
 */
#[IsGranted('ROLE_OWNER')]
class TyroliumApiKeyController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ApiKeyRepository $apiKeyRepository,
        private readonly ApiKeyPermissionRepository $apiKeyPermissionRepository,
        private readonly PermissionRepository $permissionRepository,
    ) {
    }

    #[Route('/tyrolium/api-key/get-all-key', name: 'tyrolium_api_key_get_all_key', methods: ['GET'])]
    public function getAllKey(): JsonResponse
    {
        $keys = array_map(
            fn (ApiKey $apiKey): array => $this->serializeApiKey($apiKey),
            $this->apiKeyRepository->findAll(),
        );

        return apiSuccess(data: $keys);
    }

    #[Route('/tyrolium/api-key/get-one-key/{id}', name: 'tyrolium_api_key_get_one_key', methods: ['GET'])]
    public function getOneKey(int $id): JsonResponse
    {
        $apiKey = $this->apiKeyRepository->find($id);

        if (null === $apiKey) {
            return apiError('Clé API introuvable.', 404);
        }

        return apiSuccess(data: $this->serializeApiKey($apiKey));
    }

    /**
     * La clé brute n'est renvoyée qu'ICI, une seule fois — jamais stockée,
     * jamais réaffichable ensuite (voir ApiKey.php). Au client de la copier
     * immédiatement.
     */
    #[Route('/tyrolium/api-key/post-create-key', name: 'tyrolium_api_key_post_create_key', methods: ['POST'])]
    public function postCreateKey(Request $request, #[CurrentUser] User $createdBy): JsonResponse
    {
        $payload = json_decode($request->getContent(), true) ?? [];

        $label = $payload['label'] ?? null;
        if (!is_string($label) || '' === trim($label)) {
            return apiError('Le label est obligatoire.', 400);
        }

        $environment = ApiKeyEnvironment::tryFrom((string) ($payload['environment'] ?? ''));
        if (null === $environment) {
            return apiError("L'environnement doit être 'live' ou 'test'.", 400);
        }

        $content = $payload['content'] ?? null;
        if (null !== $content && !is_string($content)) {
            return apiError('Le content doit être une chaîne.', 400);
        }

        $expiresAt = null;
        if (isset($payload['expiresAt'])) {
            if (!is_string($payload['expiresAt'])) {
                return apiError('expiresAt doit être une date ISO-8601 ou null.', 400);
            }
            try {
                $expiresAt = new \DateTimeImmutable($payload['expiresAt']);
            } catch (\Exception) {
                return apiError('expiresAt doit être une date ISO-8601 valide.', 400);
            }
        }

        $rawKey = sprintf('tyrokey_%s_%s', $environment->value, bin2hex(random_bytes(32)));

        $apiKey = new ApiKey();
        $apiKey->setLabel($label);
        $apiKey->setContent($content);
        $apiKey->setEnvironment($environment);
        $apiKey->setExpiresAt($expiresAt);
        $apiKey->setCreatedBy($createdBy);
        $apiKey->setKeyHash(hash('sha256', $rawKey));
        $apiKey->setKeyPreview(substr($rawKey, 0, \strlen('tyrokey_'.$environment->value.'_') + 8).'…');

        try {
            $this->entityManager->persist($apiKey);
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            // Collision astronomiquement improbable sur 256 bits, gardée par
            // cohérence avec le reste du controller.
            return apiError('Une clé identique existe déjà, réessaie.', 409);
        }

        $data = $this->serializeApiKey($apiKey);
        $data['key'] = $rawKey;

        return apiSuccess(data: $data, message: 'Clé créée — copie-la maintenant, elle ne sera plus jamais affichée.', code: 201);
    }

    #[Route('/tyrolium/api-key/post-revoke-key/{id}', name: 'tyrolium_api_key_post_revoke_key', methods: ['POST'])]
    public function postRevokeKey(int $id): JsonResponse
    {
        $apiKey = $this->apiKeyRepository->find($id);

        if (null === $apiKey) {
            return apiError('Clé API introuvable.', 404);
        }

        if ($apiKey->isRevoked()) {
            return apiSuccess(message: 'Cette clé était déjà révoquée.');
        }

        $apiKey->revoke();
        $this->entityManager->flush();

        return apiSuccess(message: 'Clé révoquée — refusée immédiatement sur toute requête suivante.');
    }

    #[Route('/tyrolium/api-key/post-grant-permission', name: 'tyrolium_api_key_post_grant_permission', methods: ['POST'])]
    public function postGrantPermission(Request $request, #[CurrentUser] User $grantedBy): JsonResponse
    {
        $payload = json_decode($request->getContent(), true) ?? [];
        $apiKeyId = $payload['apiKeyId'] ?? null;
        $permissionId = $payload['permissionId'] ?? null;

        if (!is_int($apiKeyId) && !(is_string($apiKeyId) && ctype_digit($apiKeyId))) {
            return apiError('apiKeyId manquant ou invalide.', 400);
        }
        if (!is_int($permissionId) && !(is_string($permissionId) && ctype_digit($permissionId))) {
            return apiError('permissionId manquant ou invalide.', 400);
        }

        $apiKey = $this->apiKeyRepository->find((int) $apiKeyId);
        if (null === $apiKey) {
            return apiError('Clé API introuvable.', 404);
        }

        $permission = $this->permissionRepository->find((int) $permissionId);
        if (null === $permission) {
            return apiError('Permission introuvable.', 404);
        }

        $apiKeyPermission = new ApiKeyPermission();
        $apiKeyPermission->setApiKey($apiKey);
        $apiKeyPermission->setPermission($permission);
        $apiKeyPermission->setGrantedBy($grantedBy);

        try {
            $this->entityManager->persist($apiKeyPermission);
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            return apiError('Cette clé a déjà cette permission.', 409);
        }

        return apiSuccess(data: $this->serializeApiKeyPermission($apiKeyPermission), message: 'Permission accordée.', code: 201);
    }

    #[Route('/tyrolium/api-key/delete-key-permission/{id}', name: 'tyrolium_api_key_delete_key_permission', methods: ['DELETE'])]
    public function deleteKeyPermission(int $id): JsonResponse
    {
        $apiKeyPermission = $this->apiKeyPermissionRepository->find($id);

        if (null === $apiKeyPermission) {
            return apiError('Attribution de permission introuvable.', 404);
        }

        $this->entityManager->remove($apiKeyPermission);
        $this->entityManager->flush();

        return apiSuccess(message: 'Permission révoquée.');
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeApiKey(ApiKey $apiKey): array
    {
        return [
            'id' => $apiKey->getId(),
            'label' => $apiKey->getLabel(),
            'content' => $apiKey->getContent(),
            'environment' => $apiKey->getEnvironment()->value,
            'keyPreview' => $apiKey->getKeyPreview(),
            'expiresAt' => $apiKey->getExpiresAt()?->format(DATE_ATOM),
            'isExpired' => $apiKey->isExpired(),
            'revokedAt' => $apiKey->getRevokedAt()?->format(DATE_ATOM),
            'createdBy' => $apiKey->getCreatedBy()?->getUsername(),
            'createdAt' => $apiKey->getCreatedAt()->format(DATE_ATOM),
            'permissions' => array_map(
                fn (ApiKeyPermission $p): array => $this->serializeApiKeyPermission($p),
                $apiKey->getPermissions()->toArray(),
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeApiKeyPermission(ApiKeyPermission $apiKeyPermission): array
    {
        $permission = $apiKeyPermission->getPermission()
            ?? throw new \LogicException('Une ApiKeyPermission persistée doit toujours avoir une Permission (colonne NOT NULL).');

        return [
            'id' => $apiKeyPermission->getId(),
            'permission' => [
                'id' => $permission->getId(),
                'name' => $permission->getName(),
                'label' => $permission->getLabel(),
            ],
            'grantedBy' => $apiKeyPermission->getGrantedBy()?->getUsername(),
            'grantedAt' => $apiKeyPermission->getGrantedAt()->format(DATE_ATOM),
        ];
    }
}
