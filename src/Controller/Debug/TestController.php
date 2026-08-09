<?php

namespace App\Controller\Debug;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class TestController extends AbstractController
{
    #[Route('/debug/test/get-ping', name: 'debug_test_get_ping', methods: ['GET'])]
    public function getPing(): JsonResponse
    {
        return apiSuccess(
            data: [
                'phpVersion' => PHP_VERSION,
                'symfonyVersion' => \Symfony\Component\HttpKernel\Kernel::VERSION,
            ],
            message: 'Premier coucou à mon API Tyrolium ! 👋'
        );
    }

    #[Route('/debug/test/post-validation', name: 'debug_test_post_validation', methods: ['POST'])]
    public function postValidation(
        Request $request,
        ValidatorInterface $validator,
        UserPasswordHasherInterface $passwordHasher,
    ): JsonResponse {
        $payload = json_decode($request->getContent(), true) ?? [];

        $user = new User();
        if (isset($payload['username'])) {
            $user->setUsername($payload['username']);
        }
        if (isset($payload['password'])) {
            $user->setPassword($payload['password']);
        }

        $violations = $validator->validate($user);

        if (count($violations) > 0) {
            return apiValidationError($violations, 'Données utilisateur invalides.');
        }

        // Exemple : hash du mot de passe en clair, puis vérification, via le hasher
        // configuré dans security.yaml ("auto" — sodium/argon2/bcrypt selon dispo).
        // C'est UserPasswordHasherInterface qui rend ça possible : il ne fonctionne
        // que parce que User implémente PasswordAuthenticatedUserInterface.
        $plainPassword = $user->getPassword();
        if (null === $plainPassword) {
            // Unreachable in practice: Assert\NotBlank on User::$password already
            // rejected an empty value above. PHPStan can't see through that, so this
            // guard is what narrows string|null down to string for the hasher calls.
            return apiError('Mot de passe manquant.', 422);
        }

        $hashedPassword = $passwordHasher->hashPassword($user, $plainPassword);
        $user->setPassword($hashedPassword);

        $isValid = $passwordHasher->isPasswordValid($user, $plainPassword);
        $isValidWithWrongPassword = $passwordHasher->isPasswordValid($user, 'mauvais-mot-de-passe');

        return apiSuccess(
            data: [
                'username' => $user->getUsername(),
                'plainPassword' => $plainPassword,
                'hashedPassword' => $hashedPassword,
                'verificationWithCorrectPassword' => $isValid,
                'verificationWithWrongPassword' => $isValidWithWrongPassword,
            ],
            message: 'Validation réussie ! L\'utilisateur respecte toutes les contraintes Assert.'
        );
    }
}
