<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Bloque le login tant que l'email default du compte n'est pas vérifié — voir
 * la règle anti-lockout discutée dans .doc/useritium-auth.md : un compte doit
 * toujours avoir un email default vérifié, sinon il est inutilisable.
 */
class UserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }

        if (!$user->hasVerifiedDefaultEmail()) {
            throw new CustomUserMessageAccountStatusException('Ton email par défaut n\'est pas encore vérifié. Vérifie ta boîte mail avant de te connecter.');
        }
    }

    public function checkPostAuth(UserInterface $user, ?TokenInterface $token = null): void
    {
        // Rien à vérifier après authentification pour l'instant.
    }
}
