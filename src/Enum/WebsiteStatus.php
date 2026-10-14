<?php

namespace App\Enum;

/**
 * Statut d'un Website dans le registre — voir Website.php.
 */
enum WebsiteStatus: string
{
    case ACTIVE = 'active';
    case MAINTENANCE = 'maintenance';
    case SUSPENDED = 'suspended';
    case ARCHIVED = 'archived';
}
