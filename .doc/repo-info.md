# Info repo — pour toute IA qui bosse ici

Fiche d'identité technique du projet, pour ne jamais deviner/halluciner une version. Toutes les valeurs ci-dessous sont lues directement depuis ce repo (`composer.lock`, `php -v`), pas des suppositions — à revérifier quand même si ce fichier a l'air vieux (regarde la date en bas).

---

## Stack

| Élément | Version |
|---|---|
| **Symfony** | **8.1.4** (`php bin/console --version`) |
| PHP requis (`composer.json`) | `>=8.4` |
| PHP installé sur la machine de Maxime | 8.5.8 |
| Base de données | **MySQL** (partout — dev et prod, décision de Maxime, pas Postgres malgré le placeholder par défaut du recipe Doctrine) |
| Gestionnaire de paquets | Composer 2.x + Symfony Flex |

## Bundles / paquets clés installés (versions verrouillées dans `composer.lock`)

| Paquet | Version |
|---|---|
| `symfony/framework-bundle` | 8.1.4 |
| `symfony/security-bundle` | 8.1.2 |
| `symfony/serializer` | 8.1.4 |
| `symfony/validator` | 8.1.4 |
| `symfony/rate-limiter` | 8.1.4 |
| `nelmio/cors-bundle` | 2.6.1 |
| `doctrine/doctrine-bundle` | 3.3.1 |
| `doctrine/orm` | 3.6.8 |
| `doctrine/dbal` | 4.4.4 |
| `doctrine/doctrine-migrations-bundle` | 4.0.0 |
| `symfony/maker-bundle` (dev) | 1.67.0 |
| `phpstan/phpstan` (dev) | 2.2.8 |
| `phpunit/phpunit` (dev) | 13.3.0 |

**Pas encore installés** (prévus mais pas encore ajoutés) : `lexik/jwt-authentication-bundle` (auth JWT), `league/oauth2-server-bundle` (OAuth2 pour Odoo/tiers), `symfony/lock` (concurrence exacte du rate limiter). Voir `.doc/useritium-auth.md` pour le pourquoi.

## Ce que ce projet est (et n'est pas)

- **Symfony skeleton pur** (`symfony/skeleton`, pas API Platform) — tous les controllers/entités sont écrits à la main.
- **100 % API JSON** — pas de templates Twig, pas de partie "site web" servie par cette app. C'est pour ça que le firewall/rate-limiter/CORS couvrent `^/` (toute l'app) et non un préfixe `/api` : il n'y a rien d'autre à exclure.
- Nom du repo : `Tyrolium-Api` (GitHub `TheMaxium69/Tyrolium-Api`). Fait partie d'un écosystème plus large (Tyrolium HUB, Useritium Dashboard, filiales SolidServ/TyroServ/Gamenium...) — voir `.doc/cahier-des-charges.md` pour la vision complète.
- Branche de travail : `maxime`, PRs vers `main`.

## Où trouver le reste

- **Comment le projet est structuré / conventions de code** → `.doc/naming-conventions.md`
- **Comment les fichiers `.env` fonctionnent** → `.doc/env-files.md`
- **Cahier des charges complet (métier)** → `.doc/cahier-des-charges.md`
- **Design de l'authentification (3 mécanismes distincts)** → `.doc/useritium-auth.md`
- **Tous les autres sujets déjà traités** (rate limiting, CORS, secrets, hashing de mot de passe) → un fichier dédié par sujet dans `.doc/`, voir `context-ia.md` pour le pourquoi de tout ce dossier.

---

*Généré le 2026-08-09, à partir de l'état réel du repo à ce moment-là (`composer.lock`, `php bin/console --version`). Si une IA lit ça plus tard et que ça semble incohérent avec le `composer.lock` actuel, faire confiance au `composer.lock`, pas à ce fichier — et le mettre à jour.*
