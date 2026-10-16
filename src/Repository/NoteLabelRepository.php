<?php

namespace App\Repository;

use App\Entity\NoteLabel;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<NoteLabel>
 */
class NoteLabelRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NoteLabel::class);
    }

    /**
     * Nombre de notes (hors corbeille) portant chaque étiquette du compte, indexé par id d'étiquette.
     *
     * @return array<int, int>
     */
    public function countNotesByLabel(User $user): array
    {
        /** @var list<array{id: int|string, total: int|string}> $rows */
        $rows = $this->createQueryBuilder('l')
            ->select('l.id AS id', 'COUNT(n.id) AS total')
            ->leftJoin('l.notes', 'n', 'WITH', 'n.trashedAt IS NULL')
            ->andWhere('l.user = :user')->setParameter('user', $user)
            ->groupBy('l.id')
            ->getQuery()
            ->getArrayResult();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) $row['id']] = (int) $row['total'];
        }

        return $counts;
    }
}
