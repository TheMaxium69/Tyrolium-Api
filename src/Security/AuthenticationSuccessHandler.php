<?php

namespace App\Security;

use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;

/**
 * Remplace le handler par défaut du bundle Lexik (qui renvoie {"token": "..."})
 * pour respecter l'enveloppe unifiée de l'API — voir .doc/api-response-format.md.
 */
class AuthenticationSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    public function __construct(
        private readonly JWTTokenManagerInterface $jwtManager,
    ) {
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): JsonResponse
    {
        $user = $token->getUser();

        if (!$user instanceof UserInterface) {
            return apiError('Authentification invalide.', 401);
        }

        $jwt = $this->jwtManager->create($user);

        return apiSuccess(
            data: ['token' => $jwt],
            message: 'Connexion réussie.',
        );
    }
}
