<?php

namespace App\Repository\Tyrolium;

use App\Entity\Tyrolium\WebSite;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<WebSite>
 */
class WebSiteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WebSite::class);
    }

    /**
     * @return WebSite[]
     */
    public function findByOwnerType(string $ownerType): array
    {
        return $this->createQueryBuilder('w')
            ->andWhere('w.ownerType = :ownerType')
            ->setParameter('ownerType', $ownerType)
            ->orderBy('w.domainName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return WebSite[]
     */
    public function findUpcomingRenewals(int $daysThreshold = 30): array
    {
        $limitDate = new \DateTimeImmutable("+{$daysThreshold} days");

        return $this->createQueryBuilder('w')
            ->andWhere('w.renewalAt IS NOT NULL')
            ->andWhere('w.renewalAt <= :limitDate')
            ->setParameter('limitDate', $limitDate)
            ->orderBy('w.renewalAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
