<?php

namespace App\Security;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\CacheableVoterInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

/**
 * ROLE_OWNER bypasse absolument tout, quel que soit l'attribut #[IsGranted]
 * vérifié — décision de Maxime, 25/09/2026 : c'est la "backdoor" volontaire,
 * réservée exclusivement à lui, jamais accordée via API/CLI (voir
 * .doc/permissions.md et App\Enum\AccessLevel).
 *
 * Nécessaire car RoleVoter (vendor/symfony/security-core/Authorization/Voter/RoleVoter.php)
 * exige une correspondance EXACTE entre l'attribut vérifié et un rôle présent
 * dans getRoles() — pas de hiérarchie implicite. Sans ce voter, ROLE_OWNER
 * serait refusé sur #[IsGranted('ROLE_SOLIDSERV_MANAGE')] tant qu'il n'a pas
 * *littéralement* ce rôle précis.
 */
final class OwnerBypassVoter implements CacheableVoterInterface
{
    public function vote(TokenInterface $token, mixed $subject, array $attributes, ?Vote $vote = null): int
    {
        if (!in_array('ROLE_OWNER', $token->getRoleNames(), true)) {
            return VoterInterface::ACCESS_ABSTAIN;
        }

        return VoterInterface::ACCESS_GRANTED;
    }

    public function supportsAttribute(string $attribute): bool
    {
        return true;
    }

    public function supportsType(string $subjectType): bool
    {
        return true;
    }
}
