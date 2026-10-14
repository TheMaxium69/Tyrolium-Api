<?php

namespace App\Repository;

use App\Entity\Website;
use App\Enum\Filiale;
use App\Enum\WebsiteOwnerType;
use App\Enum\WebsiteStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Website>
 */
class WebsiteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Website::class);
    }

    /**
     * @param array{ownerType?: WebsiteOwnerType|null, filiale?: Filiale|null, status?: WebsiteStatus|null, prestationId?: int|null, userId?: int|null, serverId?: int|null, search?: string|null} $filters
     */
    public function filteredQueryBuilder(array $filters): QueryBuilder
    {
        $qb = $this->createQueryBuilder('w')->orderBy('w.domainName', 'ASC');

        if (!empty($filters['ownerType'])) {
            $qb->andWhere('w.ownerType = :ownerType')->setParameter('ownerType', $filters['ownerType']);
        }
        if (!empty($filters['filiale'])) {
            $qb->andWhere('w.filiale = :filiale')->setParameter('filiale', $filters['filiale']);
        }
        if (!empty($filters['status'])) {
            $qb->andWhere('w.status = :status')->setParameter('status', $filters['status']);
        }
        if (!empty($filters['prestationId'])) {
            $qb->andWhere('IDENTITY(w.prestation) = :prestationId')->setParameter('prestationId', $filters['prestationId']);
        }
        if (!empty($filters['userId'])) {
            $qb->andWhere('IDENTITY(w.user) = :userId')->setParameter('userId', $filters['userId']);
        }
        if (!empty($filters['serverId'])) {
            $qb->andWhere('IDENTITY(w.server) = :serverId')->setParameter('serverId', $filters['serverId']);
        }
        if (!empty($filters['search'])) {
            $qb->andWhere('w.domainName LIKE :search OR w.name LIKE :search OR w.clientName LIKE :search')
                ->setParameter('search', '%'.addcslashes($filters['search'], '%_\\').'%');
        }

        return $qb;
    }

    /**
     * Domaines dont l'échéance tombe dans les $days prochains jours (ou est déjà passée), la plus proche d'abord.
     */
    public function expiringQueryBuilder(int $days): QueryBuilder
    {
        return $this->expiringOn('domainExpiresAt', $days);
    }

    /**
     * Certificats SSL dont l'échéance tombe dans les $days prochains jours (ou est déjà passée), le plus proche d'abord.
     */
    public function expiringSslQueryBuilder(int $days): QueryBuilder
    {
        return $this->expiringOn('sslExpiresAt', $days);
    }

    private function expiringOn(string $field, int $days): QueryBuilder
    {
        return $this->createQueryBuilder('w')
            ->andWhere('w.'.$field.' IS NOT NULL')
            ->andWhere('w.'.$field.' <= :limit')
            ->andWhere('w.status != :archived')
            ->setParameter('limit', (new \DateTimeImmutable('today'))->modify('+'.$days.' days')->setTime(23, 59, 59))
            ->setParameter('archived', WebsiteStatus::ARCHIVED)
            ->orderBy('w.'.$field, 'ASC');
    }
}
