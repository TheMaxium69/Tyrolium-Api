<?php

namespace App\Controller\Useritium;

use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class UseritiumAdminController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
    ) {
    }

    /**
     * GET /useritium/admin/get-all-user
     * Recherche et liste des utilisateurs (vue administrateur)
     */
    #[Route('/useritium/admin/get-all-user', name: 'useritium_admin_get_all_user', methods: ['GET'])]
    public function getAllUser(Request $request): JsonResponse
    {
        $search = trim((string) $request->query->get('search', ''));

        $qb = $this->userRepository->createQueryBuilder('u')
            ->leftJoin('u.emails', 'e')
            ->addSelect('e');

        if ('' !== $search) {
            $qb->where('u.username LIKE :search OR e.email LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        $users = $qb->getQuery()->getResult();

        return $this->json([
            'success' => true,
            'code' => 200,
            'message' => 'Liste des utilisateurs récupérée avec succès.',
            'data' => $users,
            'errors' => null,
            'meta' => [
                'timestamp' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
                'total' => count($users),
            ],
        ], 200, [], ['groups' => ['user:read']]);
    }

    /**
     * GET /useritium/admin/get-one-user
     * Obtenir le détail complet d'un utilisateur par son ID ou son username
     */
    #[Route('/useritium/admin/get-one-user', name: 'useritium_admin_get_one_user', methods: ['GET'])]
    public function getOneUser(Request $request): JsonResponse
    {
        $id = $request->query->get('id');
        $username = $request->query->get('username');

        $user = null;
        if (null !== $id) {
            $user = $this->userRepository->find((int) $id);
        } elseif (null !== $username) {
            $user = $this->userRepository->findOneBy(['username' => $username]);
        }

        if (null === $user) {
            return $this->json([
                'success' => false,
                'code' => 404,
                'message' => 'Utilisateur introuvable.',
                'data' => null,
                'errors' => null,
            ], 404);
        }

        return $this->json([
            'success' => true,
            'code' => 200,
            'message' => 'Détails de l\'utilisateur récupérés.',
            'data' => $user,
            'errors' => null,
        ], 200, [], ['groups' => ['user:read']]);
    }

    /**
     * POST /useritium/admin/post-revoke-tokens
     * Révoquer immédiatement tous les tokens JWT actifs d'un utilisateur
     */
    #[Route('/useritium/admin/post-revoke-tokens', name: 'useritium_admin_post_revoke_tokens', methods: ['POST'])]
    public function postRevokeTokens(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true) ?? [];
        $userId = $payload['userId'] ?? $request->query->get('userId');

        if (null === $userId) {
            return $this->json([
                'success' => false,
                'code' => 400,
                'message' => 'L\'identifiant de l\'utilisateur (userId) est obligatoire.',
                'data' => null,
            ], 400);
        }

        $user = $this->userRepository->find((int) $userId);
        if (null === $user) {
            return $this->json([
                'success' => false,
                'code' => 404,
                'message' => 'Utilisateur introuvable.',
                'data' => null,
            ], 404);
        }

        // Invalidation de tous les tokens JWT émis jusqu'ici
        $user->invalidateAllTokens();
        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'code' => 200,
            'message' => sprintf('Tous les tokens de l\'utilisateur \'%s\' ont été révoqués. Il devra se re-connecter.', $user->getUsername()),
            'data' => [
                'userId' => $user->getId(),
                'username' => $user->getUsername(),
                'tokensValidSince' => $user->getTokensValidSince()?->format(\DateTimeInterface::ATOM),
            ],
            'errors' => null,
        ]);
    }
}
