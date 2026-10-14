<?php

namespace App\Controller\SolidServ;

use App\Entity\Server;
use App\Entity\ServerType;
use App\Entity\User;
use App\Enum\ServerStatus;
use App\Helper\Pagination;
use App\Repository\ServerRepository;
use App\Repository\ServerTypeRepository;
use App\Repository\SolidServProductRepository;
use App\Repository\UserRepository;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
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
 * Parc de serveurs SolidServ/Tyrolium : types de serveurs (dénominations strictes)
 * et serveurs — voir ServerType.php et Server.php. Le paiement n'est pas géré ici.
 *
 * Permissions : solidserv.server.* et solidserv.servertype.*
 * ({view,create,update,delete} + manage, le parapluie).
 */
class SolidServDashboardController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ServerRepository $serverRepository,
        private readonly ServerTypeRepository $serverTypeRepository,
        private readonly SolidServProductRepository $productRepository,
        private readonly UserRepository $userRepository,
        private readonly ValidatorInterface $validator,
        private readonly NormalizerInterface $serializer,
    ) {
    }

    // ------------------------------------------------------------------
    // Types de serveurs
    // ------------------------------------------------------------------

    #[IsGranted('PERMS_SOLIDSERV_SERVERTYPE_VIEW')]
    #[Route('/solidserv/dashboard/get-all-server-type', name: 'solidserv_dashboard_get_all_server_type', methods: ['GET'])]
    public function getAllServerType(Request $request): JsonResponse
    {
        $result = Pagination::fromQueryBuilder(
            $this->serverTypeRepository->createQueryBuilder('t')->orderBy('t.id', 'ASC'),
            $request,
        );

        return apiSuccess(
            data: array_map(fn (ServerType $type): array => $this->normalizeServerType($type), $result['items']),
            meta: ['pagination' => $result['pagination']],
        );
    }

    #[IsGranted('PERMS_SOLIDSERV_SERVERTYPE_VIEW')]
    #[Route('/solidserv/dashboard/get-one-server-type/{id}', name: 'solidserv_dashboard_get_one_server_type', methods: ['GET'])]
    public function getOneServerType(int $id): JsonResponse
    {
        $type = $this->serverTypeRepository->find($id);
        if (null === $type) {
            return apiError('Type de serveur introuvable.', 404);
        }

        return apiSuccess(data: $this->normalizeServerType($type));
    }

    #[IsGranted('PERMS_SOLIDSERV_SERVERTYPE_CREATE')]
    #[Route('/solidserv/dashboard/post-create-server-type', name: 'solidserv_dashboard_post_create_server_type', methods: ['POST'])]
    public function postCreateServerType(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true) ?? [];

        $type = new ServerType();
        if ($error = $this->applyServerTypePayload($type, $payload)) {
            return $error;
        }
        if (null === $type->getNamePrefix() && null !== $type->getAbbreviation()) {
            $type->setNamePrefix($type->getAbbreviation());
        }

        return $this->saveServerType($type, 'Type de serveur créé.', 201);
    }

    #[IsGranted('PERMS_SOLIDSERV_SERVERTYPE_UPDATE')]
    #[Route('/solidserv/dashboard/put-update-server-type/{id}', name: 'solidserv_dashboard_put_update_server_type', methods: ['PUT'])]
    public function putUpdateServerType(int $id, Request $request): JsonResponse
    {
        $type = $this->serverTypeRepository->find($id);
        if (null === $type) {
            return apiError('Type de serveur introuvable.', 404);
        }

        $previousPrefix = $type->getNamePrefix();
        $payload = json_decode($request->getContent(), true) ?? [];

        if ($error = $this->applyServerTypePayload($type, $payload)) {
            return $error;
        }

        if ($type->getNamePrefix() !== $previousPrefix && $this->serverRepository->count(['type' => $type]) > 0) {
            return apiError('Le préfixe de nommage ne peut pas changer tant que des serveurs utilisent ce type (leurs noms ne correspondraient plus).', 409);
        }

        return $this->saveServerType($type, 'Type de serveur mis à jour.', 200);
    }

    #[IsGranted('PERMS_SOLIDSERV_SERVERTYPE_DELETE')]
    #[Route('/solidserv/dashboard/delete-server-type/{id}', name: 'solidserv_dashboard_delete_server_type', methods: ['DELETE'])]
    public function deleteServerType(int $id): JsonResponse
    {
        $type = $this->serverTypeRepository->find($id);
        if (null === $type) {
            return apiError('Type de serveur introuvable.', 404);
        }

        $this->entityManager->remove($type);

        try {
            $this->entityManager->flush();
        } catch (ForeignKeyConstraintViolationException) {
            return apiError('Impossible de supprimer ce type : des serveurs l\'utilisent encore.', 409);
        }

        return apiSuccess(message: 'Type de serveur supprimé.');
    }

    // ------------------------------------------------------------------
    // Serveurs
    // ------------------------------------------------------------------

    #[IsGranted('PERMS_SOLIDSERV_SERVER_VIEW')]
    #[Route('/solidserv/dashboard/get-stats-server', name: 'solidserv_dashboard_get_stats_server', methods: ['GET'])]
    public function getStatsServer(): JsonResponse
    {
        return apiSuccess(data: $this->serverRepository->computeOverview());
    }

    #[IsGranted('PERMS_SOLIDSERV_SERVER_VIEW')]
    #[Route('/solidserv/dashboard/get-all-server', name: 'solidserv_dashboard_get_all_server', methods: ['GET'])]
    public function getAllServer(Request $request): JsonResponse
    {
        $filters = [];

        foreach (['typeId', 'productId', 'userId'] as $key) {
            if (null !== $request->query->get($key)) {
                $value = filter_var($request->query->get($key), FILTER_VALIDATE_INT);
                if (false === $value) {
                    return apiError($key.' doit être un entier.', 400);
                }
                $filters[$key] = $value;
            }
        }
        if (null !== $request->query->get('status')) {
            $status = ServerStatus::tryFrom((string) $request->query->get('status'));
            if (null === $status) {
                return apiError('status invalide.', 400);
            }
            $filters['status'] = $status;
        }
        if (null !== $request->query->get('hasClient')) {
            $hasClient = filter_var($request->query->get('hasClient'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if (null === $hasClient) {
                return apiError('hasClient doit être true ou false.', 400);
            }
            $filters['hasClient'] = $hasClient;
        }
        $project = $request->query->get('project');
        if (is_string($project) && '' !== trim($project)) {
            $filters['project'] = trim($project);
        }
        $search = $request->query->get('search');
        if (is_string($search) && '' !== trim($search)) {
            $filters['search'] = mb_substr(trim($search), 0, 100);
        }

        $result = Pagination::fromQueryBuilder($this->serverRepository->filteredQueryBuilder($filters), $request);

        return apiSuccess(
            data: array_map(fn (Server $server): array => $this->normalizeServer($server), $result['items']),
            meta: ['pagination' => $result['pagination']],
        );
    }

    #[IsGranted('PERMS_SOLIDSERV_SERVER_VIEW')]
    #[Route('/solidserv/dashboard/get-one-server/{id}', name: 'solidserv_dashboard_get_one_server', methods: ['GET'])]
    public function getOneServer(int $id): JsonResponse
    {
        $server = $this->serverRepository->find($id);
        if (null === $server) {
            return apiError('Serveur introuvable.', 404);
        }

        return apiSuccess(data: $this->normalizeServer($server));
    }

    #[IsGranted('PERMS_SOLIDSERV_SERVER_CREATE')]
    #[Route('/solidserv/dashboard/post-create-server', name: 'solidserv_dashboard_post_create_server', methods: ['POST'])]
    public function postCreateServer(Request $request, #[CurrentUser] User $createdBy): JsonResponse
    {
        $payload = json_decode($request->getContent(), true) ?? [];

        if (!isset($payload['typeId'])) {
            return apiError('typeId est obligatoire.', 400);
        }

        $server = new Server();
        $server->setCreatedBy($createdBy);

        if ($error = $this->applyServerPayload($server, $payload)) {
            return $error;
        }

        return $this->saveServer($server, 'Serveur créé.', 201);
    }

    #[IsGranted('PERMS_SOLIDSERV_SERVER_UPDATE')]
    #[Route('/solidserv/dashboard/put-update-server/{id}', name: 'solidserv_dashboard_put_update_server', methods: ['PUT'])]
    public function putUpdateServer(int $id, Request $request): JsonResponse
    {
        $server = $this->serverRepository->find($id);
        if (null === $server) {
            return apiError('Serveur introuvable.', 404);
        }

        $payload = json_decode($request->getContent(), true) ?? [];

        if ($error = $this->applyServerPayload($server, $payload)) {
            return $error;
        }

        return $this->saveServer($server, 'Serveur mis à jour.', 200);
    }

    #[IsGranted('PERMS_SOLIDSERV_SERVER_DELETE')]
    #[Route('/solidserv/dashboard/delete-server/{id}', name: 'solidserv_dashboard_delete_server', methods: ['DELETE'])]
    public function deleteServer(int $id): JsonResponse
    {
        $server = $this->serverRepository->find($id);
        if (null === $server) {
            return apiError('Serveur introuvable.', 404);
        }

        $this->entityManager->remove($server);
        $this->entityManager->flush();

        return apiSuccess(message: 'Serveur supprimé.');
    }

    // ------------------------------------------------------------------

    /**
     * @param array<string, mixed> $payload
     */
    private function applyServerTypePayload(ServerType $type, array $payload): ?JsonResponse
    {
        foreach (['abbreviation' => 'setAbbreviation', 'namePrefix' => 'setNamePrefix', 'name' => 'setName'] as $key => $setter) {
            if (array_key_exists($key, $payload)) {
                if (!is_string($payload[$key])) {
                    return apiError($key.' doit être une chaîne.', 400);
                }
                $type->$setter($payload[$key]);
            }
        }
        if (array_key_exists('description', $payload)) {
            if (null !== $payload['description'] && !is_string($payload['description'])) {
                return apiError('description doit être une chaîne ou null.', 400);
            }
            $type->setDescription($payload['description']);
        }

        return null;
    }

    private function saveServerType(ServerType $type, string $message, int $code): JsonResponse
    {
        $violations = $this->validator->validate($type);
        if (count($violations) > 0) {
            return apiValidationError($violations, 'Type de serveur invalide.');
        }

        try {
            $this->entityManager->persist($type);
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            return apiError('Un type avec cette abréviation ou ce préfixe existe déjà.', 409);
        }

        return apiSuccess(data: $this->normalizeServerType($type), message: $message, code: $code);
    }

    /**
     * Applique uniquement les clés présentes dans le payload (création et modification partagent ce code).
     *
     * @param array<string, mixed> $payload
     */
    private function applyServerPayload(Server $server, array $payload): ?JsonResponse
    {
        if (array_key_exists('name', $payload)) {
            if (!is_string($payload['name'])) {
                return apiError('name doit être une chaîne.', 400);
            }
            $server->setName($payload['name']);
        }

        foreach (['alias' => 'setAlias', 'description' => 'setDescription', 'location' => 'setLocation', 'project' => 'setProject', 'clientName' => 'setClientName', 'hostname' => 'setHostname'] as $key => $setter) {
            if (array_key_exists($key, $payload)) {
                if (null !== $payload[$key] && !is_string($payload[$key])) {
                    return apiError($key.' doit être une chaîne ou null.', 400);
                }
                $value = null === $payload[$key] ? null : trim($payload[$key]);
                $server->$setter('' === $value ? null : $value);
            }
        }

        if (isset($payload['status'])) {
            $status = ServerStatus::tryFrom((string) $payload['status']);
            if (null === $status) {
                return apiError('status invalide.', 400);
            }
            $server->setStatus($status);
        }

        if (array_key_exists('startedAt', $payload)) {
            if (null === $payload['startedAt']) {
                $server->setStartedAt(null);
            } else {
                if (!is_string($payload['startedAt'])) {
                    return apiError('startedAt doit être une date (AAAA-MM-JJ) ou null.', 400);
                }
                try {
                    $server->setStartedAt((new \DateTimeImmutable($payload['startedAt']))->setTime(0, 0));
                } catch (\Exception) {
                    return apiError('startedAt doit être une date valide (AAAA-MM-JJ).', 400);
                }
            }
        }

        if (isset($payload['typeId'])) {
            if (!is_int($payload['typeId'])) {
                return apiError('typeId doit être un entier.', 400);
            }
            $type = $this->serverTypeRepository->find($payload['typeId']);
            if (null === $type) {
                return apiError('Type de serveur introuvable.', 404);
            }
            $server->setType($type);
        }

        if (array_key_exists('productId', $payload)) {
            if (null === $payload['productId']) {
                $server->setProduct(null);
            } else {
                if (!is_int($payload['productId'])) {
                    return apiError('productId doit être un entier ou null.', 400);
                }
                $product = $this->productRepository->find($payload['productId']);
                if (null === $product) {
                    return apiError('Produit introuvable.', 404);
                }
                $server->setProduct($product);
            }
        }

        if (array_key_exists('userId', $payload)) {
            if (null === $payload['userId']) {
                $server->setUser(null);
            } else {
                if (!is_int($payload['userId'])) {
                    return apiError('userId doit être un entier ou null.', 400);
                }
                $user = $this->userRepository->find($payload['userId']);
                if (null === $user) {
                    return apiError('Utilisateur introuvable.', 404);
                }
                $server->setUser($user);
            }
        }

        return null;
    }

    private function saveServer(Server $server, string $message, int $code): JsonResponse
    {
        $type = $server->getType();
        if (null !== $type && null !== $server->getName() && !preg_match('/^'.preg_quote((string) $type->getNamePrefix(), '/').'[0-9]+$/', $server->getName())) {
            return apiError(sprintf("Le nom d'un serveur de type %s doit être « %s » suivi d'un numéro (ex: %s201).", $type->getName(), $type->getNamePrefix(), $type->getNamePrefix()), 400);
        }

        $violations = $this->validator->validate($server);
        if (count($violations) > 0) {
            return apiValidationError($violations, 'Serveur invalide.');
        }

        try {
            $this->entityManager->persist($server);
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            return apiError('Un serveur avec ce nom existe déjà.', 409);
        }

        return apiSuccess(data: $this->normalizeServer($server), message: $message, code: $code);
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeServerType(ServerType $type): array
    {
        /** @var array<string, mixed> $data */
        $data = $this->serializer->normalize($type, context: ['groups' => ['server_type:read']]);

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeServer(Server $server): array
    {
        /** @var array<string, mixed> $data */
        $data = $this->serializer->normalize($server, context: [
            'groups' => ['server:read', 'server_type:identifier', 'solidserv_product:identifier', 'user:identifier'],
            AbstractObjectNormalizer::ENABLE_MAX_DEPTH => true,
        ]);

        return $data;
    }
}
