<?php

namespace App\Repository;

use App\Entity\TaskProject;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TaskProject>
 */
class TaskProjectRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TaskProject::class);
    }

    /**
     * Projets du compte, actifs ou archivés, dans l'ordre du menu.
     *
     * @return list<TaskProject>
     */
    public function findForUser(User $user, bool $archived): array
    {
        /** @var list<TaskProject> $projects */
        $projects = $this->createQueryBuilder('p')
            ->andWhere('p.user = :user AND p.isArchived = :archived')
            ->setParameter('user', $user)->setParameter('archived', $archived)
            ->orderBy('p.position', 'ASC')->addOrderBy('p.id', 'ASC')
            ->getQuery()->getResult();

        return $projects;
    }

    /**
     * Prochaine position libre dans le menu des projets du compte.
     */
    public function nextPosition(User $user): int
    {
        $max = $this->createQueryBuilder('p')->select('MAX(p.position)')
            ->andWhere('p.user = :user')->setParameter('user', $user)
            ->getQuery()->getSingleScalarResult();

        return null === $max ? 0 : (int) $max + 1;
    }
}
