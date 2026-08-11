<?php

namespace App\Repository;

use App\Entity\SolidServProduct;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
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
     * Retourne tous les produits publics et actifs (catalogue public solidserv.fr)
     *
     * @return SolidServProduct[]
     */
    public function findPublicCatalog(): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.isPublic = :isPublic')
            ->andWhere('p.isListed = :isListed')
            ->andWhere('p.isActive = :isActive')
            ->setParameter('isPublic', true)
            ->setParameter('isListed', true)
            ->setParameter('isActive', true)
            ->orderBy('p.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
