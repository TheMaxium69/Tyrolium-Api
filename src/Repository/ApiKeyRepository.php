<?php

namespace App\Repository;

use App\Entity\ApiKey;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ApiKey>
 */
class ApiKeyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ApiKey::class);
    }

    /**
     * Résout une clé API brute (ex: "tyrokey_live_...") en entité ApiKey,
     * en la hashant d'abord (jamais de clé brute stockée, voir ApiKey.php),
     * puis en refusant tout ce qui est révoqué ou expiré. Utilisé par
     * ApiKeyTokenHandler à chaque requête authentifiée par clé API.
     */
    public function findValidByRawKey(string $rawKey): ?ApiKey
    {
        $apiKey = $this->findOneBy(['keyHash' => hash('sha256', $rawKey)]);

        if (null === $apiKey || $apiKey->isRevoked() || $apiKey->isExpired()) {
            return null;
        }

        return $apiKey;
    }
}
