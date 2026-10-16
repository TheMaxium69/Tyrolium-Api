<?php

namespace App\Repository;

use App\Entity\Task;
use App\Entity\TaskColumn;
use App\Entity\TaskProject;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Task>
 */
class TaskRepository extends ServiceEntityRepository
{
    public const STATUSES = ['open', 'done', 'all'];
    public const DUES = ['today', 'overdue', 'upcoming', 'nodate'];
    public const SORTS = ['manual', 'dueDate', 'priority', 'createdAt'];

    /** « upcoming » = les 7 jours qui suivent aujourd'hui. */
    public const UPCOMING_DAYS = 7;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Task::class);
    }

    /**
     * Tâches racines (jamais les sous-tâches) d'un compte, filtrées.
     * `project` : 'none' (liste de base), 'any' (toutes) ou un id de projet.
     *
     * @param array{project?: string|int|null, status?: string|null, due?: string|null, priority?: int|null, labelId?: int|null, search?: string|null, sort?: string|null} $filters
     */
    public function rootsQueryBuilder(User $user, array $filters): QueryBuilder
    {
        $qb = $this->createQueryBuilder('n')
            ->andWhere('n.user = :user AND n.parent IS NULL')->setParameter('user', $user);

        $project = $filters['project'] ?? 'none';
        if ('none' === $project) {
            $qb->andWhere('n.project IS NULL');
        } elseif ('any' !== $project) {
            $qb->andWhere('n.project = :project')->setParameter('project', $project);
        }

        match ($filters['status'] ?? 'open') {
            'done' => $qb->andWhere('n.isDone = true'),
            'all' => null,
            default => $qb->andWhere('n.isDone = false'),
        };

        if (isset($filters['due'])) {
            $today = Task::today();
            $todayParam = [Types::DATE_IMMUTABLE];
            switch ($filters['due']) {
                case 'today':
                    $qb->andWhere('n.dueDate = :today')->setParameter('today', $today, ...$todayParam);
                    break;
                case 'overdue':
                    $qb->andWhere('n.dueDate < :today AND n.isDone = false')->setParameter('today', $today, ...$todayParam);
                    break;
                case 'upcoming':
                    $qb->andWhere('n.dueDate > :today AND n.dueDate <= :limit')
                        ->setParameter('today', $today, ...$todayParam)
                        ->setParameter('limit', $today->modify(sprintf('+%d days', self::UPCOMING_DAYS)), ...$todayParam);
                    break;
                case 'nodate':
                    $qb->andWhere('n.dueDate IS NULL');
                    break;
            }
        }

        if (isset($filters['priority'])) {
            $qb->andWhere('n.priority = :priority')->setParameter('priority', $filters['priority']);
        }
        if (isset($filters['labelId'])) {
            $qb->andWhere(':label MEMBER OF n.labels')->setParameter('label', $filters['labelId']);
        }
        if (!empty($filters['search'])) {
            $qb->andWhere('n.title LIKE :search OR n.description LIKE :search OR EXISTS (SELECT st.id FROM '.Task::class.' st WHERE st.parent = n AND st.title LIKE :search)')
                ->setParameter('search', '%'.addcslashes($filters['search'], '%_\\').'%');
        }

        match ($filters['sort'] ?? 'manual') {
            // Les tâches sans échéance viennent après celles qui en ont (MySQL trie NULL en premier).
            'dueDate' => $qb->addSelect('CASE WHEN n.dueDate IS NULL THEN 1 ELSE 0 END AS HIDDEN dueDateMissing')
                ->orderBy('dueDateMissing', 'ASC')->addOrderBy('n.dueDate', 'ASC')->addOrderBy('n.position', 'ASC'),
            'priority' => $qb->orderBy('n.priority', 'DESC')->addOrderBy('n.position', 'ASC'),
            // created_at a une précision d'une seconde : à égalité, la plus récente (id le plus grand) d'abord.
            'createdAt' => $qb->orderBy('n.createdAt', 'DESC')->addOrderBy('n.id', 'DESC'),
            default => $qb->orderBy('n.position', 'ASC'),
        };

        return $qb->addOrderBy('n.id', 'ASC');
    }

    /**
     * Cartes (tâches racines) d'un projet, dans l'ordre de leur colonne puis de leur position.
     *
     * @return list<Task>
     */
    public function findProjectCards(TaskProject $project): array
    {
        /** @var list<Task> $cards */
        $cards = $this->createQueryBuilder('t')
            ->andWhere('t.project = :project AND t.parent IS NULL')->setParameter('project', $project)
            ->orderBy('t.position', 'ASC')->addOrderBy('t.id', 'ASC')
            ->getQuery()->getResult();

        return $cards;
    }

    /**
     * Frères d'une tâche dans son conteneur, triés par position : les sous-tâches d'un
     * parent, les cartes d'une colonne, ou la liste de base (ni parent, ni projet).
     *
     * @return list<Task>
     */
    public function findSiblings(User $user, ?Task $parent, ?TaskColumn $column, ?Task $exclude = null): array
    {
        $qb = $this->createQueryBuilder('t')->andWhere('t.user = :user')->setParameter('user', $user);

        if (null !== $parent) {
            $qb->andWhere('t.parent = :parent')->setParameter('parent', $parent);
        } elseif (null !== $column) {
            $qb->andWhere('t.parent IS NULL AND t.column = :column')->setParameter('column', $column);
        } else {
            $qb->andWhere('t.parent IS NULL AND t.project IS NULL');
        }

        if (null !== $exclude && null !== $exclude->getId()) {
            $qb->andWhere('t.id != :exclude')->setParameter('exclude', $exclude->getId());
        }

        /** @var list<Task> $siblings */
        $siblings = $qb->orderBy('t.position', 'ASC')->addOrderBy('t.id', 'ASC')->getQuery()->getResult();

        return $siblings;
    }

    /**
     * Charge en deux requêtes (au lieu d'une par tâche) les étiquettes des tâches
     * racines données et leurs sous-tâches ; retourne ces dernières groupées par id de parent.
     *
     * @param list<Task> $roots
     *
     * @return array<int, list<Task>>
     */
    public function loadRelations(array $roots): array
    {
        if ([] === $roots) {
            return [];
        }
        $ids = array_map(static fn (Task $task): ?int => $task->getId(), $roots);

        $this->createQueryBuilder('t')->select('t', 'l')->leftJoin('t.labels', 'l')
            ->andWhere('t.id IN (:ids)')->setParameter('ids', $ids)
            ->getQuery()->getResult();

        /** @var list<Task> $subtasks */
        $subtasks = $this->createQueryBuilder('s')
            ->andWhere('s.parent IN (:ids)')->setParameter('ids', $ids)
            ->orderBy('s.position', 'ASC')->addOrderBy('s.id', 'ASC')
            ->getQuery()->getResult();

        $grouped = [];
        foreach ($subtasks as $subtask) {
            $parentId = $subtask->getParent()?->getId();
            if (null !== $parentId) {
                $grouped[$parentId][] = $subtask;
            }
        }

        return $grouped;
    }

    /**
     * Nombre de tâches racines ouvertes / terminées par projet du compte.
     *
     * @return array<int, array{open: int, done: int}> indexé par id de projet
     */
    public function countRootsByProject(User $user): array
    {
        /** @var list<array{pid: int|string, done: bool|int|string, total: int|string}> $rows */
        $rows = $this->createQueryBuilder('t')
            ->select('IDENTITY(t.project) AS pid', 't.isDone AS done', 'COUNT(t.id) AS total')
            ->andWhere('t.user = :user AND t.parent IS NULL AND t.project IS NOT NULL')->setParameter('user', $user)
            ->groupBy('t.project', 't.isDone')
            ->getQuery()->getArrayResult();

        $counts = [];
        foreach ($rows as $row) {
            $projectId = (int) $row['pid'];
            $counts[$projectId] ??= ['open' => 0, 'done' => 0];
            $counts[$projectId][$row['done'] ? 'done' : 'open'] = (int) $row['total'];
        }

        return $counts;
    }

    /**
     * Nombre de cartes par colonne d'un projet, indexé par id de colonne.
     *
     * @return array<int, int>
     */
    public function countCardsByColumn(TaskProject $project): array
    {
        /** @var list<array{cid: int|string, total: int|string}> $rows */
        $rows = $this->createQueryBuilder('t')
            ->select('IDENTITY(t.column) AS cid', 'COUNT(t.id) AS total')
            ->andWhere('t.project = :project AND t.parent IS NULL AND t.column IS NOT NULL')->setParameter('project', $project)
            ->groupBy('t.column')
            ->getQuery()->getArrayResult();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) $row['cid']] = (int) $row['total'];
        }

        return $counts;
    }

    /**
     * Compteurs de la barre latérale : liste de base (ouvertes / terminées) et échéances
     * (aujourd'hui, en retard, 7 jours à venir) sur toutes les tâches racines ouvertes.
     *
     * @return array{inbox: int, completed: int, today: int, overdue: int, upcoming: int}
     */
    public function overview(User $user): array
    {
        $today = Task::today();

        $row = $this->getEntityManager()->getConnection()->fetchAssociative(
            'SELECT
                COALESCE(SUM(is_done = 0 AND project_id IS NULL), 0) AS inbox,
                COALESCE(SUM(is_done = 1 AND project_id IS NULL), 0) AS completed,
                COALESCE(SUM(is_done = 0 AND due_date = :today), 0) AS today,
                COALESCE(SUM(is_done = 0 AND due_date < :today), 0) AS overdue,
                COALESCE(SUM(is_done = 0 AND due_date > :today AND due_date <= :limit), 0) AS upcoming
             FROM task WHERE user_id = :user AND parent_id IS NULL',
            [
                'user' => $user->getId(),
                'today' => $today->format('Y-m-d'),
                'limit' => $today->modify(sprintf('+%d days', self::UPCOMING_DAYS))->format('Y-m-d'),
            ],
            ['user' => ParameterType::INTEGER],
        );
        $row = false === $row ? [] : $row;

        return [
            'inbox' => (int) ($row['inbox'] ?? 0),
            'completed' => (int) ($row['completed'] ?? 0),
            'today' => (int) ($row['today'] ?? 0),
            'overdue' => (int) ($row['overdue'] ?? 0),
            'upcoming' => (int) ($row['upcoming'] ?? 0),
        ];
    }
}
