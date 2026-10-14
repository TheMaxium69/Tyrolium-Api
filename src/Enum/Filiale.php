<?php

namespace App\Enum;

/**
 * Filiales et structures du groupe Tyrolium (cahier des charges, section 2) —
 * propriétaires possibles d'un Website quand il n'appartient pas à un client.
 */
enum Filiale: string
{
    case TYROLIUM = 'tyrolium';
    case USERITIUM = 'useritium';
    case SOLIDSERV = 'solidserv';
    case TYROSERV = 'tyroserv';
    case GAMENIUM = 'gamenium';
    case VTURIAS = 'vturias';
    case INFLUNIAS = 'influnias';
    case TYROCIEL = 'tyrociel';
}
