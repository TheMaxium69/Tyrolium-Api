<?php

namespace App\Enum;

/**
 * Statut d'une Prestation (relation offre <-> client) — voir Prestation.php.
 */
enum PrestationStatus: string
{
    case PENDING = 'pending';
    case ACTIVE = 'active';
    case SUSPENDED = 'suspended';
    case TERMINATED = 'terminated';
}
