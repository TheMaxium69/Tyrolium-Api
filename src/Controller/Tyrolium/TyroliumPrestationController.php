<?php

namespace App\Controller\Tyrolium;

use App\Entity\Offre;
use App\Entity\Prestation;
use App\Entity\User;
use App\Enum\OffreVisibility;
use App\Enum\PrestationStatus;
use App\Repository\OffreRepository;
use App\Repository\PrestationRepository;
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
 * Gestion des offres (catalogue) et prestations (lien offre <-> client) — voir
 * cahier des charges (TyroliumPrestationController) et Offre.php/Prestation.php
 * pour la séparation catalogue/attribution.
 *
 * Permissions attendues en catalogue (à créer/lier via TyroliumPermissionController,
 * voir .doc/permissions.md) :
 *   tyrolium.offre.{view,create,update,delete} + tyrolium.offre.manage (parapluie)
 *   tyrolium.prestation.{view,create,update,delete} + tyrolium.prestation.manage (parapluie)
 */
class TyroliumPrestationController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly OffreRepository $offreRepository,
        private readonly PrestationRepository $prestationRepository,
        private readonly UserRepository $userRepository,
        private readonly ValidatorInterface $validator,
        private readonly NormalizerInterface $serializer,
    ) {
    }

    // ------------------------------------------------------------------
    // Offre (catalogue)
    // ------------------------------------------------------------------

    #[IsGranted('PERMS_TYROLIUM_OFFRE_VIEW')]
    #[Route('/tyrolium/prestation/get-all-offre', name: 'tyrolium_prestation_get_all_offre', methods: ['GET'])]
    public function getAllOffre(): JsonResponse
    {
        $offres = array_map(
            fn (Offre $offre): array => $this->normalizeOffre($offre),
            $this->offreRepository->findAll(),
        );

        return apiSuccess(data: $offres);
    }

    #[IsGranted('PERMS_TYROLIUM_OFFRE_VIEW')]
    #[Route('/tyrolium/prestation/get-one-offre/{id}', name: 'tyrolium_prestation_get_one_offre', methods: ['GET'])]
    public function getOneOffre(int $id): JsonResponse
    {
        $offre = $this->offreRepository->find($id);

        if (null === $offre) {
            return apiError('Offre introuvable.', 404);
        }

        return apiSuccess(data: $this->normalizeOffre($offre));
    }

    #[IsGranted('PERMS_TYROLIUM_OFFRE_CREATE')]
    #[Route('/tyrolium/prestation/post-create-offre', name: 'tyrolium_prestation_post_create_offre', methods: ['POST'])]
    public function postCreateOffre(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true) ?? [];

        $visibility = OffreVisibility::tryFrom((string) ($payload['visibility'] ?? OffreVisibility::LISTED->value));
        if (null === $visibility) {
            return apiError("visibility doit être 'listed' ou 'custom'.", 400);
        }

        $offre = new Offre();
        if (isset($payload['tagName']) && is_string($payload['tagName'])) {
            $offre->setTagName($payload['tagName']);
        }
        if (isset($payload['displayName']) && is_string($payload['displayName'])) {
            $offre->setDisplayName($payload['displayName']);
        }
        if (isset($payload['description']) && is_string($payload['description'])) {
            $offre->setDescription($payload['description']);
        }
        $offre->setVisibility($visibility);
        if (isset($payload['price'])) {
            if (!is_int($payload['price'])) {
                return apiError('price doit être un entier (centimes).', 400);
            }
            $offre->setPrice($payload['price']);
        }
        if (isset($payload['isActive'])) {
            if (!is_bool($payload['isActive'])) {
                return apiError('isActive doit être un booléen.', 400);
            }
            $offre->setIsActive($payload['isActive']);
        }

        $violations = $this->validator->validate($offre);
        if (count($violations) > 0) {
            return apiValidationError($violations, 'Offre invalide.');
        }

        try {
            $this->entityManager->persist($offre);
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            return apiError('Une offre avec ce tagName existe déjà.', 409);
        }

        return apiSuccess(data: $this->normalizeOffre($offre), message: 'Offre créée.', code: 201);
    }

    #[IsGranted('PERMS_TYROLIUM_OFFRE_UPDATE')]
    #[Route('/tyrolium/prestation/put-update-offre/{id}', name: 'tyrolium_prestation_put_update_offre', methods: ['PUT'])]
    public function putUpdateOffre(int $id, Request $request): JsonResponse
    {
        $offre = $this->offreRepository->find($id);
        if (null === $offre) {
            return apiError('Offre introuvable.', 404);
        }

        $payload = json_decode($request->getContent(), true) ?? [];

        if (isset($payload['tagName'])) {
            if (!is_string($payload['tagName'])) {
                return apiError('tagName doit être une chaîne.', 400);
            }
            $offre->setTagName($payload['tagName']);
        }
        if (isset($payload['displayName'])) {
            if (!is_string($payload['displayName'])) {
                return apiError('displayName doit être une chaîne.', 400);
            }
            $offre->setDisplayName($payload['displayName']);
        }
        if (array_key_exists('description', $payload)) {
            if (null !== $payload['description'] && !is_string($payload['description'])) {
                return apiError('description doit être une chaîne ou null.', 400);
            }
            $offre->setDescription($payload['description']);
        }
        if (isset($payload['visibility'])) {
            $visibility = OffreVisibility::tryFrom((string) $payload['visibility']);
            if (null === $visibility) {
                return apiError("visibility doit être 'listed' ou 'custom'.", 400);
            }
            $offre->setVisibility($visibility);
        }
        if (array_key_exists('price', $payload)) {
            if (null !== $payload['price'] && !is_int($payload['price'])) {
                return apiError('price doit être un entier (centimes) ou null.', 400);
            }
            $offre->setPrice($payload['price']);
        }
        if (isset($payload['isActive'])) {
            if (!is_bool($payload['isActive'])) {
                return apiError('isActive doit être un booléen.', 400);
            }
            $offre->setIsActive($payload['isActive']);
        }

        $violations = $this->validator->validate($offre);
        if (count($violations) > 0) {
            return apiValidationError($violations, 'Offre invalide.');
        }

        try {
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            return apiError('Une offre avec ce tagName existe déjà.', 409);
        }

        return apiSuccess(data: $this->normalizeOffre($offre), message: 'Offre mise à jour.');
    }

    #[IsGranted('PERMS_TYROLIUM_OFFRE_DELETE')]
    #[Route('/tyrolium/prestation/delete-offre/{id}', name: 'tyrolium_prestation_delete_offre', methods: ['DELETE'])]
    public function deleteOffre(int $id): JsonResponse
    {
        $offre = $this->offreRepository->find($id);
        if (null === $offre) {
            return apiError('Offre introuvable.', 404);
        }

        $this->entityManager->remove($offre);

        try {
            $this->entityManager->flush();
        } catch (ForeignKeyConstraintViolationException) {
            // Offre::onDelete est RESTRICT côté Prestation — volontaire, on
            // ne supprime jamais silencieusement l'historique des clients.
            return apiError('Impossible de supprimer cette offre : des prestations y sont encore liées.', 409);
        }

        return apiSuccess(message: 'Offre supprimée.');
    }

    // ------------------------------------------------------------------
    // Prestation (lien offre <-> client)
    // ------------------------------------------------------------------

    #[IsGranted('PERMS_TYROLIUM_PRESTATION_VIEW')]
    #[Route('/tyrolium/prestation/get-all-prestation', name: 'tyrolium_prestation_get_all_prestation', methods: ['GET'])]
    public function getAllPrestation(): JsonResponse
    {
        $prestations = array_map(
            fn (Prestation $prestation): array => $this->normalizePrestation($prestation),
            $this->prestationRepository->findAll(),
        );

        return apiSuccess(data: $prestations);
    }

    #[IsGranted('PERMS_TYROLIUM_PRESTATION_VIEW')]
    #[Route('/tyrolium/prestation/get-one-prestation/{id}', name: 'tyrolium_prestation_get_one_prestation', methods: ['GET'])]
    public function getOnePrestation(int $id): JsonResponse
    {
        $prestation = $this->prestationRepository->find($id);

        if (null === $prestation) {
            return apiError('Prestation introuvable.', 404);
        }

        return apiSuccess(data: $this->normalizePrestation($prestation));
    }

    #[IsGranted('PERMS_TYROLIUM_PRESTATION_CREATE')]
    #[Route('/tyrolium/prestation/post-create-prestation', name: 'tyrolium_prestation_post_create_prestation', methods: ['POST'])]
    public function postCreatePrestation(Request $request, #[CurrentUser] User $createdBy): JsonResponse
    {
        $payload = json_decode($request->getContent(), true) ?? [];

        $offreId = $payload['offreId'] ?? null;
        if (!is_int($offreId) && !(is_string($offreId) && ctype_digit($offreId))) {
            return apiError('offreId manquant ou invalide.', 400);
        }
        $offre = $this->offreRepository->find((int) $offreId);
        if (null === $offre) {
            return apiError('Offre introuvable.', 404);
        }

        $userId = $payload['userId'] ?? null;
        $clientName = $payload['clientName'] ?? null;
        $clientEmail = $payload['clientEmail'] ?? null;

        $client = null;
        if (null !== $userId) {
            if (!is_int($userId) && !(is_string($userId) && ctype_digit($userId))) {
                return apiError('userId invalide.', 400);
            }
            $client = $this->userRepository->find((int) $userId);
            if (null === $client) {
                return apiError('Utilisateur introuvable.', 404);
            }
        } elseif (null === $clientName || null === $clientEmail) {
            // Voir Prestation.php : au moins un des deux moyens d'identifier
            // le client est obligatoire, sinon la prestation est orpheline.
            return apiError('Renseigne soit userId, soit clientName ET clientEmail.', 400);
        }

        $prestation = new Prestation();
        $prestation->setOffre($offre);
        $prestation->setCreatedBy($createdBy);
        if (null !== $client) {
            $prestation->setUser($client);
        }
        if (is_string($clientName)) {
            $prestation->setClientName($clientName);
        }
        if (is_string($clientEmail)) {
            $prestation->setClientEmail($clientEmail);
        }
        if (isset($payload['content']) && is_string($payload['content'])) {
            $prestation->setContent($payload['content']);
        }
        if (isset($payload['progress'])) {
            if (!is_int($payload['progress'])) {
                return apiError('progress doit être un entier entre 0 et 100.', 400);
            }
            $prestation->setProgress($payload['progress']);
        }
        if (isset($payload['price'])) {
            if (!is_int($payload['price'])) {
                return apiError('price doit être un entier (centimes).', 400);
            }
            $prestation->setPrice($payload['price']);
        }
        if (isset($payload['status'])) {
            $status = PrestationStatus::tryFrom((string) $payload['status']);
            if (null === $status) {
                return apiError('status invalide.', 400);
            }
            $prestation->setStatus($status);
        }

        $violations = $this->validator->validate($prestation);
        if (count($violations) > 0) {
            return apiValidationError($violations, 'Prestation invalide.');
        }

        $this->entityManager->persist($prestation);
        $this->entityManager->flush();

        return apiSuccess(data: $this->normalizePrestation($prestation), message: 'Prestation créée.', code: 201);
    }

    #[IsGranted('PERMS_TYROLIUM_PRESTATION_UPDATE')]
    #[Route('/tyrolium/prestation/put-update-prestation/{id}', name: 'tyrolium_prestation_put_update_prestation', methods: ['PUT'])]
    public function putUpdatePrestation(int $id, Request $request): JsonResponse
    {
        $prestation = $this->prestationRepository->find($id);
        if (null === $prestation) {
            return apiError('Prestation introuvable.', 404);
        }

        $payload = json_decode($request->getContent(), true) ?? [];

        if (isset($payload['offreId'])) {
            if (!is_int($payload['offreId']) && !(is_string($payload['offreId']) && ctype_digit($payload['offreId']))) {
                return apiError('offreId invalide.', 400);
            }
            $offre = $this->offreRepository->find((int) $payload['offreId']);
            if (null === $offre) {
                return apiError('Offre introuvable.', 404);
            }
            $prestation->setOffre($offre);
        }
        if (array_key_exists('userId', $payload)) {
            if (null === $payload['userId']) {
                $prestation->setUser(null);
            } else {
                if (!is_int($payload['userId']) && !(is_string($payload['userId']) && ctype_digit($payload['userId']))) {
                    return apiError('userId invalide.', 400);
                }
                $client = $this->userRepository->find((int) $payload['userId']);
                if (null === $client) {
                    return apiError('Utilisateur introuvable.', 404);
                }
                $prestation->setUser($client);
            }
        }
        if (array_key_exists('clientName', $payload)) {
            if (null !== $payload['clientName'] && !is_string($payload['clientName'])) {
                return apiError('clientName doit être une chaîne ou null.', 400);
            }
            $prestation->setClientName($payload['clientName']);
        }
        if (array_key_exists('clientEmail', $payload)) {
            if (null !== $payload['clientEmail'] && !is_string($payload['clientEmail'])) {
                return apiError('clientEmail doit être une chaîne ou null.', 400);
            }
            $prestation->setClientEmail($payload['clientEmail']);
        }
        if (null === $prestation->getUser() && (null === $prestation->getClientName() || null === $prestation->getClientEmail())) {
            return apiError('Une prestation doit toujours avoir soit un userId, soit clientName ET clientEmail.', 400);
        }
        if (array_key_exists('content', $payload)) {
            if (null !== $payload['content'] && !is_string($payload['content'])) {
                return apiError('content doit être une chaîne ou null.', 400);
            }
            $prestation->setContent($payload['content']);
        }
        if (isset($payload['progress'])) {
            if (!is_int($payload['progress'])) {
                return apiError('progress doit être un entier entre 0 et 100.', 400);
            }
            $prestation->setProgress($payload['progress']);
        }
        if (array_key_exists('price', $payload)) {
            if (null !== $payload['price'] && !is_int($payload['price'])) {
                return apiError('price doit être un entier (centimes) ou null.', 400);
            }
            $prestation->setPrice($payload['price']);
        }
        if (isset($payload['status'])) {
            $status = PrestationStatus::tryFrom((string) $payload['status']);
            if (null === $status) {
                return apiError('status invalide.', 400);
            }
            $prestation->setStatus($status);
            if (PrestationStatus::TERMINATED === $status && null === $prestation->getEndedAt()) {
                $prestation->setEndedAt(new \DateTimeImmutable());
            }
        }

        $violations = $this->validator->validate($prestation);
        if (count($violations) > 0) {
            return apiValidationError($violations, 'Prestation invalide.');
        }

        $this->entityManager->flush();

        return apiSuccess(data: $this->normalizePrestation($prestation), message: 'Prestation mise à jour.');
    }

    #[IsGranted('PERMS_TYROLIUM_PRESTATION_DELETE')]
    #[Route('/tyrolium/prestation/delete-prestation/{id}', name: 'tyrolium_prestation_delete_prestation', methods: ['DELETE'])]
    public function deletePrestation(int $id): JsonResponse
    {
        $prestation = $this->prestationRepository->find($id);
        if (null === $prestation) {
            return apiError('Prestation introuvable.', 404);
        }

        $this->entityManager->remove($prestation);
        $this->entityManager->flush();

        return apiSuccess(message: 'Prestation supprimée.');
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeOffre(Offre $offre): array
    {
        /** @var array<string, mixed> $data */
        $data = $this->serializer->normalize($offre, context: ['groups' => ['offre:read']]);

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizePrestation(Prestation $prestation): array
    {
        /** @var array<string, mixed> $data */
        $data = $this->serializer->normalize($prestation, context: [
            'groups' => ['prestation:read', 'offre:read', 'user:identifier'],
            AbstractObjectNormalizer::ENABLE_MAX_DEPTH => true,
        ]);

        return $data;
    }
}
