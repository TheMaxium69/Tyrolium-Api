<?php

namespace App\Repository;

use App\Entity\TaskColumn;
use App\Entity\TaskProject;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TaskColumn>
 */
class TaskColumnRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TaskColumn::class);
    }

    /**
     * Colonnes d'un projet, de gauche à droite.
     *
     * @return list<TaskColumn>
     */
    public function findForProject(TaskProject $project): array
    {
        /** @var list<TaskColumn> $columns */
        $columns = $this->createQueryBuilder('c')
            ->andWhere('c.project = :project')->setParameter('project', $project)
            ->orderBy('c.position', 'ASC')->addOrderBy('c.id', 'ASC')
            ->getQuery()->getResult();

        return $columns;
    }

    /**
     * Colonne par id, seulement si son projet appartient à ce compte.
     */
    public function findOneOwned(int $id, User $user): ?TaskColumn
    {
        $column = $this->createQueryBuilder('c')
            ->innerJoin('c.project', 'p')
            ->andWhere('c.id = :id AND p.user = :user')
            ->setParameter('id', $id)->setParameter('user', $user)
            ->getQuery()->getOneOrNullResult();

        return $column instanceof TaskColumn ? $column : null;
    }
}
