<?php

namespace App\Repository;

use App\Entity\AnalyticsInput;
use App\Entity\AnalyticsProject;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
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
     * @param array{project?: AnalyticsProject|null, ip?: string|null, pageName?: string|null, uri?: string|null} $filters
     */
    public function filteredQueryBuilder(array $filters): QueryBuilder
    {
        $qb = $this->createQueryBuilder('i')->orderBy('i.createdAt', 'DESC')->addOrderBy('i.id', 'DESC');

        if (!empty($filters['project'])) {
            $qb->andWhere('i.project = :project')->setParameter('project', $filters['project']);
        }
        if (!empty($filters['ip'])) {
            $qb->andWhere('i.ip = :ip')->setParameter('ip', $filters['ip']);
        }
        if (!empty($filters['pageName'])) {
            $qb->andWhere('i.pageName = :pageName')->setParameter('pageName', $filters['pageName']);
        }
        if (!empty($filters['uri'])) {
            $qb->andWhere('i.uri = :uri')->setParameter('uri', $filters['uri']);
        }

        return $qb;
    }

    /**
     * Totaux calculés directement en base : jamais de chargement des visites en mémoire.
     *
     * @return array{summary: array<string, int>, topPages: list<array<string, mixed>>, visitsPerDay: list<array<string, mixed>>, projects: list<array<string, mixed>>}
     */
    public function computeStats(?AnalyticsProject $project, ?\DateTimeImmutable $from, ?\DateTimeImmutable $to): array
    {
        $connection = $this->getEntityManager()->getConnection();

        $where = [];
        $params = [];
        if (null !== $project) {
            $where[] = 'i.project_id = :projectId';
            $params['projectId'] = $project->getId();
        }
        if (null !== $from) {
            $where[] = 'i.created_at >= :from';
            $params['from'] = $from->format('Y-m-d 00:00:00');
        }
        if (null !== $to) {
            $where[] = 'i.created_at < :to';
            $params['to'] = $to->modify('+1 day')->format('Y-m-d 00:00:00');
        }
        $whereSql = [] === $where ? '1=1' : implode(' AND ', $where);

        $summary = $connection->fetchAssociative(
            "SELECT COUNT(*) AS visits, COUNT(DISTINCT i.ip) AS uniqueVisitors, COALESCE(SUM(i.is_login), 0) AS loggedInVisits
             FROM analytics_input i WHERE $whereSql",
            $params,
        );

        $topPages = $connection->fetchAllAssociative(
            "SELECT i.page_name AS pageName, i.uri AS uri, COUNT(*) AS visits, COUNT(DISTINCT i.ip) AS uniqueVisitors
             FROM analytics_input i WHERE $whereSql
             GROUP BY i.page_name, i.uri ORDER BY visits DESC LIMIT 10",
            $params,
        );

        $visitsPerDay = $connection->fetchAllAssociative(
            "SELECT DATE(i.created_at) AS day, COUNT(*) AS visits, COUNT(DISTINCT i.ip) AS uniqueVisitors
             FROM analytics_input i WHERE $whereSql
             GROUP BY DATE(i.created_at) ORDER BY day ASC",
            $params,
        );

        $projects = [];
        if (null === $project) {
            $dateWhere = [] === $where ? '1=1' : implode(' AND ', $where);
            $projects = $connection->fetchAllAssociative(
                "SELECT p.id AS id, p.tag AS tag, COUNT(i.id) AS visits, COUNT(DISTINCT i.ip) AS uniqueVisitors
                 FROM analytics_project p
                 LEFT JOIN analytics_input i ON i.project_id = p.id AND $dateWhere
                 GROUP BY p.id, p.tag ORDER BY visits DESC",
                $params,
            );
        }

        return [
            'summary' => [
                'visits' => (int) $summary['visits'],
                'uniqueVisitors' => (int) $summary['uniqueVisitors'],
                'loggedInVisits' => (int) $summary['loggedInVisits'],
            ],
            'topPages' => $topPages,
            'visitsPerDay' => $visitsPerDay,
            'projects' => $projects,
        ];
    }
}
