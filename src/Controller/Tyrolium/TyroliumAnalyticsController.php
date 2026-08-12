<?php

namespace App\Controller\Tyrolium;

use App\Entity\Tyrolium\AnalyticsInput;
use App\Entity\Tyrolium\AnalyticsProject;
use App\Repository\Tyrolium\AnalyticsInputRepository;
use App\Repository\Tyrolium\AnalyticsProjectRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class TyroliumAnalyticsController extends AbstractController
{
    #[Route('/tyrolium/analytics/post-create-project', name: 'tyrolium_analytics_post_create_project', methods: ['POST'])]
    public function postCreateProject(
        Request $request,
        EntityManagerInterface $entityManager,
        AnalyticsProjectRepository $projectRepository
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (empty($data)) {
            return apiError('JSON invalide.', 400);
        }

        $domainNames = $data['domainNames'] ?? $data['domain_names'] ?? null;
        $useritiumToken = $data['useritiumToken'] ?? $data['useritium_token'] ?? null;

        if (empty($domainNames) || !is_array($domainNames) || empty($domainNames[0])) {
            return apiError('Nom de domaine invalide ou manquant.', 400);
        }

        if (empty($useritiumToken)) {
            return apiError('Le jeton useritiumToken est requis.', 400);
        }

        // Simulé pour le moment : vérification de l'ID Useritium (ID 1)
        $useritiumId = 1;

        // Vérification si un des noms de domaine existe déjà dans les projets
        $allProjects = $projectRepository->findAll();
        foreach ($allProjects as $existingProject) {
            foreach ($existingProject->getDomainNames() as $existingDomain) {
                if (in_array($existingDomain, $domainNames, true)) {
                    return apiError('Le nom de domaine "'.$existingDomain.'" existe déjà.', 409);
                }
            }
        }

        $project = new AnalyticsProject();
        $project->setUseritiumId($useritiumId);
        $project->setDomainNames($domainNames);

        $clef = uniqid(md5($domainNames[0]));
        $tag = 'TyroTag-' . $clef;
        $project->setTag($tag);

        $entityManager->persist($project);
        $entityManager->flush();

        return apiSuccess($project->toArray(), 'Projet Analytics créé avec succès.', 201);
    }

    #[Route('/tyrolium/analytics/get-all-project', name: 'tyrolium_analytics_get_all_project', methods: ['GET'])]
    public function getAllProject(AnalyticsProjectRepository $projectRepository): JsonResponse
    {
        $projects = $projectRepository->findAll();
        $data = array_map(static fn (AnalyticsProject $p) => $p->toArray(), $projects);

        return apiSuccess($data, 'Tous les projets Analytics ont été récupérés.');
    }

    #[Route('/tyrolium/analytics/get-one-project/{id}', name: 'tyrolium_analytics_get_one_project', methods: ['GET'])]
    public function getOneProject(int $id, AnalyticsProjectRepository $projectRepository): JsonResponse
    {
        $project = $projectRepository->find($id);
        if (!$project) {
            return apiError('Projet non trouvé.', 404);
        }

        return apiSuccess($project->toArray(), 'Projet Analytics récupéré.');
    }

    #[Route('/tyrolium/analytics/put-update-project-domain/{id}', name: 'tyrolium_analytics_put_update_project_domain', methods: ['PUT'])]
    public function putUpdateProjectDomain(
        int $id,
        Request $request,
        EntityManagerInterface $entityManager,
        AnalyticsProjectRepository $projectRepository
    ): JsonResponse {
        $project = $projectRepository->find($id);
        if (!$project) {
            return apiError('Projet non trouvé.', 404);
        }

        $data = json_decode($request->getContent(), true);
        $newDomain = $data['domainName'] ?? $data['domain_names'] ?? $data['newDomain'] ?? null;

        if (!$newDomain) {
            return apiError('Domaine invalide.', 400);
        }

        $allProjects = $projectRepository->findAll();
        foreach ($allProjects as $p) {
            if (in_array($newDomain, $p->getDomainNames(), true)) {
                if ($p->getId() === $project->getId()) {
                    return apiError('Le domaine existe déjà dans ce projet.', 409);
                }
                return apiError('Le domaine existe déjà dans un autre projet.', 409);
            }
        }

        $existingDomains = $project->getDomainNames();
        $existingDomains[] = $newDomain;
        $project->setDomainNames($existingDomains);

        $entityManager->persist($project);
        $entityManager->flush();

        return apiSuccess($project->toArray(), 'Domaine ajouté au projet Analytics avec succès.');
    }

    #[Route('/tyrolium/analytics/delete-project/{id}', name: 'tyrolium_analytics_delete_project', methods: ['DELETE'])]
    public function deleteProject(
        int $id,
        EntityManagerInterface $entityManager,
        AnalyticsProjectRepository $projectRepository
    ): JsonResponse {
        $project = $projectRepository->find($id);
        if (!$project) {
            return apiError('Projet non trouvé.', 404);
        }

        $entityManager->remove($project);
        $entityManager->flush();

        return apiSuccess(null, 'Le projet Analytics a été supprimé.');
    }

    #[Route('/tyrolium/analytics/post-create-input', name: 'tyrolium_analytics_post_create_input', methods: ['POST'])]
    public function postCreateInput(
        Request $request,
        EntityManagerInterface $entityManager,
        AnalyticsProjectRepository $projectRepository,
        AnalyticsInputRepository $inputRepository
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        $projectTag = $data['projectTag'] ?? $data['project_tag'] ?? null;
        $ip = $data['ip'] ?? null;
        $pageName = $data['pageName'] ?? $data['page_name'] ?? null;
        $uri = $data['uri'] ?? null;
        $isLogin = $data['isLogin'] ?? $data['is_login'] ?? null;

        if (!$projectTag || !$ip || !$pageName || !$uri || null === $isLogin) {
            return apiError('Tous les champs requis doivent être remplis.', 400);
        }

        $project = $projectRepository->findOneBy(['tag' => $projectTag]);
        if (!$project) {
            return apiError('Aucun projet trouvé pour ce tag.', 404);
        }

        $existingInput = $inputRepository->findOneBy([
            'project' => $project,
            'ip' => $ip,
            'pageName' => $pageName,
            'uri' => $uri,
            'isLogin' => (bool)$isLogin,
        ]);

        if ($existingInput) {
            return apiError('Cette entrée Analytics existe déjà.', 409);
        }

        $input = new AnalyticsInput();
        $input->setProject($project);
        $input->setIp($ip);
        $input->setPageName($pageName);
        $input->setUri($uri);
        $input->setIsLogin((bool)$isLogin);

        $entityManager->persist($input);
        $entityManager->flush();

        return apiSuccess($input->toArray(), 'Entrée Analytics créée avec succès.', 201);
    }

    #[Route('/tyrolium/analytics/get-all-input', name: 'tyrolium_analytics_get_all_input', methods: ['GET'])]
    public function getAllInput(AnalyticsInputRepository $inputRepository): JsonResponse
    {
        $inputs = $inputRepository->findAll();
        $data = array_map(static fn (AnalyticsInput $i) => $i->toArray(), $inputs);

        return apiSuccess($data, 'Liste des entrées Analytics récupérée.');
    }

    #[Route('/tyrolium/analytics/get-search-input', name: 'tyrolium_analytics_get_search_input', methods: ['GET'])]
    public function getSearchInput(
        Request $request,
        AnalyticsInputRepository $inputRepository,
        AnalyticsProjectRepository $projectRepository
    ): JsonResponse {
        $projectTag = $request->query->get('project_tag') ?? $request->query->get('projectTag');
        $project = null;

        if (null !== $projectTag) {
            $project = $projectRepository->findOneBy(['tag' => $projectTag]);
            if (!$project) {
                return apiError('Aucun projet trouvé pour ce tag.', 404);
            }
        }

        $filters = [
            'project' => $project,
            'ip' => $request->query->get('ip'),
            'pageName' => $request->query->get('page_name') ?? $request->query->get('pageName'),
            'uri' => $request->query->get('uri'),
        ];

        $inputs = $inputRepository->findByAdvancedFilters($filters);
        $data = array_map(static fn (AnalyticsInput $i) => $i->toArray(), $inputs);

        return apiSuccess($data, 'Résultats de la recherche Analytics.');
    }

    #[Route('/tyrolium/analytics/get-input-by-project/{id}', name: 'tyrolium_analytics_get_input_by_project', methods: ['GET'])]
    public function getInputByProject(
        int $id,
        AnalyticsInputRepository $inputRepository,
        AnalyticsProjectRepository $projectRepository
    ): JsonResponse {
        $project = $projectRepository->find($id);
        if (!$project) {
            return apiError('Projet non trouvé.', 404);
        }

        $inputs = $inputRepository->findBy(['project' => $project]);
        $data = array_map(static fn (AnalyticsInput $i) => $i->toArray(), $inputs);

        return apiSuccess($data, 'Entrées Analytics du projet récupérées.');
    }
}
