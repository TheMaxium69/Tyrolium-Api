<?php

namespace App\Enum;

/**
 * Statut de provisionnement / d'exploitation d'un Server — voir Server.php.
 */
enum ServerStatus: string
{
    case PROVISIONING = 'provisioning';
    case ACTIVE = 'active';
    case MAINTENANCE = 'maintenance';
    case SUSPENDED = 'suspended';
    case DECOMMISSIONED = 'decommissioned';
}
