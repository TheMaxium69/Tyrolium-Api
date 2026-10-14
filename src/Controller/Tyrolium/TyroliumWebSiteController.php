<?php

namespace App\Controller\Tyrolium;

use App\Entity\User;
use App\Entity\Website;
use App\Enum\Filiale;
use App\Enum\WebsiteOwnerType;
use App\Enum\WebsiteStatus;
use App\Helper\Pagination;
use App\Repository\PrestationRepository;
use App\Repository\ServerRepository;
use App\Repository\UserRepository;
use App\Repository\WebsiteRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Registre des noms de domaine / sites web du groupe — voir Website.php.
 * Permissions : tyrolium.website.{view,create,update,delete} + tyrolium.website.manage (parapluie).
 */
class TyroliumWebSiteController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly WebsiteRepository $websiteRepository,
        private readonly PrestationRepository $prestationRepository,
        private readonly ServerRepository $serverRepository,
        private readonly UserRepository $userRepository,
        private readonly ValidatorInterface $validator,
        private readonly NormalizerInterface $serializer,
    ) {
    }

    #[IsGranted('PERMS_TYROLIUM_WEBSITE_VIEW')]
    #[Route('/tyrolium/web-site/get-all-web-site', name: 'tyrolium_web_site_get_all_web_site', methods: ['GET'])]
    public function getAllWebSite(Request $request): JsonResponse
    {
        $filters = [];

        if (null !== $request->query->get('ownerType')) {
            $ownerType = WebsiteOwnerType::tryFrom((string) $request->query->get('ownerType'));
            if (null === $ownerType) {
                return apiError("ownerType doit être 'filiale' ou 'client'.", 400);
            }
            $filters['ownerType'] = $ownerType;
        }
        if (null !== $request->query->get('filiale')) {
            $filiale = Filiale::tryFrom((string) $request->query->get('filiale'));
            if (null === $filiale) {
                return apiError('filiale invalide.', 400);
            }
            $filters['filiale'] = $filiale;
        }
        if (null !== $request->query->get('status')) {
            $status = WebsiteStatus::tryFrom((string) $request->query->get('status'));
            if (null === $status) {
                return apiError('status invalide.', 400);
            }
            $filters['status'] = $status;
        }
        foreach (['prestationId', 'userId', 'serverId'] as $key) {
            if (null !== $request->query->get($key)) {
                $value = filter_var($request->query->get($key), FILTER_VALIDATE_INT);
                if (false === $value) {
                    return apiError($key.' doit être un entier.', 400);
                }
                $filters[$key] = $value;
            }
        }
        $search = $request->query->get('search');
        if (is_string($search) && '' !== trim($search)) {
            $filters['search'] = mb_substr(trim($search), 0, 100);
        }

        $result = Pagination::fromQueryBuilder($this->websiteRepository->filteredQueryBuilder($filters), $request);

        return apiSuccess(
            data: array_map(fn (Website $website): array => $this->normalizeWebsite($website), $result['items']),
            meta: ['pagination' => $result['pagination']],
        );
    }

    #[IsGranted('PERMS_TYROLIUM_WEBSITE_VIEW')]
    #[Route('/tyrolium/web-site/get-expiring-web-site', name: 'tyrolium_web_site_get_expiring_web_site', methods: ['GET'])]
    public function getExpiringWebSite(Request $request): JsonResponse
    {
        $days = filter_var($request->query->get('days', 30), FILTER_VALIDATE_INT);
        if (false === $days || $days < 1 || $days > 365) {
            return apiError('days doit être un entier entre 1 et 365.', 400);
        }

        $result = Pagination::fromQueryBuilder($this->websiteRepository->expiringQueryBuilder($days), $request);

        return apiSuccess(
            data: array_map(fn (Website $website): array => $this->normalizeWebsite($website), $result['items']),
            meta: ['pagination' => $result['pagination']],
        );
    }

    #[IsGranted('PERMS_TYROLIUM_WEBSITE_VIEW')]
    #[Route('/tyrolium/web-site/get-expiring-ssl-web-site', name: 'tyrolium_web_site_get_expiring_ssl_web_site', methods: ['GET'])]
    public function getExpiringSslWebSite(Request $request): JsonResponse
    {
        $days = filter_var($request->query->get('days', 30), FILTER_VALIDATE_INT);
        if (false === $days || $days < 1 || $days > 365) {
            return apiError('days doit être un entier entre 1 et 365.', 400);
        }

        $result = Pagination::fromQueryBuilder($this->websiteRepository->expiringSslQueryBuilder($days), $request);

        return apiSuccess(
            data: array_map(fn (Website $website): array => $this->normalizeWebsite($website), $result['items']),
            meta: ['pagination' => $result['pagination']],
        );
    }

    #[IsGranted('PERMS_TYROLIUM_WEBSITE_VIEW')]
    #[Route('/tyrolium/web-site/get-one-web-site/{id}', name: 'tyrolium_web_site_get_one_web_site', methods: ['GET'])]
    public function getOneWebSite(int $id): JsonResponse
    {
        $website = $this->websiteRepository->find($id);
        if (null === $website) {
            return apiError('Site introuvable.', 404);
        }

        return apiSuccess(data: $this->normalizeWebsite($website));
    }

    #[IsGranted('PERMS_TYROLIUM_WEBSITE_CREATE')]
    #[Route('/tyrolium/web-site/post-create-web-site', name: 'tyrolium_web_site_post_create_web_site', methods: ['POST'])]
    public function postCreateWebSite(Request $request, #[CurrentUser] User $createdBy): JsonResponse
    {
        $payload = json_decode($request->getContent(), true) ?? [];

        if (!isset($payload['ownerType'])) {
            return apiError("ownerType est obligatoire ('filiale' ou 'client').", 400);
        }

        $website = new Website();
        $website->setCreatedBy($createdBy);

        if ($error = $this->applyPayload($website, $payload)) {
            return $error;
        }

        return $this->saveWebsite($website, 'Site créé.', 201);
    }

    #[IsGranted('PERMS_TYROLIUM_WEBSITE_UPDATE')]
    #[Route('/tyrolium/web-site/put-update-web-site/{id}', name: 'tyrolium_web_site_put_update_web_site', methods: ['PUT'])]
    public function putUpdateWebSite(int $id, Request $request): JsonResponse
    {
        $website = $this->websiteRepository->find($id);
        if (null === $website) {
            return apiError('Site introuvable.', 404);
        }

        $payload = json_decode($request->getContent(), true) ?? [];

        if ($error = $this->applyPayload($website, $payload)) {
            return $error;
        }

        return $this->saveWebsite($website, 'Site mis à jour.', 200);
    }

    #[IsGranted('PERMS_TYROLIUM_WEBSITE_DELETE')]
    #[Route('/tyrolium/web-site/delete-web-site/{id}', name: 'tyrolium_web_site_delete_web_site', methods: ['DELETE'])]
    public function deleteWebSite(int $id): JsonResponse
    {
        $website = $this->websiteRepository->find($id);
        if (null === $website) {
            return apiError('Site introuvable.', 404);
        }

        $this->entityManager->remove($website);
        $this->entityManager->flush();

        return apiSuccess(message: 'Site supprimé.');
    }

    /**
     * Applique uniquement les clés présentes dans le payload (création et modification partagent ce code).
     *
     * @param array<string, mixed> $payload
     */
    private function applyPayload(Website $website, array $payload): ?JsonResponse
    {
        foreach (['domainName', 'name'] as $key) {
            if (array_key_exists($key, $payload)) {
                if (!is_string($payload[$key])) {
                    return apiError($key.' doit être une chaîne.', 400);
                }
            }
        }
        if (array_key_exists('domainName', $payload)) {
            $website->setDomainName($payload['domainName']);
        }
        if (array_key_exists('name', $payload)) {
            $website->setName($payload['name']);
        }

        foreach (['description', 'clientName', 'registrar', 'sslProvider', 'gitRepository'] as $key) {
            if (array_key_exists($key, $payload) && null !== $payload[$key] && !is_string($payload[$key])) {
                return apiError($key.' doit être une chaîne ou null.', 400);
            }
        }
        if (array_key_exists('description', $payload)) {
            $website->setDescription($payload['description']);
        }
        if (array_key_exists('clientName', $payload)) {
            $website->setClientName($this->emptyToNull($payload['clientName']));
        }
        if (array_key_exists('registrar', $payload)) {
            $website->setRegistrar($this->emptyToNull($payload['registrar']));
        }
        if (array_key_exists('sslProvider', $payload)) {
            $website->setSslProvider($this->emptyToNull($payload['sslProvider']));
        }
        if (array_key_exists('gitRepository', $payload)) {
            $website->setGitRepository($this->emptyToNull($payload['gitRepository']));
        }

        if (isset($payload['ownerType'])) {
            $ownerType = WebsiteOwnerType::tryFrom((string) $payload['ownerType']);
            if (null === $ownerType) {
                return apiError("ownerType doit être 'filiale' ou 'client'.", 400);
            }
            $website->setOwnerType($ownerType);
        }
        if (array_key_exists('filiale', $payload)) {
            if (null === $payload['filiale']) {
                $website->setFiliale(null);
            } else {
                $filiale = Filiale::tryFrom((string) $payload['filiale']);
                if (null === $filiale) {
                    return apiError('filiale invalide.', 400);
                }
                $website->setFiliale($filiale);
            }
        }
        if (isset($payload['status'])) {
            $status = WebsiteStatus::tryFrom((string) $payload['status']);
            if (null === $status) {
                return apiError('status invalide.', 400);
            }
            $website->setStatus($status);
        }

        if (array_key_exists('prestationId', $payload)) {
            if (null === $payload['prestationId']) {
                $website->setPrestation(null);
            } else {
                if (!is_int($payload['prestationId'])) {
                    return apiError('prestationId doit être un entier ou null.', 400);
                }
                $prestation = $this->prestationRepository->find($payload['prestationId']);
                if (null === $prestation) {
                    return apiError('Prestation introuvable.', 404);
                }
                $website->setPrestation($prestation);
            }
        }
        if (array_key_exists('userId', $payload)) {
            if (null === $payload['userId']) {
                $website->setUser(null);
            } else {
                if (!is_int($payload['userId'])) {
                    return apiError('userId doit être un entier ou null.', 400);
                }
                $user = $this->userRepository->find($payload['userId']);
                if (null === $user) {
                    return apiError('Utilisateur introuvable.', 404);
                }
                $website->setUser($user);
            }
        }

        if (array_key_exists('serverId', $payload)) {
            if (null === $payload['serverId']) {
                $website->setServer(null);
            } else {
                if (!is_int($payload['serverId'])) {
                    return apiError('serverId doit être un entier ou null.', 400);
                }
                $server = $this->serverRepository->find($payload['serverId']);
                if (null === $server) {
                    return apiError('Serveur introuvable.', 404);
                }
                $website->setServer($server);
            }
        }

        foreach (['domainExpiresAt' => 'setDomainExpiresAt', 'sslExpiresAt' => 'setSslExpiresAt'] as $key => $setter) {
            if (array_key_exists($key, $payload)) {
                if (null === $payload[$key]) {
                    $website->$setter(null);
                    continue;
                }
                if (!is_string($payload[$key])) {
                    return apiError($key.' doit être une date (AAAA-MM-JJ) ou null.', 400);
                }
                try {
                    $website->$setter((new \DateTimeImmutable($payload[$key]))->setTime(0, 0));
                } catch (\Exception) {
                    return apiError($key.' doit être une date valide (AAAA-MM-JJ).', 400);
                }
            }
        }
        foreach (['autoRenew' => 'setAutoRenew', 'sslAutoRenew' => 'setSslAutoRenew'] as $key => $setter) {
            if (isset($payload[$key])) {
                if (!is_bool($payload[$key])) {
                    return apiError($key.' doit être un booléen.', 400);
                }
                $website->$setter($payload[$key]);
            }
        }

        return null;
    }

    private function saveWebsite(Website $website, string $message, int $code): JsonResponse
    {
        if ($error = $this->checkOwnership($website)) {
            return $error;
        }

        $violations = $this->validator->validate($website);
        if (count($violations) > 0) {
            return apiValidationError($violations, 'Site invalide.');
        }

        try {
            $this->entityManager->persist($website);
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            return apiError('Ce nom de domaine est déjà dans le registre.', 409);
        }

        return apiSuccess(data: $this->normalizeWebsite($website), message: $message, code: $code);
    }

    /**
     * Un site de filiale a une filiale et aucun client ; un site client n'a pas de filiale et au moins un lien client.
     */
    private function checkOwnership(Website $website): ?JsonResponse
    {
        $hasClient = null !== $website->getPrestation() || null !== $website->getUser() || null !== $website->getClientName();

        if (WebsiteOwnerType::FILIALE === $website->getOwnerType()) {
            if (null === $website->getFiliale()) {
                return apiError('filiale est obligatoire pour un site de filiale.', 400);
            }
            if ($hasClient) {
                return apiError('Un site de filiale ne peut pas avoir de client (prestationId, userId, clientName).', 400);
            }

            return null;
        }

        if (null !== $website->getFiliale()) {
            return apiError('Un site client ne peut pas avoir de filiale.', 400);
        }
        if (!$hasClient) {
            return apiError('Un site client doit avoir au moins une prestation, un userId ou un clientName.', 400);
        }

        return null;
    }

    private function emptyToNull(?string $value): ?string
    {
        $value = null === $value ? null : trim($value);

        return '' === $value ? null : $value;
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeWebsite(Website $website): array
    {
        /** @var array<string, mixed> $data */
        $data = $this->serializer->normalize($website, context: [
            'groups' => ['website:read', 'prestation:identifier', 'offre:identifier', 'user:identifier', 'server:identifier', 'server_type:identifier'],
            AbstractObjectNormalizer::ENABLE_MAX_DEPTH => true,
        ]);

        return $data;
    }
}
