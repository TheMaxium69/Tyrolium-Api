<?php

namespace App\Controller\Tyrolium;

use App\Entity\AnalyticsInput;
use App\Entity\AnalyticsProject;
use App\Entity\User;
use App\Helper\Pagination;
use App\Repository\AnalyticsInputRepository;
use App\Repository\AnalyticsProjectRepository;
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
 * Analytics : projets suivis (un tag par site) et visites remontées par leur
 * script de tracking. Seule la route d'enregistrement d'une visite
 * (post-create-input) est publique : elle est appelée depuis le navigateur des
 * visiteurs, le tag du projet sert de clé. Toutes les lectures et la gestion
 * des projets sont protégées par permission (tyrolium.analytics.*).
 */
class TyroliumAnalyticsController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly AnalyticsProjectRepository $projectRepository,
        private readonly AnalyticsInputRepository $inputRepository,
        private readonly ValidatorInterface $validator,
        private readonly NormalizerInterface $serializer,
    ) {
    }

    #[IsGranted('PERMS_TYROLIUM_ANALYTICS_CREATE')]
    #[Route('/tyrolium/analytics/post-create-project', name: 'tyrolium_analytics_post_create_project', methods: ['POST'])]
    public function postCreateProject(Request $request, #[CurrentUser] User $createdBy): JsonResponse
    {
        $payload = json_decode($request->getContent(), true) ?? [];

        $domainNames = $payload['domainNames'] ?? null;
        if (!is_array($domainNames) || array_filter($domainNames, static fn (mixed $d): bool => !is_string($d) || '' === trim($d)) !== []) {
            return apiError('domainNames doit être une liste de domaines non vides.', 400);
        }

        if ($this->findDomainOwner($domainNames) !== null) {
            return apiError('Un de ces noms de domaine est déjà suivi par un autre projet.', 409);
        }

        $description = $payload['description'] ?? null;
        if (null !== $description && !is_string($description)) {
            return apiError('description doit être une chaîne ou null.', 400);
        }

        $project = new AnalyticsProject();
        $project->setTag('TyroTag-'.bin2hex(random_bytes(12)));
        $project->setDomainNames($domainNames);
        $project->setDescription($description);
        $project->setCreatedBy($createdBy);

        $violations = $this->validator->validate($project);
        if (count($violations) > 0) {
            return apiValidationError($violations, 'Projet analytics invalide.');
        }

        $this->entityManager->persist($project);
        $this->entityManager->flush();

        return apiSuccess(data: $this->normalizeProject($project), message: 'Projet analytics créé.', code: 201);
    }

    #[IsGranted('PERMS_TYROLIUM_ANALYTICS_VIEW')]
    #[Route('/tyrolium/analytics/get-all-project', name: 'tyrolium_analytics_get_all_project', methods: ['GET'])]
    public function getAllProject(Request $request): JsonResponse
    {
        $result = Pagination::fromQueryBuilder(
            $this->projectRepository->createQueryBuilder('p')->orderBy('p.id', 'ASC'),
            $request,
        );

        return apiSuccess(
            data: array_map(fn (AnalyticsProject $project): array => $this->normalizeProject($project), $result['items']),
            meta: ['pagination' => $result['pagination']],
        );
    }

    #[IsGranted('PERMS_TYROLIUM_ANALYTICS_VIEW')]
    #[Route('/tyrolium/analytics/get-one-project/{id}', name: 'tyrolium_analytics_get_one_project', methods: ['GET'])]
    public function getOneProject(int $id): JsonResponse
    {
        $project = $this->projectRepository->find($id);
        if (null === $project) {
            return apiError('Projet analytics introuvable.', 404);
        }

        return apiSuccess(data: $this->normalizeProject($project));
    }

    #[IsGranted('PERMS_TYROLIUM_ANALYTICS_UPDATE')]
    #[Route('/tyrolium/analytics/put-update-project-domain/{id}', name: 'tyrolium_analytics_put_update_project_domain', methods: ['PUT'])]
    public function putUpdateProjectDomain(int $id, Request $request): JsonResponse
    {
        $project = $this->projectRepository->find($id);
        if (null === $project) {
            return apiError('Projet analytics introuvable.', 404);
        }

        $payload = json_decode($request->getContent(), true) ?? [];
        $domainName = $payload['domainName'] ?? null;
        if (!is_string($domainName) || '' === trim($domainName)) {
            return apiError('domainName doit être un domaine non vide.', 400);
        }

        $owner = $this->findDomainOwner([$domainName]);
        if (null !== $owner) {
            return apiError($owner->getId() === $project->getId()
                ? 'Ce domaine est déjà suivi par ce projet.'
                : 'Ce domaine est déjà suivi par un autre projet.', 409);
        }

        $project->addDomainName($domainName);
        $this->entityManager->flush();

        return apiSuccess(data: $this->normalizeProject($project), message: 'Domaine ajouté au projet analytics.');
    }

    #[IsGranted('PERMS_TYROLIUM_ANALYTICS_UPDATE')]
    #[Route('/tyrolium/analytics/put-update-project-description/{id}', name: 'tyrolium_analytics_put_update_project_description', methods: ['PUT'])]
    public function putUpdateProjectDescription(int $id, Request $request): JsonResponse
    {
        $project = $this->projectRepository->find($id);
        if (null === $project) {
            return apiError('Projet analytics introuvable.', 404);
        }

        $payload = json_decode($request->getContent(), true) ?? [];
        if (!array_key_exists('description', $payload) || (null !== $payload['description'] && !is_string($payload['description']))) {
            return apiError('description doit être une chaîne ou null.', 400);
        }

        $project->setDescription($payload['description']);
        $this->entityManager->flush();

        return apiSuccess(data: $this->normalizeProject($project), message: 'Description mise à jour.');
    }

    #[IsGranted('PERMS_TYROLIUM_ANALYTICS_DELETE')]
    #[Route('/tyrolium/analytics/delete-project/{id}', name: 'tyrolium_analytics_delete_project', methods: ['DELETE'])]
    public function deleteProject(int $id): JsonResponse
    {
        $project = $this->projectRepository->find($id);
        if (null === $project) {
            return apiError('Projet analytics introuvable.', 404);
        }

        $this->entityManager->remove($project);
        $this->entityManager->flush();

        return apiSuccess(message: 'Projet analytics supprimé.');
    }

    /**
     * Route publique appelée par le script de tracking des sites suivis.
     */
    #[Route('/tyrolium/analytics/post-create-input', name: 'tyrolium_analytics_post_create_input', methods: ['POST'])]
    public function postCreateInput(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true) ?? [];

        $projectTag = $payload['projectTag'] ?? null;
        if (!is_string($projectTag) || '' === $projectTag) {
            return apiError('projectTag est requis.', 400);
        }

        $isLogin = $payload['isLogin'] ?? null;
        if (!is_bool($isLogin)) {
            return apiError('isLogin doit être un booléen.', 400);
        }

        $project = $this->projectRepository->findOneBy(['tag' => $projectTag]);
        if (null === $project) {
            return apiError('Projet analytics introuvable.', 404);
        }

        $input = new AnalyticsInput();
        $input->setProject($project);
        $input->setIp((string) $request->getClientIp());
        $input->setPageName(is_string($payload['pageName'] ?? null) ? $payload['pageName'] : '');
        $input->setUri(is_string($payload['uri'] ?? null) ? $payload['uri'] : '');
        $input->setIsLogin($isLogin);

        $violations = $this->validator->validate($input);
        if (count($violations) > 0) {
            return apiValidationError($violations, 'Visite analytics invalide.');
        }

        $this->entityManager->persist($input);
        $this->entityManager->flush();

        return apiSuccess(data: $this->normalizeInput($input), message: 'Visite enregistrée.', code: 201);
    }

    #[IsGranted('PERMS_TYROLIUM_ANALYTICS_VIEW')]
    #[Route('/tyrolium/analytics/get-stats-global', name: 'tyrolium_analytics_get_stats_global', methods: ['GET'])]
    public function getStatsGlobal(Request $request): JsonResponse
    {
        $period = $this->parsePeriod($request);
        if ($period instanceof JsonResponse) {
            return $period;
        }

        return apiSuccess(data: $this->inputRepository->computeStats(null, ...$period));
    }

    #[IsGranted('PERMS_TYROLIUM_ANALYTICS_VIEW')]
    #[Route('/tyrolium/analytics/get-stats-project/{id}', name: 'tyrolium_analytics_get_stats_project', methods: ['GET'])]
    public function getStatsProject(int $id, Request $request): JsonResponse
    {
        $project = $this->projectRepository->find($id);
        if (null === $project) {
            return apiError('Projet analytics introuvable.', 404);
        }

        $period = $this->parsePeriod($request);
        if ($period instanceof JsonResponse) {
            return $period;
        }

        return apiSuccess(data: $this->inputRepository->computeStats($project, ...$period));
    }

    #[IsGranted('PERMS_TYROLIUM_ANALYTICS_VIEW')]
    #[Route('/tyrolium/analytics/get-all-input', name: 'tyrolium_analytics_get_all_input', methods: ['GET'])]
    public function getAllInput(Request $request): JsonResponse
    {
        $result = Pagination::fromQueryBuilder($this->inputRepository->filteredQueryBuilder([]), $request);

        return apiSuccess(
            data: array_map(fn (AnalyticsInput $input): array => $this->normalizeInput($input), $result['items']),
            meta: ['pagination' => $result['pagination']],
        );
    }

    #[IsGranted('PERMS_TYROLIUM_ANALYTICS_VIEW')]
    #[Route('/tyrolium/analytics/get-search-input', name: 'tyrolium_analytics_get_search_input', methods: ['GET'])]
    public function getSearchInput(Request $request): JsonResponse
    {
        $project = null;
        $projectTag = $request->query->get('projectTag');
        if (null !== $projectTag) {
            $project = $this->projectRepository->findOneBy(['tag' => $projectTag]);
            if (null === $project) {
                return apiError('Projet analytics introuvable.', 404);
            }
        }

        $result = Pagination::fromQueryBuilder($this->inputRepository->filteredQueryBuilder([
            'project' => $project,
            'ip' => $request->query->get('ip'),
            'pageName' => $request->query->get('pageName'),
            'uri' => $request->query->get('uri'),
        ]), $request);

        return apiSuccess(
            data: array_map(fn (AnalyticsInput $input): array => $this->normalizeInput($input), $result['items']),
            meta: ['pagination' => $result['pagination']],
        );
    }

    #[IsGranted('PERMS_TYROLIUM_ANALYTICS_VIEW')]
    #[Route('/tyrolium/analytics/get-input-by-project/{id}', name: 'tyrolium_analytics_get_input_by_project', methods: ['GET'])]
    public function getInputByProject(int $id, Request $request): JsonResponse
    {
        $project = $this->projectRepository->find($id);
        if (null === $project) {
            return apiError('Projet analytics introuvable.', 404);
        }

        $result = Pagination::fromQueryBuilder($this->inputRepository->filteredQueryBuilder(['project' => $project]), $request);

        return apiSuccess(
            data: array_map(fn (AnalyticsInput $input): array => $this->normalizeInput($input), $result['items']),
            meta: ['pagination' => $result['pagination']],
        );
    }

    /**
     * @return array{0: ?\DateTimeImmutable, 1: ?\DateTimeImmutable}|JsonResponse
     */
    private function parsePeriod(Request $request): array|JsonResponse
    {
        $from = $this->parseDate($request->query->get('from'));
        $to = $this->parseDate($request->query->get('to'));
        if (false === $from || false === $to) {
            return apiError('from et to doivent être au format AAAA-MM-JJ.', 400);
        }
        if (null !== $from && null !== $to && $from > $to) {
            return apiError('from doit être antérieur ou égal à to.', 400);
        }

        return [$from, $to];
    }

    /**
     * @return \DateTimeImmutable|null|false
     */
    private function parseDate(mixed $value): \DateTimeImmutable|null|false
    {
        if (null === $value || '' === $value) {
            return null;
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $value);

        return false === $date || $date->format('Y-m-d') !== $value ? false : $date;
    }

    /**
     * @param list<string> $domainNames
     */
    private function findDomainOwner(array $domainNames): ?AnalyticsProject
    {
        foreach ($this->projectRepository->findAll() as $project) {
            if (array_intersect($project->getDomainNames(), $domainNames) !== []) {
                return $project;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeProject(AnalyticsProject $project): array
    {
        /** @var array<string, mixed> $data */
        $data = $this->serializer->normalize($project, context: [
            'groups' => ['analytics:project:read', 'user:identifier'],
            AbstractObjectNormalizer::ENABLE_MAX_DEPTH => true,
        ]);

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeInput(AnalyticsInput $input): array
    {
        /** @var array<string, mixed> $data */
        $data = $this->serializer->normalize($input, context: [
            'groups' => ['analytics:input:read', 'analytics:project:read', 'user:identifier'],
            AbstractObjectNormalizer::ENABLE_MAX_DEPTH => true,
        ]);

        return $data;
    }
}
