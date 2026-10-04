<?php

namespace App\Controller\Useritium;

use App\Entity\User;
use App\Entity\UserEmail;
use App\Entity\UserPermission;
use App\Enum\AccessLevel;
use App\Helper\Pagination;
use App\Repository\UserEmailRepository;
use App\Repository\UserRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Administration des comptes Useritium, protégée par permissions (useritium.user.*)
 * pour le HUB. Un owner ne peut jamais être modifié, suspendu ni déconnecté
 * via l'API (même règle que TyroliumPermissionController).
 */
class UseritiumAdminController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly UserEmailRepository $userEmailRepository,
        private readonly ValidatorInterface $validator,
        private readonly NormalizerInterface $serializer,
        #[Autowire('%kernel.environment%')]
        private readonly string $environment,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    #[IsGranted('PERMS_USERITIUM_USER_VIEW')]
    #[Route('/useritium/admin/get-all-user', name: 'useritium_admin_get_all_user', methods: ['GET'])]
    public function getAllUser(Request $request): JsonResponse
    {
        $result = Pagination::fromQueryBuilder(
            $this->userRepository->createQueryBuilder('u')->orderBy('u.id', 'ASC'),
            $request,
        );

        return apiSuccess(
            data: array_map(fn (User $user): array => $this->normalizeUser($user), $result['items']),
            meta: ['pagination' => $result['pagination']],
        );
    }

    #[IsGranted('PERMS_USERITIUM_USER_VIEW')]
    #[Route('/useritium/admin/get-one-user/{id}', name: 'useritium_admin_get_one_user', methods: ['GET'])]
    public function getOneUser(int $id): JsonResponse
    {
        $user = $this->userRepository->find($id);
        if (null === $user) {
            return apiError('Utilisateur introuvable.', 404);
        }

        return apiSuccess(data: $this->normalizeUserDetail($user));
    }

    #[IsGranted('PERMS_USERITIUM_USER_UPDATE')]
    #[Route('/useritium/admin/put-update-user-username/{id}', name: 'useritium_admin_put_update_user_username', methods: ['PUT'])]
    public function putUpdateUserUsername(int $id, Request $request): JsonResponse
    {
        $user = $this->userRepository->find($id);
        if (null === $user) {
            return apiError('Utilisateur introuvable.', 404);
        }
        if ($forbidden = $this->refuseIfOwner($user)) {
            return $forbidden;
        }

        $payload = json_decode($request->getContent(), true) ?? [];
        $username = $payload['username'] ?? null;
        if (!is_string($username)) {
            return apiError('username doit être une chaîne.', 400);
        }

        $user->setUsername($username);

        $violations = $this->validator->validate($user);
        if (count($violations) > 0) {
            return apiValidationError($violations, 'Pseudo invalide.');
        }

        try {
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            return apiError('Ce pseudo est déjà pris.', 409);
        }

        return apiSuccess(data: $this->normalizeUserDetail($user), message: 'Pseudo mis à jour.');
    }

    #[IsGranted('PERMS_USERITIUM_USER_UPDATE')]
    #[Route('/useritium/admin/put-update-user-display-name/{id}', name: 'useritium_admin_put_update_user_display_name', methods: ['PUT'])]
    public function putUpdateUserDisplayName(int $id, Request $request): JsonResponse
    {
        $user = $this->userRepository->find($id);
        if (null === $user) {
            return apiError('Utilisateur introuvable.', 404);
        }
        if ($forbidden = $this->refuseIfOwner($user)) {
            return $forbidden;
        }

        $payload = json_decode($request->getContent(), true) ?? [];
        if (!array_key_exists('displayName', $payload) || (null !== $payload['displayName'] && !is_string($payload['displayName']))) {
            return apiError('displayName doit être une chaîne ou null.', 400);
        }

        $displayName = null !== $payload['displayName'] ? trim($payload['displayName']) : null;
        $user->setDisplayName('' === $displayName ? null : $displayName);

        $violations = $this->validator->validate($user);
        if (count($violations) > 0) {
            return apiValidationError($violations, 'Nom affiché invalide.');
        }

        $this->entityManager->flush();

        return apiSuccess(data: $this->normalizeUserDetail($user), message: 'Nom affiché mis à jour.');
    }

    #[IsGranted('PERMS_USERITIUM_USER_UPDATE')]
    #[Route('/useritium/admin/delete-user-pp/{id}', name: 'useritium_admin_delete_user_pp', methods: ['DELETE'])]
    public function deleteUserPp(int $id): JsonResponse
    {
        $user = $this->userRepository->find($id);
        if (null === $user) {
            return apiError('Utilisateur introuvable.', 404);
        }
        if ($forbidden = $this->refuseIfOwner($user)) {
            return $forbidden;
        }

        $previous = $user->getPp();
        $user->setPp(null);
        $this->entityManager->flush();

        if (null !== $previous && str_starts_with($previous, '/uploads/avatars/')) {
            $path = $this->projectDir.'/public'.$previous;
            if (is_file($path)) {
                unlink($path);
            }
        }

        return apiSuccess(data: $this->normalizeUserDetail($user), message: 'Photo de profil supprimée.');
    }

    #[IsGranted('PERMS_USERITIUM_USER_UPDATE')]
    #[Route('/useritium/admin/post-add-user-email/{id}', name: 'useritium_admin_post_add_user_email', methods: ['POST'])]
    public function postAddUserEmail(int $id, Request $request): JsonResponse
    {
        $user = $this->userRepository->find($id);
        if (null === $user) {
            return apiError('Utilisateur introuvable.', 404);
        }
        if ($forbidden = $this->refuseIfOwner($user)) {
            return $forbidden;
        }

        $payload = json_decode($request->getContent(), true) ?? [];
        $email = is_string($payload['email'] ?? null) ? $payload['email'] : '';

        $violations = $this->validator->validate($email, [
            new Assert\NotBlank(message: "L'email est obligatoire."),
            new Assert\Email(message: "L'adresse email n'est pas valide."),
        ]);
        if (count($violations) > 0) {
            return apiValidationError($violations, 'Email invalide.');
        }

        if ($user->getEmails()->count() >= User::MAX_EMAILS_PER_USER) {
            return apiError(sprintf('Un compte ne peut pas avoir plus de %d emails.', User::MAX_EMAILS_PER_USER), 409);
        }

        foreach ($user->getEmails() as $existingEmail) {
            if ($existingEmail->getEmail() === $email) {
                return apiError('Cet email est déjà associé à ce compte.', 409);
            }
        }

        if (null !== $this->userEmailRepository->findOneBy(['email' => $email, 'isDefault' => true])) {
            return apiError('Cet email est déjà utilisé comme email par défaut sur un autre compte.', 409);
        }

        $userEmail = new UserEmail();
        $userEmail->setEmail($email);
        $verificationToken = $userEmail->generateVerificationToken();
        $user->addEmail($userEmail);

        try {
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            return apiError('Cet email est déjà utilisé.', 409);
        }

        return apiSuccess(
            data: $this->withDebugToken($this->normalizeUserEmail($userEmail), 'verificationToken', $verificationToken),
            message: 'Email ajouté. Il doit être vérifié avant de pouvoir devenir l\'email par défaut.',
            code: 201,
        );
    }

    #[IsGranted('PERMS_USERITIUM_USER_UPDATE')]
    #[Route('/useritium/admin/delete-user-email/{userId}/{emailId}', name: 'useritium_admin_delete_user_email', methods: ['DELETE'])]
    public function deleteUserEmail(int $userId, int $emailId): JsonResponse
    {
        $user = $this->userRepository->find($userId);
        if (null === $user) {
            return apiError('Utilisateur introuvable.', 404);
        }
        if ($forbidden = $this->refuseIfOwner($user)) {
            return $forbidden;
        }

        $target = $this->userEmailRepository->find($emailId);
        if (null === $target || $target->getUser() !== $user) {
            return apiError('Email introuvable sur ce compte.', 404);
        }

        if ($target->isDefault()) {
            return apiError('Impossible de supprimer l\'email par défaut : passe un autre email vérifié en défaut avant.', 409);
        }

        if (1 === $user->getEmails()->count()) {
            return apiError('Un compte doit toujours avoir au moins un email.', 409);
        }

        $user->removeEmail($target);
        $this->entityManager->remove($target);
        $this->entityManager->flush();

        return apiSuccess(message: 'Email supprimé.');
    }

    #[IsGranted('PERMS_USERITIUM_USER_BAN')]
    #[Route('/useritium/admin/post-ban-user/{id}', name: 'useritium_admin_post_ban_user', methods: ['POST'])]
    public function postBanUser(int $id, Request $request, #[CurrentUser] User $admin): JsonResponse
    {
        $user = $this->userRepository->find($id);
        if (null === $user) {
            return apiError('Utilisateur introuvable.', 404);
        }
        if ($user === $admin) {
            return apiError('Tu ne peux pas suspendre ton propre compte.', 409);
        }
        if ($forbidden = $this->refuseIfOwner($user)) {
            return $forbidden;
        }
        if ($user->isBanned()) {
            return apiError('Ce compte est déjà suspendu.', 409);
        }

        $payload = json_decode($request->getContent(), true) ?? [];
        $reason = $payload['reason'] ?? null;
        if (null !== $reason && (!is_string($reason) || mb_strlen($reason) > 500)) {
            return apiError('reason doit être une chaîne de 500 caractères maximum, ou null.', 400);
        }

        $user->ban($reason);
        $this->entityManager->flush();

        return apiSuccess(data: $this->normalizeUserDetail($user), message: 'Compte suspendu : connexion refusée et sessions invalidées.');
    }

    #[IsGranted('PERMS_USERITIUM_USER_BAN')]
    #[Route('/useritium/admin/post-unban-user/{id}', name: 'useritium_admin_post_unban_user', methods: ['POST'])]
    public function postUnbanUser(int $id): JsonResponse
    {
        $user = $this->userRepository->find($id);
        if (null === $user) {
            return apiError('Utilisateur introuvable.', 404);
        }
        if ($forbidden = $this->refuseIfOwner($user)) {
            return $forbidden;
        }
        if (!$user->isBanned()) {
            return apiError('Ce compte n\'est pas suspendu.', 409);
        }

        $user->unban();
        $this->entityManager->flush();

        return apiSuccess(data: $this->normalizeUserDetail($user), message: 'Compte réactivé.');
    }

    #[IsGranted('PERMS_USERITIUM_USER_REVOKE')]
    #[Route('/useritium/admin/post-revoke-user-tokens/{id}', name: 'useritium_admin_post_revoke_user_tokens', methods: ['POST'])]
    public function postRevokeUserTokens(int $id): JsonResponse
    {
        $user = $this->userRepository->find($id);
        if (null === $user) {
            return apiError('Utilisateur introuvable.', 404);
        }
        if ($forbidden = $this->refuseIfOwner($user)) {
            return $forbidden;
        }

        $user->invalidateAllTokens();
        $this->entityManager->flush();

        return apiSuccess(data: $this->normalizeUserDetail($user), message: 'Toutes les sessions de ce compte sont invalidées.');
    }

    private function refuseIfOwner(User $target): ?JsonResponse
    {
        if (AccessLevel::OWNER === $target->getAccessLevel()) {
            return apiError('Impossible de modifier un owner via l\'API.', 403);
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeUser(User $user): array
    {
        /** @var array<string, mixed> $data */
        $data = $this->serializer->normalize($user, context: ['groups' => ['user:read', 'user:access', 'user:admin']]);

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeUserDetail(User $user): array
    {
        $data = $this->normalizeUser($user);

        $data['permissions'] = array_map(
            fn (UserPermission $userPermission): array => $this->normalizeUserPermission($userPermission),
            $user->getPermissions()->toArray(),
        );

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeUserPermission(UserPermission $userPermission): array
    {
        /** @var array<string, mixed> $data */
        $data = $this->serializer->normalize($userPermission, context: [
            'groups' => ['user_permission:read', 'permission:read', 'user:identifier'],
            AbstractObjectNormalizer::ENABLE_MAX_DEPTH => true,
        ]);

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeUserEmail(UserEmail $userEmail): array
    {
        /** @var array<string, mixed> $data */
        $data = $this->serializer->normalize($userEmail, context: ['groups' => ['user:read']]);

        return $data;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function withDebugToken(array $data, string $key, string $token): array
    {
        if ('prod' !== $this->environment) {
            $data[$key] = $token;
        }

        return $data;
    }
}
