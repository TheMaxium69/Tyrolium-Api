<?php

namespace App\Helper;

use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Component\HttpFoundation\Request;

final class Pagination
{
    public const DEFAULT_LIMIT = 20;
    public const MAX_LIMIT = 100;

    /**
     * @return array{items: list<object>, pagination: array{page: int, limit: int, total: int, pages: int}}
     */
    public static function fromQueryBuilder(QueryBuilder $queryBuilder, Request $request): array
    {
        $page = max(1, self::intParam($request, 'page', 1));
        $limit = min(self::MAX_LIMIT, max(1, self::intParam($request, 'limit', self::DEFAULT_LIMIT)));

        $queryBuilder->setFirstResult(($page - 1) * $limit)->setMaxResults($limit);
        $paginator = new Paginator($queryBuilder, fetchJoinCollection: true);

        $total = count($paginator);

        return [
            'items' => array_values(iterator_to_array($paginator, false)),
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => (int) ceil($total / $limit),
            ],
        ];
    }

    private static function intParam(Request $request, string $key, int $default): int
    {
        return filter_var($request->query->get($key), FILTER_VALIDATE_INT) ?: $default;
    }
}
