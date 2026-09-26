<?php

namespace App\Enum;

/**
 * "LISTED" (répertoriée, visible publiquement) vs "CUSTOM" (sur-mesure, pas
 * dans le catalogue public — devis client précis, type Cabinet Marthelot
 * pour SolidServ dans le cahier des charges). Voir Offre::$visibility.
 */
enum OffreVisibility: string
{
    case LISTED = 'listed';
    case CUSTOM = 'custom';
}
