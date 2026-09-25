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
        private readonly NormalizerInterface $serializer,
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
            fn (User $user): array => $this->normalizeUser($user),
            $this->userRepository->findAll(),
        );

        return apiSuccess(data: $users);
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeUser(User $user): array
    {
        /** @var array<string, mixed> $data */
        $data = $this->serializer->normalize($user, context: ['groups' => ['user:read']]);

        return $data;
    }
}
