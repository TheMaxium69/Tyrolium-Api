<?php

namespace App\Controller\Useritium;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Dashboard Useritium (public), réutilisé par le HUB pour afficher un compte
 * sous forme de chip. Ne renvoie que le minimum utile à l'affichage — jamais
 * d'email ni de permission.
 */
class UseritiumDashboardController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository,
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
}
