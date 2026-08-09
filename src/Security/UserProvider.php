<?php

namespace App\Security;

use App\Entity\User;
use App\Repository\UserEmailRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * Résout un identifiant de login qui peut être soit le username, soit l'email
 * default de l'utilisateur (voir .doc/useritium-auth.md). Un email secondaire
 * non-default n'est volontairement jamais accepté ici.
 *
 * @implements UserProviderInterface<User>
 */
class UserProvider implements UserProviderInterface, PasswordUpgraderInterface
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly UserEmailRepository $userEmailRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return User
     */
    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        $user = $this->userRepository->findOneBy(['username' => $identifier]);

        if (null === $user) {
            $userEmail = $this->userEmailRepository->findOneDefaultVerifiedByEmail($identifier);
            $user = $userEmail?->getUser();
        }

        if (null === $user) {
            throw new UserNotFoundException(sprintf('Aucun utilisateur trouvé pour l\'identifiant "%s".', $identifier));
        }

        return $user;
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', get_debug_type($user)));
        }

        $freshUser = $this->userRepository->find($user->getId());

        if (null === $freshUser) {
            throw new UserNotFoundException(sprintf('User with id "%s" not found.', $user->getId()));
        }

        return $freshUser;
    }

    public function supportsClass(string $class): bool
    {
        return User::class === $class || is_subclass_of($class, User::class);
    }

    public function upgradePassword(PasswordAuthenticatedUserInterface|UserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            return;
        }

        $user->setPassword($newHashedPassword);
        $this->entityManager->flush();
    }
}
