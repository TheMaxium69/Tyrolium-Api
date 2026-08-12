<?php

namespace App\Controller\Tyrolium;

use App\Entity\Tyrolium\WebSite;
use App\Repository\Tyrolium\WebSiteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class TyroliumWebSiteController extends AbstractController
{
    #[Route('/tyrolium/web-site/post-create', name: 'tyrolium_web_site_post_create', methods: ['POST'])]
    public function postCreate(
        Request $request,
        EntityManagerInterface $entityManager,
        WebSiteRepository $webSiteRepository
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (empty($data)) {
            return apiError('JSON invalide.', 400);
        }

        $domainName = $data['domainName'] ?? $data['domain_name'] ?? null;
        $name = $data['name'] ?? null;
        $ownerType = $data['ownerType'] ?? $data['owner_type'] ?? null;
        $ownerName = $data['ownerName'] ?? $data['owner_name'] ?? null;
        $serverInstance = $data['serverInstance'] ?? $data['server_instance'] ?? null;
        $renewalAt = $data['renewalAt'] ?? $data['renewal_at'] ?? null;
        $isAutoRenew = $data['isAutoRenew'] ?? $data['is_auto_renew'] ?? true;
        $status = $data['status'] ?? 'active';
        $notes = $data['notes'] ?? null;

        if (!$domainName || !$name || !$ownerType || !$ownerName) {
            return apiError('Les champs domainName, name, ownerType et ownerName sont obligatoires.', 400);
        }

        if (!in_array($ownerType, ['filiale', 'client_prestation'], true)) {
            return apiError('ownerType doit être "filiale" ou "client_prestation".', 422);
        }

        $existingSite = $webSiteRepository->findOneBy(['domainName' => $domainName]);
        if ($existingSite) {
            return apiError('Le nom de domaine "'.$domainName.'" existe déjà dans l\'inventaire.', 409);
        }

        $site = new WebSite();
        $site->setDomainName($domainName);
        $site->setName($name);
        $site->setOwnerType($ownerType);
        $site->setOwnerName($ownerName);
        $site->setServerInstance($serverInstance);
        $site->setIsAutoRenew((bool)$isAutoRenew);
        $site->setStatus($status);
        $site->setNotes($notes);

        if ($renewalAt) {
            try {
                $site->setRenewalAt(new \DateTimeImmutable($renewalAt));
            } catch (\Exception) {
                return apiError('Format de date renewalAt invalide (attendu ISO 8601).', 400);
            }
        }

        $entityManager->persist($site);
        $entityManager->flush();

        return apiSuccess($site->toArray(), 'Site web / domaine enregistré avec succès dans l\'inventaire.', 201);
    }

    #[Route('/tyrolium/web-site/get-all', name: 'tyrolium_web_site_get_all', methods: ['GET'])]
    public function getAll(Request $request, WebSiteRepository $webSiteRepository): JsonResponse
    {
        $ownerType = $request->query->get('owner_type') ?? $request->query->get('ownerType');

        if ($ownerType) {
            $sites = $webSiteRepository->findByOwnerType($ownerType);
        } else {
            $sites = $webSiteRepository->findAll();
        }

        $data = array_map(static fn (WebSite $site) => $site->toArray(), $sites);

        return apiSuccess($data, 'Liste de l\'inventaire des sites web récupérée.');
    }

    #[Route('/tyrolium/web-site/get-one/{id}', name: 'tyrolium_web_site_get_one', methods: ['GET'])]
    public function getOne(int $id, WebSiteRepository $webSiteRepository): JsonResponse
    {
        $site = $webSiteRepository->find($id);
        if (!$site) {
            return apiError('Site web / domaine non trouvé.', 404);
        }

        return apiSuccess($site->toArray(), 'Détails du site web récupérés.');
    }

    #[Route('/tyrolium/web-site/get-renewals', name: 'tyrolium_web_site_get_renewals', methods: ['GET'])]
    public function getRenewals(Request $request, WebSiteRepository $webSiteRepository): JsonResponse
    {
        $days = (int)($request->query->get('days') ?? 30);
        if ($days <= 0) {
            $days = 30;
        }

        $sites = $webSiteRepository->findUpcomingRenewals($days);
        $data = array_map(static fn (WebSite $site) => $site->toArray(), $sites);

        return apiSuccess($data, "Liste des domaines arrivant à échéance dans les {$days} prochains jours.");
    }

    #[Route('/tyrolium/web-site/put-update/{id}', name: 'tyrolium_web_site_put_update', methods: ['PUT'])]
    public function putUpdate(
        int $id,
        Request $request,
        EntityManagerInterface $entityManager,
        WebSiteRepository $webSiteRepository
    ): JsonResponse {
        $site = $webSiteRepository->find($id);
        if (!$site) {
            return apiError('Site web / domaine non trouvé.', 404);
        }

        $data = json_decode($request->getContent(), true);
        if (empty($data)) {
            return apiError('JSON invalide.', 400);
        }

        if (isset($data['name'])) {
            $site->setName($data['name']);
        }
        if (isset($data['ownerType']) || isset($data['owner_type'])) {
            $ownerType = $data['ownerType'] ?? $data['owner_type'];
            if (!in_array($ownerType, ['filiale', 'client_prestation'], true)) {
                return apiError('ownerType doit être "filiale" ou "client_prestation".', 422);
            }
            $site->setOwnerType($ownerType);
        }
        if (isset($data['ownerName']) || isset($data['owner_name'])) {
            $site->setOwnerName($data['ownerName'] ?? $data['owner_name']);
        }
        if (array_key_exists('serverInstance', $data) || array_key_exists('server_instance', $data)) {
            $site->setServerInstance($data['serverInstance'] ?? $data['server_instance'] ?? null);
        }
        if (array_key_exists('renewalAt', $data) || array_key_exists('renewal_at', $data)) {
            $renewalAt = $data['renewalAt'] ?? $data['renewal_at'] ?? null;
            if ($renewalAt) {
                try {
                    $site->setRenewalAt(new \DateTimeImmutable($renewalAt));
                } catch (\Exception) {
                    return apiError('Format de date renewalAt invalide.', 400);
                }
            } else {
                $site->setRenewalAt(null);
            }
        }
        if (isset($data['isAutoRenew']) || isset($data['is_auto_renew'])) {
            $site->setIsAutoRenew((bool)($data['isAutoRenew'] ?? $data['is_auto_renew']));
        }
        if (isset($data['status'])) {
            $site->setStatus($data['status']);
        }
        if (array_key_exists('notes', $data)) {
            $site->setNotes($data['notes']);
        }

        $site->setUpdatedAt(new \DateTimeImmutable());

        $entityManager->flush();

        return apiSuccess($site->toArray(), 'Site web mis à jour avec succès.');
    }

    #[Route('/tyrolium/web-site/delete/{id}', name: 'tyrolium_web_site_delete', methods: ['DELETE'])]
    public function delete(
        int $id,
        EntityManagerInterface $entityManager,
        WebSiteRepository $webSiteRepository
    ): JsonResponse {
        $site = $webSiteRepository->find($id);
        if (!$site) {
            return apiError('Site web / domaine non trouvé.', 404);
        }

        $entityManager->remove($site);
        $entityManager->flush();

        return apiSuccess(null, 'Site web supprimé de l\'inventaire.');
    }
}
