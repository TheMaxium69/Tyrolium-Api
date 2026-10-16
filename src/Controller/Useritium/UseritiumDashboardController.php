<?php

namespace App\Controller\Useritium;

use App\Entity\Prestation;
use App\Entity\Server;
use App\Entity\User;
use App\Entity\Website;
use App\Enum\PrestationStatus;
use App\Enum\ServerStatus;
use App\Enum\WebsiteStatus;
use App\Helper\Pagination;
use App\Repository\PrestationRepository;
use App\Repository\ServerRepository;
use App\Repository\UserRepository;
use App\Repository\WebsiteRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Dashboard Useritium (public). get-user-card est aussi réutilisé par le HUB pour
 * afficher un compte sous forme de chip : il ne renvoie que le minimum utile à
 * l'affichage — jamais d'email ni de permission.
 *
 * Les routes get-all-my-* / get-one-my-* exposent au client connecté SES propres
 * prestations, serveurs et sites web (lien `user`). Le filtre sur l'utilisateur courant est
 * imposé côté serveur, jamais lu dans la requête ; une ressource d'un autre
 * compte répond 404 (pas 403) pour ne pas révéler son existence. Les champs sont
 * listés explicitement (liste blanche) : rien d'interne (createdBy, notes,
 * dépôt git, description serveur, projet, serveur hébergeur d'un site) ne sort vers le public.
 */
class UseritiumDashboardController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly PrestationRepository $prestationRepository,
        private readonly ServerRepository $serverRepository,
        private readonly WebsiteRepository $websiteRepository,
        private readonly NormalizerInterface $serializer,
    ) {
    }

    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[Route('/useritium/dashboard/get-user-card/{id}', name: 'useritium_dashboard_get_user_card', methods: ['GET'])]
    public function getUserCard(int $id): JsonResponse
    {
        $user = $this->userRepository->find($id);
        if (null === $user) {
            return apiError('Utilisateur introuvable.', 404);
        }

        /** @var array<string, mixed> $card */
        $card = $this->serializer->normalize($user, context: ['groups' => ['user:card']]);

        return apiSuccess(data: $card);
    }

    // ------------------------------------------------------------------
    // Mes prestations
    // ------------------------------------------------------------------

    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[Route('/useritium/dashboard/get-all-my-prestation', name: 'useritium_dashboard_get_all_my_prestation', methods: ['GET'])]
    public function getAllMyPrestation(Request $request): JsonResponse
    {
        $user = $this->getUser();
        \assert($user instanceof User);

        $qb = $this->prestationRepository->createQueryBuilder('p')
            ->andWhere('p.user = :user')->setParameter('user', $user)
            ->orderBy('p.startedAt', 'DESC')->addOrderBy('p.id', 'DESC');

        $rawStatus = $request->query->get('status');
        if (null !== $rawStatus && '' !== $rawStatus) {
            $status = PrestationStatus::tryFrom((string) $rawStatus);
            if (null === $status) {
                return apiError('status invalide (valeurs : '.implode(', ', array_column(PrestationStatus::cases(), 'value')).').', 400);
            }
            $qb->andWhere('p.status = :status')->setParameter('status', $status);
        }

        $result = Pagination::fromQueryBuilder($qb, $request);

        /** @var list<Prestation> $items */
        $items = $result['items'];

        return apiSuccess(
            data: array_map(fn (Prestation $prestation): array => $this->normalizeMyPrestation($prestation), $items),
            meta: ['pagination' => $result['pagination']],
        );
    }

    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[Route('/useritium/dashboard/get-one-my-prestation/{id}', name: 'useritium_dashboard_get_one_my_prestation', methods: ['GET'])]
    public function getOneMyPrestation(int $id): JsonResponse
    {
        $user = $this->getUser();
        \assert($user instanceof User);

        $prestation = $this->prestationRepository->findOneBy(['id' => $id, 'user' => $user]);
        if (null === $prestation) {
            return apiError('Prestation introuvable.', 404);
        }

        return apiSuccess(data: $this->normalizeMyPrestation($prestation));
    }

    // ------------------------------------------------------------------
    // Mes serveurs
    // ------------------------------------------------------------------

    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[Route('/useritium/dashboard/get-all-my-server', name: 'useritium_dashboard_get_all_my_server', methods: ['GET'])]
    public function getAllMyServer(Request $request): JsonResponse
    {
        $user = $this->getUser();
        \assert($user instanceof User);

        $qb = $this->serverRepository->createQueryBuilder('s')
            ->andWhere('s.user = :user')->setParameter('user', $user)
            ->orderBy('s.name', 'ASC');

        $rawStatus = $request->query->get('status');
        if (null !== $rawStatus && '' !== $rawStatus) {
            $status = ServerStatus::tryFrom((string) $rawStatus);
            if (null === $status) {
                return apiError('status invalide (valeurs : '.implode(', ', array_column(ServerStatus::cases(), 'value')).').', 400);
            }
            $qb->andWhere('s.status = :status')->setParameter('status', $status);
        }

        $result = Pagination::fromQueryBuilder($qb, $request);

        /** @var list<Server> $items */
        $items = $result['items'];

        return apiSuccess(
            data: array_map(fn (Server $server): array => $this->normalizeMyServer($server), $items),
            meta: ['pagination' => $result['pagination']],
        );
    }

    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[Route('/useritium/dashboard/get-one-my-server/{id}', name: 'useritium_dashboard_get_one_my_server', methods: ['GET'])]
    public function getOneMyServer(int $id): JsonResponse
    {
        $user = $this->getUser();
        \assert($user instanceof User);

        $server = $this->serverRepository->findOneBy(['id' => $id, 'user' => $user]);
        if (null === $server) {
            return apiError('Serveur introuvable.', 404);
        }

        return apiSuccess(data: $this->normalizeMyServer($server));
    }

    // ------------------------------------------------------------------
    // Mes sites web
    // ------------------------------------------------------------------

    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[Route('/useritium/dashboard/get-all-my-web-site', name: 'useritium_dashboard_get_all_my_web_site', methods: ['GET'])]
    public function getAllMyWebSite(Request $request): JsonResponse
    {
        $user = $this->getUser();
        \assert($user instanceof User);

        $qb = $this->websiteRepository->createQueryBuilder('w')
            ->andWhere('w.user = :user')->setParameter('user', $user)
            ->orderBy('w.domainName', 'ASC');

        $rawStatus = $request->query->get('status');
        if (null !== $rawStatus && '' !== $rawStatus) {
            $status = WebsiteStatus::tryFrom((string) $rawStatus);
            if (null === $status) {
                return apiError('status invalide (valeurs : '.implode(', ', array_column(WebsiteStatus::cases(), 'value')).').', 400);
            }
            $qb->andWhere('w.status = :status')->setParameter('status', $status);
        }

        $result = Pagination::fromQueryBuilder($qb, $request);

        /** @var list<Website> $items */
        $items = $result['items'];

        return apiSuccess(
            data: array_map(fn (Website $website): array => $this->normalizeMyWebsite($website, $user), $items),
            meta: ['pagination' => $result['pagination']],
        );
    }

    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[Route('/useritium/dashboard/get-one-my-web-site/{id}', name: 'useritium_dashboard_get_one_my_web_site', methods: ['GET'])]
    public function getOneMyWebSite(int $id): JsonResponse
    {
        $user = $this->getUser();
        \assert($user instanceof User);

        $website = $this->websiteRepository->findOneBy(['id' => $id, 'user' => $user]);
        if (null === $website) {
            return apiError('Site web introuvable.', 404);
        }

        return apiSuccess(data: $this->normalizeMyWebsite($website, $user));
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeMyPrestation(Prestation $prestation): array
    {
        $offre = $prestation->getOffre();

        return [
            'id' => $prestation->getId(),
            'offre' => null === $offre ? null : [
                'id' => $offre->getId(),
                'tagName' => $offre->getTagName(),
                'displayName' => $offre->getDisplayName(),
                'description' => $offre->getDescription(),
            ],
            'status' => $prestation->getStatus()->value,
            'progress' => $prestation->getProgress(),
            'price' => $prestation->getPrice(),
            'startedAt' => $prestation->getStartedAt()->format(\DATE_ATOM),
            'endedAt' => $prestation->getEndedAt()?->format(\DATE_ATOM),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeMyServer(Server $server): array
    {
        $type = $server->getType();
        $product = $server->getProduct();

        return [
            'id' => $server->getId(),
            'name' => $server->getName(),
            'alias' => $server->getAlias(),
            'hostname' => $server->getHostname(),
            'location' => $server->getLocation(),
            'status' => $server->getStatus()->value,
            'startedAt' => $server->getStartedAt()?->format(\DATE_ATOM),
            'type' => null === $type ? null : [
                'id' => $type->getId(),
                'abbreviation' => $type->getAbbreviation(),
                'name' => $type->getName(),
            ],
            'product' => null === $product ? null : [
                'id' => $product->getId(),
                'tagName' => $product->getTagName(),
                'displayName' => $product->getDisplayName(),
                'cpuCores' => $product->getCpuCores(),
                'ramMb' => $product->getRamMb(),
                'storageGb' => $product->getStorageGb(),
            ],
        ];
    }

    /**
     * Le lien prestation n'est exposé que si elle appartient bien au même compte ;
     * le serveur hébergeur ne l'est jamais (il peut être mutualisé ou interne).
     *
     * @return array<string, mixed>
     */
    private function normalizeMyWebsite(Website $website, User $user): array
    {
        $prestation = $website->getPrestation();
        $ownPrestation = null !== $prestation && $prestation->getUser()?->getId() === $user->getId() ? $prestation : null;

        return [
            'id' => $website->getId(),
            'domainName' => $website->getDomainName(),
            'name' => $website->getName(),
            'description' => $website->getDescription(),
            'status' => $website->getStatus()->value,
            'registrar' => $website->getRegistrar(),
            'domainExpiresAt' => $website->getDomainExpiresAt()?->format(\DATE_ATOM),
            'daysUntilExpiry' => $website->getDaysUntilExpiry(),
            'autoRenew' => $website->isAutoRenew(),
            'sslExpiresAt' => $website->getSslExpiresAt()?->format(\DATE_ATOM),
            'sslDaysUntilExpiry' => $website->getSslDaysUntilExpiry(),
            'sslAutoRenew' => $website->isSslAutoRenew(),
            'sslProvider' => $website->getSslProvider(),
            'prestation' => null === $ownPrestation ? null : [
                'id' => $ownPrestation->getId(),
                'offre' => $ownPrestation->getOffre()?->getDisplayName(),
            ],
        ];
    }
}
