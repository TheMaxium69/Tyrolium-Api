<?php

namespace App\Repository;

use App\Entity\Note;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Note>
 */
class NoteRepository extends ServiceEntityRepository
{
    public const VIEW_ACTIVE = 'active';
    public const VIEW_ARCHIVED = 'archived';
    public const VIEW_TRASH = 'trash';
    public const VIEWS = [self::VIEW_ACTIVE, self::VIEW_ARCHIVED, self::VIEW_TRASH];

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Note::class);
    }

    /**
     * Notes d'un compte pour une vue (active, archivée, corbeille), avec filtres
     * optionnels. Tri : épinglées d'abord puis dernière modification (corbeille :
     * la plus récemment supprimée d'abord).
     *
     * @param array{labelId?: int|null, search?: string|null, color?: string|null, type?: string|null} $filters
     */
    public function viewQueryBuilder(User $user, string $view, array $filters = []): QueryBuilder
    {
        $qb = $this->createQueryBuilder('n')
            ->andWhere('n.user = :user')->setParameter('user', $user);

        match ($view) {
            self::VIEW_ARCHIVED => $qb->andWhere('n.trashedAt IS NULL AND n.isArchived = true')
                ->orderBy('n.updatedAt', 'DESC'),
            self::VIEW_TRASH => $qb->andWhere('n.trashedAt IS NOT NULL')
                ->orderBy('n.trashedAt', 'DESC'),
            default => $qb->andWhere('n.trashedAt IS NULL AND n.isArchived = false')
                ->orderBy('n.isPinned', 'DESC')->addOrderBy('n.updatedAt', 'DESC'),
        };
        $qb->addOrderBy('n.id', 'DESC');

        if (isset($filters['labelId'])) {
            $qb->andWhere(':label MEMBER OF n.labels')->setParameter('label', $filters['labelId']);
        }
        if (!empty($filters['color'])) {
            $qb->andWhere('n.color = :color')->setParameter('color', $filters['color']);
        }
        if (!empty($filters['type'])) {
            $qb->andWhere('n.type = :type')->setParameter('type', $filters['type']);
        }
        if (!empty($filters['search'])) {
            // itemsText = texte des cases à plat (même collation que titre et corps).
            $qb->andWhere('n.title LIKE :search OR n.content LIKE :search OR n.itemsText LIKE :search')
                ->setParameter('search', '%'.addcslashes($filters['search'], '%_\\').'%');
        }

        return $qb;
    }

    /**
     * Supprime définitivement les notes à la corbeille avant la date limite donnée.
     *
     * @return int nombre de notes supprimées
     */
    public function purgeTrashedBefore(\DateTimeImmutable $limit): int
    {
        return (int) $this->createQueryBuilder('n')
            ->delete()
            ->where('n.trashedAt IS NOT NULL AND n.trashedAt < :limit')
            ->setParameter('limit', $limit)
            ->getQuery()
            ->execute();
    }
}
