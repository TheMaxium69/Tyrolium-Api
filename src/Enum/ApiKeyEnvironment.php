<?php

namespace App\Enum;

/**
 * Voir .doc/permissions.md (section ApiKey) — convention `live`/`test`
 * choisie par Maxime le 25/09/2026, inspirée de Stripe (`sk_live_`/`sk_test_`).
 */
enum ApiKeyEnvironment: string
{
    case LIVE = 'live';
    case TEST = 'test';
}
