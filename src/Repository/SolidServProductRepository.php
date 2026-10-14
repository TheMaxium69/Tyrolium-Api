<?php

namespace App\Repository;

use App\Entity\SolidServProduct;
use App\Enum\OffreVisibility;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SolidServProduct>
 */
class SolidServProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SolidServProduct::class);
    }

    /**
     * @param array{visibility?: OffreVisibility|null, isActive?: bool|null, inStock?: bool|null, search?: string|null} $filters
     */
    public function filteredQueryBuilder(array $filters): QueryBuilder
    {
        $qb = $this->createQueryBuilder('p')->orderBy('p.position', 'ASC')->addOrderBy('p.id', 'ASC');

        if (isset($filters['visibility'])) {
            $qb->andWhere('p.visibility = :visibility')->setParameter('visibility', $filters['visibility']);
        }
        if (isset($filters['isActive'])) {
            $qb->andWhere('p.isActive = :isActive')->setParameter('isActive', $filters['isActive']);
        }
        if (isset($filters['inStock'])) {
            $qb->andWhere($filters['inStock'] ? 'p.stock IS NULL OR p.stock > 0' : 'p.stock = 0');
        }
        if (!empty($filters['search'])) {
            $qb->andWhere('p.tagName LIKE :search OR p.displayName LIKE :search')
                ->setParameter('search', '%'.addcslashes($filters['search'], '%_\\').'%');
        }

        return $qb;
    }
}
