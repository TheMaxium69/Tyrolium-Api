<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;

/**
 * Ne jamais révéler *pourquoi* le login a échoué (mauvais username/email vs
 * mauvais mot de passe) — sinon c'est un oracle d'énumération de comptes.
 * Seule exception volontaire : le cas "email default non vérifié"
 * (CustomUserMessageAccountStatusException, levée par UserChecker), un message
 * actionnable pour l'utilisateur, pas une fuite exploitable côté password.
 */
class AuthenticationFailureHandler implements AuthenticationFailureHandlerInterface
{
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): JsonResponse
    {
        if ($exception instanceof CustomUserMessageAccountStatusException) {
            return apiError($exception->getMessageKey(), 401);
        }

        return apiError('Identifiants invalides.', 401);
    }
}
