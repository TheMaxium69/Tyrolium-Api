<?php

namespace App\Controller\Tyrolium;

use App\Entity\Tyrolium\ApiKey;
use App\Entity\Tyrolium\Webhook;
use App\Repository\Tyrolium\ApiKeyRepository;
use App\Repository\Tyrolium\WebhookRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class TyroliumApiKeyController extends AbstractController
{
    // ==========================================
    // API KEYS ENDPOINTS
    // ==========================================

    #[Route('/tyrolium/api-key/post-create', name: 'tyrolium_api_key_post_create', methods: ['POST'])]
    public function postCreateKey(
        Request $request,
        EntityManagerInterface $entityManager,
        ApiKeyRepository $apiKeyRepository
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (empty($data)) {
            return apiError('JSON invalide.', 400);
        }

        $name = $data['name'] ?? null;
        $projectTag = $data['projectTag'] ?? $data['project_tag'] ?? null;
        $scopes = $data['scopes'] ?? [];
        $expiresAt = $data['expiresAt'] ?? $data['expires_at'] ?? null;

        if (!$name) {
            return apiError('Le champ name est obligatoire.', 400);
        }

        if (!is_array($scopes)) {
            return apiError('Le champ scopes doit être un tableau de chaînes.', 422);
        }

        // Génération de la clé API : tyrokey_live_ + 32 hex chars
        $rawSecret = bin2hex(random_bytes(16));
        $plainKey = 'tyrokey_live_' . $rawSecret;
        $keyPrefix = substr($plainKey, 0, 20) . '...';
        $keyHash = hash('sha256', $plainKey);

        $apiKey = new ApiKey();
        $apiKey->setName($name);
        $apiKey->setKeyPrefix($keyPrefix);
        $apiKey->setKeyHash($keyHash);
        $apiKey->setProjectTag($projectTag);
        $apiKey->setScopes($scopes);

        if ($expiresAt) {
            try {
                $apiKey->setExpiresAt(new \DateTimeImmutable($expiresAt));
            } catch (\Exception) {
                return apiError('Format de date expiresAt invalide.', 400);
            }
        }

        $entityManager->persist($apiKey);
        $entityManager->flush();

        $resultData = array_merge($apiKey->toArray(), [
            'rawApiKey' => $plainKey, // Explicitement renvoyé UNE SEULE FOIS lors de la création
        ]);

        return apiSuccess($resultData, 'Clé API créée avec succès. Conservez la clé secrète, elle ne sera plus réaffichée !', 201);
    }

    #[Route('/tyrolium/api-key/get-all', name: 'tyrolium_api_key_get_all', methods: ['GET'])]
    public function getAllKeys(Request $request, ApiKeyRepository $apiKeyRepository): JsonResponse
    {
        $projectTag = $request->query->get('project_tag') ?? $request->query->get('projectTag');

        if ($projectTag) {
            $keys = $apiKeyRepository->findBy(['projectTag' => $projectTag]);
        } else {
            $keys = $apiKeyRepository->findAll();
        }

        $data = array_map(static fn (ApiKey $key) => $key->toArray(), $keys);

        return apiSuccess($data, 'Liste des clés API récupérée.');
    }

    #[Route('/tyrolium/api-key/get-one/{id}', name: 'tyrolium_api_key_get_one', methods: ['GET'])]
    public function getOneKey(int $id, ApiKeyRepository $apiKeyRepository): JsonResponse
    {
        $key = $apiKeyRepository->find($id);
        if (!$key) {
            return apiError('Clé API non trouvée.', 404);
        }

        return apiSuccess($key->toArray(), 'Détails de la clé API récupérés.');
    }

    #[Route('/tyrolium/api-key/put-toggle-active/{id}', name: 'tyrolium_api_key_put_toggle_active', methods: ['PUT'])]
    public function putToggleKeyActive(
        int $id,
        Request $request,
        EntityManagerInterface $entityManager,
        ApiKeyRepository $apiKeyRepository
    ): JsonResponse {
        $key = $apiKeyRepository->find($id);
        if (!$key) {
            return apiError('Clé API non trouvée.', 404);
        }

        $data = json_decode($request->getContent(), true);
        if (isset($data['isActive']) || isset($data['is_active'])) {
            $key->setIsActive((bool)($data['isActive'] ?? $data['is_active']));
        } else {
            $key->setIsActive(!$key->isActive());
        }

        $entityManager->flush();

        $statusMsg = $key->isActive() ? 'activée' : 'désactivée';

        return apiSuccess($key->toArray(), "Clé API {$statusMsg} avec succès.");
    }

    #[Route('/tyrolium/api-key/put-update-scopes/{id}', name: 'tyrolium_api_key_put_update_scopes', methods: ['PUT'])]
    public function putUpdateKeyScopes(
        int $id,
        Request $request,
        EntityManagerInterface $entityManager,
        ApiKeyRepository $apiKeyRepository
    ): JsonResponse {
        $key = $apiKeyRepository->find($id);
        if (!$key) {
            return apiError('Clé API non trouvée.', 404);
        }

        $data = json_decode($request->getContent(), true);
        $scopes = $data['scopes'] ?? null;

        if (!is_array($scopes)) {
            return apiError('Le champ scopes doit être un tableau.', 400);
        }

        $key->setScopes($scopes);
        $entityManager->flush();

        return apiSuccess($key->toArray(), 'Scopes de la clé API mis à jour.');
    }

    #[Route('/tyrolium/api-key/delete/{id}', name: 'tyrolium_api_key_delete', methods: ['DELETE'])]
    public function deleteKey(
        int $id,
        EntityManagerInterface $entityManager,
        ApiKeyRepository $apiKeyRepository
    ): JsonResponse {
        $key = $apiKeyRepository->find($id);
        if (!$key) {
            return apiError('Clé API non trouvée.', 404);
        }

        $entityManager->remove($key);
        $entityManager->flush();

        return apiSuccess(null, 'Clé API supprimée.');
    }

    // ==========================================
    // WEBHOOKS ENDPOINTS
    // ==========================================

    #[Route('/tyrolium/api-key/post-create-webhook', name: 'tyrolium_api_key_post_create_webhook', methods: ['POST'])]
    public function postCreateWebhook(
        Request $request,
        EntityManagerInterface $entityManager,
        WebhookRepository $webhookRepository
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (empty($data)) {
            return apiError('JSON invalide.', 400);
        }

        $name = $data['name'] ?? null;
        $url = $data['url'] ?? null;
        $events = $data['events'] ?? [];
        $projectTag = $data['projectTag'] ?? $data['project_tag'] ?? null;
        $secret = $data['secret'] ?? bin2hex(random_bytes(16));

        if (!$name || !$url) {
            return apiError('Les champs name et url sont obligatoires.', 400);
        }

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return apiError('L\'URL du Webhook n\'est pas valide.', 422);
        }

        if (!is_array($events)) {
            return apiError('Le champ events doit être un tableau de chaînes d\'événements.', 422);
        }

        $webhook = new Webhook();
        $webhook->setName($name);
        $webhook->setUrl($url);
        $webhook->setEvents($events);
        $webhook->setProjectTag($projectTag);
        $webhook->setSecret($secret);

        $entityManager->persist($webhook);
        $entityManager->flush();

        return apiSuccess($webhook->toArray(), 'Webhook créé avec succès.', 201);
    }

    #[Route('/tyrolium/api-key/get-all-webhooks', name: 'tyrolium_api_key_get_all_webhooks', methods: ['GET'])]
    public function getAllWebhooks(Request $request, WebhookRepository $webhookRepository): JsonResponse
    {
        $projectTag = $request->query->get('project_tag') ?? $request->query->get('projectTag');

        if ($projectTag) {
            $webhooks = $webhookRepository->findBy(['projectTag' => $projectTag]);
        } else {
            $webhooks = $webhookRepository->findAll();
        }

        $data = array_map(static fn (Webhook $wh) => $wh->toArray(), $webhooks);

        return apiSuccess($data, 'Liste des Webhooks récupérée.');
    }

    #[Route('/tyrolium/api-key/get-one-webhook/{id}', name: 'tyrolium_api_key_get_one_webhook', methods: ['GET'])]
    public function getOneWebhook(int $id, WebhookRepository $webhookRepository): JsonResponse
    {
        $webhook = $webhookRepository->find($id);
        if (!$webhook) {
            return apiError('Webhook non trouvé.', 404);
        }

        return apiSuccess($webhook->toArray(), 'Détails du Webhook récupérés.');
    }

    #[Route('/tyrolium/api-key/put-toggle-webhook-active/{id}', name: 'tyrolium_api_key_put_toggle_webhook_active', methods: ['PUT'])]
    public function putToggleWebhookActive(
        int $id,
        Request $request,
        EntityManagerInterface $entityManager,
        WebhookRepository $webhookRepository
    ): JsonResponse {
        $webhook = $webhookRepository->find($id);
        if (!$webhook) {
            return apiError('Webhook non trouvé.', 404);
        }

        $data = json_decode($request->getContent(), true);
        if (isset($data['isActive']) || isset($data['is_active'])) {
            $webhook->setIsActive((bool)($data['isActive'] ?? $data['is_active']));
        } else {
            $webhook->setIsActive(!$webhook->isActive());
        }

        $entityManager->flush();

        $statusMsg = $webhook->isActive() ? 'activé' : 'désactivé';

        return apiSuccess($webhook->toArray(), "Webhook {$statusMsg} avec succès.");
    }

    #[Route('/tyrolium/api-key/delete-webhook/{id}', name: 'tyrolium_api_key_delete_webhook', methods: ['DELETE'])]
    public function deleteWebhook(
        int $id,
        EntityManagerInterface $entityManager,
        WebhookRepository $webhookRepository
    ): JsonResponse {
        $webhook = $webhookRepository->find($id);
        if (!$webhook) {
            return apiError('Webhook non trouvé.', 404);
        }

        $entityManager->remove($webhook);
        $entityManager->flush();

        return apiSuccess(null, 'Webhook supprimé.');
    }
}
