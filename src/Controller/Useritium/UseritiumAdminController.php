<?php

namespace App\Controller\Useritium;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Administration des comptes Useritium — voir cahier des charges
 * (UseritiumAdminController). Volontairement réduit à la liste des comptes
 * pour l'instant (décision de Maxime, 25/09/2026) — signalements/bannissement
 * pas encore construits, relèvent d'un système de modération à concevoir à
 * part entière plus tard.
 */
class UseritiumAdminController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository,
    ) {
    }

    /**
     * Pas de pagination/filtrage pour l'instant — à ajouter le jour où le
     * nombre de comptes le justifie, pas de besoin identifié aujourd'hui.
     */
    #[IsGranted('ROLE_OWNER')]
    #[Route('/useritium/admin/get-all-user', name: 'useritium_admin_get_all_user', methods: ['GET'])]
    public function getAllUser(): JsonResponse
    {
        $users = array_map(
            fn (User $user): array => $this->serializeUser($user),
            $this->userRepository->findAll(),
        );

        return apiSuccess(data: $users);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeUser(User $user): array
    {
        return [
            'id' => $user->getId(),
            'username' => $user->getUsername(),
            'accessLevel' => $user->getAccessLevel()->value,
            'emails' => array_map(
                static fn ($email): array => [
                    'id' => $email->getId(),
                    'email' => $email->getEmail(),
                    'isDefault' => $email->isDefault(),
                    'isVerified' => $email->isVerified(),
                ],
                $user->getEmails()->toArray(),
            ),
            'createdAt' => $user->getCreatedAt()->format(DATE_ATOM),
        ];
    }
}
