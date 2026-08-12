<?php

namespace App\Repository\Tyrolium;

use App\Entity\Tyrolium\AnalyticsInput;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AnalyticsInput>
 */
class AnalyticsInputRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AnalyticsInput::class);
    }

    /**
     * @param array<string, mixed> $filters
     * @return AnalyticsInput[]
     */
    public function findByAdvancedFilters(array $filters): array
    {
        $qb = $this->createQueryBuilder('i');

        if (!empty($filters['project'])) {
            $qb->andWhere('i.project = :project')
                ->setParameter('project', $filters['project']);
        }

        if (!empty($filters['ip'])) {
            $qb->andWhere('i.ip = :ip')
                ->setParameter('ip', $filters['ip']);
        }

        if (!empty($filters['pageName'])) {
            $qb->andWhere('i.pageName = :pageName')
                ->setParameter('pageName', $filters['pageName']);
        }

        if (!empty($filters['uri'])) {
            $qb->andWhere('i.uri = :uri')
                ->setParameter('uri', $filters['uri']);
        }

        return $qb->getQuery()->getResult();
    }
}
