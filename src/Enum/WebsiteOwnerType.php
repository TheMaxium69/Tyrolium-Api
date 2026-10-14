<?php

namespace App\Enum;

/**
 * À qui appartient un Website : une filiale du groupe, ou un client (via une
 * Prestation, un compte Useritium ou un simple nom) — voir Website.php.
 */
enum WebsiteOwnerType: string
{
    case FILIALE = 'filiale';
    case CLIENT = 'client';
}
