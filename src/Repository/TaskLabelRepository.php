<?php

namespace App\Repository;

use App\Entity\TaskLabel;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TaskLabel>
 */
class TaskLabelRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TaskLabel::class);
    }

    /**
     * Nombre de tâches racines ouvertes portant chaque étiquette du compte, indexé par id d'étiquette.
     *
     * @return array<int, int>
     */
    public function countOpenTasksByLabel(User $user): array
    {
        /** @var list<array{id: int|string, total: int|string}> $rows */
        $rows = $this->createQueryBuilder('l')
            ->select('l.id AS id', 'COUNT(t.id) AS total')
            ->leftJoin('l.tasks', 't', 'WITH', 't.isDone = false AND t.parent IS NULL')
            ->andWhere('l.user = :user')->setParameter('user', $user)
            ->groupBy('l.id')
            ->getQuery()->getArrayResult();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) $row['id']] = (int) $row['total'];
        }

        return $counts;
    }
}
