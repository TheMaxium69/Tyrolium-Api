<?php

namespace App\Controller\Tyrolium;

use App\Entity\Tyrolium\WebSite;
use App\Repository\Tyrolium\WebSiteRepository;
use App\Repository\UserRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use App\Entity\User;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class TyroliumWebSiteController extends AbstractController
{

    private function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly WebSiteRepository $webSiteRepository,
        private readonly ValidatorInterface $validator,
    ){
    }

    #[Route('/tyrolium/web-site/post-web-site', name: 'tyrolium_web_site_post_web_site', methods: ['POST'])]
    public function postWebSite(Request $request, #[CurrentUser] ?User $currentUser): JsonResponse
    {

        $payload = json_decode($request->getContent(), true) ?? [];

        if (empty($payload)) {
            return apiError('JSON invalide.', 400);
        }

        $website = new WebSite();

        if (isset($payload['domainName'])) {
            $website->setDomainName($payload['domainName']);
        }

        if (isset($payload['label'])) {
            $website->setLabel($payload['label']);
        }

        if (isset($payload['owner'])) {
            $website->setOwner($payload['owner']);
        }

        if (isset($payload['registrar'])) {
            $website->setRegistrar($payload['registrar']);
        }

        if (isset($payload['server'])) {
            $website->setServer($payload['server']);
        }

        if (isset($payload['content'])) {
            $website->setContent($payload['content']);
        }

        if (isset($payload['status'])) {
            $website->setStatus($payload['status']);
        }

        if (isset($payload['isAutoSSLRenew'])) {
            $website->setisAutoSSLRenew((bool)$payload['isAutoSSLRenew']);
        }

        if (isset($payload['isAutoDomainRenew'])) {
            $website->setisAutoSSLRenew((bool)$payload['isAutoDomainRenew']);
        }

        if ($currentUser) {
            $website->setCreatedBy($currentUser);
        }

        $violations = $this->validator->validate($website);

        $websiteViolations = $this->validator->validate($website, [
            new Assert\NotBlank(message: 'Le champ owner est obligatoire.'),
            new Assert\NotBlank(message: 'Le champ domainName est obligatoire.')
        ]);

        foreach ($websiteViolations as $violation) {
            $violations->add($violation);
        }

        if (count($violations) > 0) {
            return apiValidationError($violations, 'données invalides.', 400);
        }

        try {
            $this->entityManager->persist($website);
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            return apiError('Nom de domain deja utiliser', 409);
        }

        $payload = $this->serialize->normalize($website, null, ['groups' => ['web_site:create']]);

        return apiSuccess(
            data: $payload,
            message: 'Site créer avec succes.',
            code: 201
        );
    }



    #[Route('/tyrolium/web-site/put-update-web-site/{id}', name: 'tyrolium_web_site_put_update_web_site', methods: ['PUT'])]
    public function putUpdateWebSite(int $id, Request $request, #[CurrentUser] ?User $currentUser): JsonResponse
    {

        $website = $this->webSiteRepository->find($id);

        if (!$website) {
            return apiError('Site web / domaine non trouvé.', 404);
        }

        $payload = json_decode($request->getContent(), true);

        if (empty($payload)) {
            return apiError('JSON invalide.', 400);
        }

        if (isset($payload['domainName'])) {
            $website->setDomainName($payload['domainName']);
        }

        if (isset($payload['label'])) {
            $website->setlabel($payload['label']);
        }

        if (isset($payload['owner'])) {
            $website->setOwner($payload['owner']);
        }

        if (isset($payload['registrar'])) {
            $website->setRegistrar($payload['registrar']);
        }

        if (isset($payload['server'])) {
            $website->setServer($payload['server']);
        }

        if (isset($payload['content'])) {
            $website->setContent($payload['content']);
        }

        if (isset($payload['status'])) {
            $website->setStatus($payload['status']);
        }

        if (isset($payload['isAutoSSLRenew'])) {
            $website->setisAutoSSLRenew((bool) $payload['isAutoSSLRenew']);
        }

        if (isset($payload['isAutoDomainRenew'])) {
            $website->setIsAutoDomainRenew((bool) $payload['isAutoDomainRenew']);
        }

        $website->setUpdatedAt(new \DateTimeImmutable());
        if ($currentUser) {
            $website->setUpdateBy($currentUser);
        }

        $violations = $this->validator->validate($website);

        if (count($violations) > 0) {
            return apiValidationError($violations, 'Données de mise à jour invalides.');
        }

        try {
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            return apiError('Ce nom de domaine est déjà utilisé.', 409);
        }

        $data = $this->serializer->normalize($website, null, ['groups' => 'website:read']);

        return apiSuccess(
            data: $data,
            message: 'Site web mis à jour avec succès.'
        );
    }



    #[Route('/tyrolium/web-site/delete-web-site/{id}', name: 'tyrolium_web_site_delete_web_site', methods: ['DELETE'])]
    public function deleteWebSite( int $id ): JsonResponse {

        $website = $this->webSiteRepository->find($id);

        if (!$website) {
            return apiError('Site web / domaine non trouvé.', 404);
        }

        $this->entityManager->remove($website);
        $this->entityManager->flush();

        return apiSuccess(
            data: null,
            message: 'Site web supprimé de l\'inventaire.'
        );
    }




    #[Route('/tyrolium/web-site/get-all', name: 'tyrolium_web_site_get_all', methods: ['GET'])]
    public function getAll(WebSiteRepository $webSiteRepository): JsonResponse
    {
        $sites = $webSiteRepository->findAll();

        // Trie en PHP par la date d'expiration SSL la plus proche (valeurs nulles à la fin)
        usort($sites, static function (WebSite $a, WebSite $b) {
            $dateA = $a->getSslExpiresAt();
            $dateB = $b->getSslExpiresAt();

            if (null === $dateA && null === $dateB) {
                return 0;
            }
            if (null === $dateA) {
                return 1;
            }
            if (null === $dateB) {
                return -1;
            }

            return $dateA <=> $dateB;
        });

        $payload = array_map(static fn (WebSite $site) => $site->toArray(), $sites);

        return apiSuccess($payload, 'Liste des sites web triée par date d\'expiration SSL la plus proche.');
    }

    #[Route('/tyrolium/web-site/get-one/{id}', name: 'tyrolium_web_site_get_one', methods: ['GET'])]
    public function getOneById(int $id, WebSiteRepository $webSiteRepository): JsonResponse
    {
        $site = $webSiteRepository->find($id);
        if (!$site) {
            return apiError('Site web introuvable pour cet ID.', 404);
        }
        $payload = $site->toArray();
        return apiSuccess($payload, 'Détails du site web récupérés avec succès.');
    }

    #[Route('/tyrolium/web-site/get-search', name: 'tyrolium_web_site_get_search', methods: ['GET'])]
    public function getSearch(Request $request, WebSiteRepository $webSiteRepository): JsonResponse
    {
        $query = trim((string)($request->query->get('q') ?? $request->query->get('domain_name') ?? ''));
        if ('' === $query) {
            return apiError('Le paramètre de recherche ?q= ou ?domain_name= est requis.', 400);
        }
        $sites = $webSiteRepository->createQueryBuilder('w')
            ->where('LOWER(w.domainName) LIKE LOWER(:query)')
            ->orWhere('LOWER(w.label) LIKE LOWER(:query)')
            ->orWhere('LOWER(w.name) LIKE LOWER(:query)')
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('w.domainName', 'ASC')
            ->getQuery()
            ->getResult();
        $payload = array_map(static fn (WebSite $site) => $site->toArray(), $sites);
        return apiSuccess($payload, 'Résultats de la recherche pour "' . $query . '".');
    }

    #[Route('/tyrolium/web-site/get-all-by-client/{owner}', name: 'tyrolium_web_site_get_all_by_client', methods: ['GET'])]
    public function getAllByClient(string $owner, WebSiteRepository $webSiteRepository): JsonResponse
    {
        $ownerName = trim(urldecode($owner));

        if ('' === $ownerName) {
            return apiError('Le nom du client (owner) est obligatoire.', 400);
        }

        $sites = $webSiteRepository->findBy(['owner' => $ownerName], ['domainName' => 'ASC']);
        $payload = array_map(static fn (WebSite $site) => $site->toArray(), $sites);

        return apiSuccess($payload, 'Liste des sites web du client "' . $ownerName . '" récupérée.');
    }

}

