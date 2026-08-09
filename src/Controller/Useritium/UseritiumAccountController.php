<?php

namespace App\Controller\Useritium;

use App\Entity\User;
use App\Entity\UserEmail;
use App\Repository\UserEmailRepository;
use App\Repository\UserRepository;
use App\Security\UserProvider;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Login de base (username ou email default) + gestion des emails multiples —
 * voir .doc/useritium-auth.md pour le design complet.
 *
 * Le vrai envoi d'email (token de vérification / de reset) n'est pas encore
 * câblé (symfony/mailer pas installé, provider SMTP pas choisi) : en dev/test,
 * le token est exposé directement dans la réponse pour pouvoir tester le flow
 * de bout en bout. À retirer le jour où l'envoi réel est en place.
 */
class UseritiumAccountController extends AbstractController
{
    private const MAX_EMAILS_PER_USER = 5;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly UserEmailRepository $userEmailRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly ValidatorInterface $validator,
        private readonly UserProvider $userProvider,
        #[Autowire('%kernel.environment%')]
        private readonly string $environment,
    ) {
    }

    #[Route('/useritium/account/post-register', name: 'useritium_account_post_register', methods: ['POST'])]
    public function postRegister(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true) ?? [];

        $user = new User();
        if (isset($payload['username'])) {
            $user->setUsername($payload['username']);
        }
        if (isset($payload['password'])) {
            $user->setPassword($payload['password']);
        }

        $violations = $this->validator->validate($user);

        $email = is_string($payload['email'] ?? null) ? $payload['email'] : '';
        $emailViolations = $this->validator->validate($email, [
            new Assert\NotBlank(message: "L'email est obligatoire."),
            new Assert\Email(message: "L'adresse email n'est pas valide."),
        ]);
        foreach ($emailViolations as $violation) {
            $violations->add($violation);
        }

        if (count($violations) > 0) {
            return apiValidationError($violations, 'Données d\'inscription invalides.');
        }

        if (null !== $this->userEmailRepository->findOneBy(['email' => $email, 'isDefault' => true])) {
            return apiError('Cet email est déjà utilisé comme email par défaut sur un autre compte.', 409);
        }

        $user->setPassword($this->passwordHasher->hashPassword($user, $payload['password']));

        $userEmail = new UserEmail();
        $userEmail->setEmail($email);
        $userEmail->setIsDefault(true);
        $verificationToken = $userEmail->generateVerificationToken();
        $user->addEmail($userEmail);

        try {
            $this->entityManager->persist($user);
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            return apiError('Ce nom d\'utilisateur ou cet email est déjà utilisé.', 409);
        }

        return apiSuccess(
            data: $this->withDebugToken(['username' => $user->getUsername()], 'verificationToken', $verificationToken),
            message: 'Compte créé. Vérifie ton email avant de pouvoir te connecter.',
            code: 201,
        );
    }

    /**
     * Jamais exécutée en pratique : interceptée par le firewall (json_login,
     * check_path) avant d'atteindre le controller. La route doit exister pour
     * que check_path puisse la résoudre — voir security.yaml.
     */
    #[Route('/useritium/account/post-login', name: 'useritium_account_post_login', methods: ['POST'])]
    public function postLogin(): JsonResponse
    {
        throw new \LogicException('Cette route est interceptée par le firewall avant d\'atteindre le controller.');
    }

    #[Route('/useritium/account/post-verify-email', name: 'useritium_account_post_verify_email', methods: ['POST'])]
    public function postVerifyEmail(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true) ?? [];
        $token = is_string($payload['token'] ?? null) ? $payload['token'] : null;

        if (null === $token) {
            return apiError('Token manquant.', 400);
        }

        $userEmail = $this->userEmailRepository->findOneByVerificationToken($token);

        if (null === $userEmail || !$userEmail->isVerificationTokenValid()) {
            return apiError('Ce lien de vérification est invalide ou expiré.', 400);
        }

        $userEmail->markAsVerified();
        $this->entityManager->flush();

        return apiSuccess(message: 'Email vérifié avec succès.');
    }

    #[Route('/useritium/account/post-forgot-password', name: 'useritium_account_post_forgot_password', methods: ['POST'])]
    public function postForgotPassword(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true) ?? [];
        $identifier = is_string($payload['identifier'] ?? null) ? $payload['identifier'] : null;

        // Message générique dans tous les cas, que le compte existe ou non —
        // sinon cet endpoint devient un oracle d'énumération de comptes.
        $genericMessage = 'Si un compte existe pour cet identifiant, un email de réinitialisation a été envoyé à son adresse par défaut.';

        if (null === $identifier) {
            return apiError('Identifiant manquant.', 400);
        }

        try {
            $user = $this->userProvider->loadUserByIdentifier($identifier);
        } catch (UserNotFoundException) {
            return apiSuccess(message: $genericMessage);
        }

        $resetToken = $user->generateResetToken();
        $this->entityManager->flush();

        return apiSuccess(
            data: $this->withDebugToken([], 'resetToken', $resetToken),
            message: $genericMessage,
        );
    }

    #[Route('/useritium/account/post-reset-password', name: 'useritium_account_post_reset_password', methods: ['POST'])]
    public function postResetPassword(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true) ?? [];
        $token = is_string($payload['token'] ?? null) ? $payload['token'] : null;
        $newPassword = is_string($payload['newPassword'] ?? null) ? $payload['newPassword'] : null;

        if (null === $token || null === $newPassword) {
            return apiError('Token et nouveau mot de passe requis.', 400);
        }

        $user = $this->userRepository->findOneBy(['resetToken' => $token]);

        if (null === $user || !$user->isResetTokenValid()) {
            return apiError('Ce lien de réinitialisation est invalide ou expiré.', 400);
        }

        $violations = $this->validator->validate($newPassword, [
            new Assert\NotBlank(message: 'Le mot de passe est obligatoire.'),
            new Assert\Length(min: 8, minMessage: 'Le mot de passe doit contenir au moins 8 caractères.'),
        ]);

        if (count($violations) > 0) {
            return apiValidationError($violations, 'Mot de passe invalide.');
        }

        $user->setPassword($this->passwordHasher->hashPassword($user, $newPassword));
        $user->clearResetToken();
        $this->entityManager->flush();

        return apiSuccess(message: 'Mot de passe réinitialisé avec succès.');
    }

    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[Route('/useritium/account/post-add-email', name: 'useritium_account_post_add_email', methods: ['POST'])]
    public function postAddEmail(Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $payload = json_decode($request->getContent(), true) ?? [];
        $email = is_string($payload['email'] ?? null) ? $payload['email'] : '';

        $violations = $this->validator->validate($email, [
            new Assert\NotBlank(message: "L'email est obligatoire."),
            new Assert\Email(message: "L'adresse email n'est pas valide."),
        ]);

        if (count($violations) > 0) {
            return apiValidationError($violations, 'Email invalide.');
        }

        if ($user->getEmails()->count() >= self::MAX_EMAILS_PER_USER) {
            return apiError(sprintf('Un compte ne peut pas avoir plus de %d emails.', self::MAX_EMAILS_PER_USER), 409);
        }

        foreach ($user->getEmails() as $existingEmail) {
            if ($existingEmail->getEmail() === $email) {
                return apiError('Cet email est déjà associé à ton compte.', 409);
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
            data: $this->withDebugToken(['id' => $userEmail->getId(), 'email' => $userEmail->getEmail()], 'verificationToken', $verificationToken),
            message: 'Email ajouté. Vérifie-le avant de pouvoir le passer par défaut.',
            code: 201,
        );
    }

    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[Route('/useritium/account/post-set-default-email', name: 'useritium_account_post_set_default_email', methods: ['POST'])]
    public function postSetDefaultEmail(Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $payload = json_decode($request->getContent(), true) ?? [];
        $emailId = $payload['emailId'] ?? null;

        $target = null !== $emailId ? $this->userEmailRepository->find($emailId) : null;

        if (null === $target || $target->getUser() !== $user) {
            return apiError('Email introuvable sur ce compte.', 404);
        }

        if (!$target->isVerified()) {
            return apiError('Cet email doit être vérifié avant de pouvoir devenir l\'email par défaut.', 400);
        }

        if ($target->isDefault()) {
            return apiSuccess(message: 'Cet email est déjà ton email par défaut.');
        }

        // Deux flush séparés, volontairement : MySQL vérifie la contrainte
        // unique de suite (pas de contrainte différée comme Postgres), donc
        // l'ancien default doit être désactivé AVANT que le nouveau soit posé.
        $currentDefault = $user->getDefaultEmail();
        if (null !== $currentDefault) {
            $currentDefault->setIsDefault(false);
            $this->entityManager->flush();
        }

        $target->setIsDefault(true);
        $this->entityManager->flush();

        return apiSuccess(message: 'Email par défaut mis à jour.');
    }

    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[Route('/useritium/account/delete-email/{id}', name: 'useritium_account_delete_email', methods: ['DELETE'])]
    public function deleteEmail(int $id, #[CurrentUser] User $user): JsonResponse
    {
        $target = $this->userEmailRepository->find($id);

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
