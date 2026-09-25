<?php

namespace App\Security;

use App\Repository\ApiKeyRepository;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\AccessToken\AccessTokenHandlerInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

/**
 * Résout le header X-Api-Key en ApiKey — voir config/packages/security.yaml
 * (firewall api > access_token) et .doc/permissions.md. Symfony fournit tout
 * le reste du mécanisme (extraction du header, construction du Passport) via
 * son composant access_token natif, pas d'Authenticator custom à écrire.
 */
final class ApiKeyTokenHandler implements AccessTokenHandlerInterface
{
    public function __construct(
        private readonly ApiKeyRepository $apiKeyRepository,
    ) {
    }

    public function getUserBadgeFrom(#[\SensitiveParameter] string $accessToken): UserBadge
    {
        $apiKey = $this->apiKeyRepository->findValidByRawKey($accessToken);

        if (null === $apiKey) {
            throw new CustomUserMessageAuthenticationException('Clé API invalide, expirée ou révoquée.');
        }

        return new UserBadge($apiKey->getUserIdentifier(), static fn (): \App\Entity\ApiKey => $apiKey);
    }
}
