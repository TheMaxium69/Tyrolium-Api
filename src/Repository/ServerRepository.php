<?php

namespace App\Repository;

use App\Entity\Server;
use App\Enum\ServerStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Server>
 */
class ServerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Server::class);
    }

    /**
     * @param array{typeId?: int|null, status?: ServerStatus|null, project?: string|null, productId?: int|null, userId?: int|null, hasClient?: bool|null, search?: string|null} $filters
     */
    public function filteredQueryBuilder(array $filters): QueryBuilder
    {
        $qb = $this->createQueryBuilder('s')->orderBy('s.name', 'ASC');

        if (isset($filters['typeId'])) {
            $qb->andWhere('IDENTITY(s.type) = :typeId')->setParameter('typeId', $filters['typeId']);
        }
        if (isset($filters['status'])) {
            $qb->andWhere('s.status = :status')->setParameter('status', $filters['status']);
        }
        if (!empty($filters['project'])) {
            $qb->andWhere('s.project = :project')->setParameter('project', $filters['project']);
        }
        if (isset($filters['productId'])) {
            $qb->andWhere('IDENTITY(s.product) = :productId')->setParameter('productId', $filters['productId']);
        }
        if (isset($filters['userId'])) {
            $qb->andWhere('IDENTITY(s.user) = :userId')->setParameter('userId', $filters['userId']);
        }
        if (isset($filters['hasClient'])) {
            $qb->andWhere($filters['hasClient'] ? 's.user IS NOT NULL OR s.clientName IS NOT NULL' : 's.user IS NULL AND s.clientName IS NULL');
        }
        if (!empty($filters['search'])) {
            $qb->andWhere('s.name LIKE :search OR s.alias LIKE :search OR s.description LIKE :search OR s.location LIKE :search OR s.hostname LIKE :search OR s.project LIKE :search OR s.clientName LIKE :search')
                ->setParameter('search', '%'.addcslashes($filters['search'], '%_\\').'%');
        }

        return $qb;
    }

    /**
     * Vue d'ensemble du parc, calculée en base.
     *
     * @return array{total: int, withClient: int, internal: int, byStatus: array<string, int>, byType: list<array<string, mixed>>, byProject: list<array<string, mixed>>}
     */
    public function computeOverview(): array
    {
        $connection = $this->getEntityManager()->getConnection();

        $totals = $connection->fetchAssociative(
            'SELECT COUNT(*) AS total, COALESCE(SUM(user_id IS NOT NULL OR client_name IS NOT NULL), 0) AS withClient FROM server',
        );

        $byStatus = [];
        foreach (ServerStatus::cases() as $status) {
            $byStatus[$status->value] = 0;
        }
        foreach ($connection->fetchAllAssociative('SELECT status, COUNT(*) AS n FROM server GROUP BY status') as $row) {
            $byStatus[$row['status']] = (int) $row['n'];
        }

        $byType = $connection->fetchAllAssociative(
            'SELECT t.id AS id, t.abbreviation AS abbreviation, t.name AS name, COUNT(s.id) AS servers
             FROM server_type t LEFT JOIN server s ON s.type_id = t.id
             GROUP BY t.id, t.abbreviation, t.name ORDER BY t.id',
        );
        foreach ($byType as &$row) {
            $row['id'] = (int) $row['id'];
            $row['servers'] = (int) $row['servers'];
        }
        unset($row);

        $byProject = $connection->fetchAllAssociative(
            'SELECT project, COUNT(*) AS servers FROM server WHERE project IS NOT NULL GROUP BY project ORDER BY servers DESC, project ASC',
        );
        foreach ($byProject as &$row) {
            $row['servers'] = (int) $row['servers'];
        }
        unset($row);

        $total = (int) $totals['total'];
        $withClient = (int) $totals['withClient'];

        return [
            'total' => $total,
            'withClient' => $withClient,
            'internal' => $total - $withClient,
            'byStatus' => $byStatus,
            'byType' => $byType,
            'byProject' => $byProject,
        ];
    }
}
