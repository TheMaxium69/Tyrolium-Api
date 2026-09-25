<?php

namespace App\Enum;

/**
 * Niveau d'accès d'un compte Useritium — voir .doc/permissions.md. Un même
 * compte sert aussi bien le public (clients TyroServ/SolidServ/Gamenium) que
 * le personnel interne : ce n'est PAS un système de permissions à soi seul,
 * juste la porte d'entrée qui détermine si le système de permissions
 * granulaires (Permission/UserPermission) s'applique du tout à ce compte.
 *
 * - USER    : client/public — aucune permission, jamais, quoi qu'il y ait en
 *             base dans user_permission (voir User::getRoles()).
 * - INTERNE : employé — les permissions granulaires deviennent effectives.
 * - OWNER   : Maxime exclusivement, jamais accordé via API/CLI (décision du
 *             25/09/2026) — bypass absolu de toute vérification, voir
 *             App\Security\OwnerBypassVoter.
 */
enum AccessLevel: string
{
    case USER = 'user';
    case INTERNE = 'interne';
    case OWNER = 'owner';
}
