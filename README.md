# Tyrolium-Api

API backend centrale de l'écosystème Tyrolium — Symfony 8.1, 100% JSON, sécurité by design.

## Sommaire

- [Contexte](#contexte)
- [Stack](#stack)
- [Installation](#installation)
- [Lancer le projet](#lancer-le-projet)
- [Structure du projet](#structure-du-projet)
- [Conventions](#conventions)
- [Sécurité en place](#sécurité-en-place)
- [CI](#ci)
- [Workflow Git](#workflow-git)
- [État du projet / roadmap](#état-du-projet--roadmap)
- [Documentation complète](#documentation-complète)

## Contexte

Tyrolium unifie plusieurs backends historiques (Gamenium, TyroServ, l'ancien `api.useritium.fr`...) en une architecture API-first centralisée. Cette API sert de backend unique à :

- **Tyrolium HUB** (`hub.tyrolium.fr`) — frontend Angular interne, réservé au staff
- **Useritium Dashboard** (`dashboard.useritium.fr`) — frontend Angular public, client-facing
- **Tyrolium App** (`app.tyrolium.fr`) — instance Odoo interne (gestion, facturation, RH), reliée en SSO
- Les filiales : SolidServ, TyroServ, Gamenium, et d'autres à venir

Cette API **ne sert aucune page web** — c'est un skeleton Symfony pur (pas d'API Platform, pas de Twig), consommé à distance par des frontends séparés. Vision complète dans le cahier des charges (voir [Documentation complète](#documentation-complète)).

## Stack

| Élément | Version |
|---|---|
| Symfony | 8.1 (`symfony/skeleton`, pas API Platform) |
| PHP | `>=8.4` (`composer.json`) |
| Base de données | MySQL, partout (dev et prod) |
| ORM | Doctrine ORM 3.6 / DBAL 4.4 |
| Gestionnaire de paquets | Composer 2.x + Symfony Flex |

Autres briques déjà en place : `symfony/rate-limiter`, `nelmio/cors-bundle`, `symfony/serializer`, `symfony/validator`, `doctrine/doctrine-migrations-bundle`, `roave/security-advisories` (bloque l'installation de paquets aux CVE connues).

## Installation

```bash
git clone git@github.com:TheMaxium69/Tyrolium-Api.git
cd Tyrolium-Api
composer install
```

### Base de données locale

Copie le template et adapte les identifiants à ta config locale (MAMP, Docker, install native...) :

```bash
cp .env.local.template .env.local
```

`.env.local` n'est jamais commité (voir `.doc/env-files.md`). Adapte le port/user/password/nom de DB à ta machine, puis :

```bash
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
```

Vérifie que la connexion fonctionne :

```bash
php bin/console dbal:run-sql "SELECT 1"
```

## Lancer le projet

Avec le binaire Symfony CLI (recommandé) :

```bash
symfony server:start
```

Ou en PHP natif :

```bash
APP_ENV=dev php -S 127.0.0.1:8000 -t public
```

## Structure du projet

```
config/
  packages/        # config par bundle (security, rate_limiter, nelmio_cors, doctrine...)
  secrets/         # Symfony Secrets Vault (par environnement)
src/
  Controller/      # controllers métier, un dossier par namespace (Useritium/, Tyrolium/, SolidServ/...)
  Controller/Debug/  # controllers de scratch, routes actives en dev uniquement
  Entity/          # entités Doctrine
  EventSubscriber/ # ApiRateLimitSubscriber, etc.
  Helper/          # ApiResponseHelper (fonctions globales, hors PSR-4)
migrations/        # migrations Doctrine
tests/
.doc/              # documentation technique complète (voir plus bas)
```

## Conventions

Toutes les conventions de nommage (dossiers de controllers, classes, méthodes, routes, variables) sont figées et documentées dans `.doc/naming-conventions.md`. Résumé rapide :

| Élément | Règle | Exemple |
|---|---|---|
| Dossier controller | premier segment d'URL, minuscule | `Useritium/` |
| Classe | `{Dossier}{Spécifique}Controller` | `UseritiumSsoController` |
| Méthode | `{verbeHttp}{Contexte}` camelCase | `getAllUser`, `postCreateOffer` |
| URL | méthode en kebab-case | `/useritium/sso/get-all-user` |
| Nom de route | chemin complet en snake_case | `useritium_sso_get_all_user` |
| Variables PHP | toujours camelCase | `$hashedPassword` |

Le format de réponse JSON (enveloppe `success`/`code`/`message`/`data`/`errors`/`meta`) est standardisé pour toutes les routes — spec complète et contrat TypeScript dans `.doc/api-response-format.md`. Toujours passer par `src/Helper/ApiResponseHelper.php` (`apiSuccess()`, `apiError()`, `apiValidationError()`) plutôt que de construire une `JsonResponse` à la main.

## Sécurité en place

- **Rate limiting** global (`api_global`, 100 req/min, token bucket, par IP) — `.doc/rate-limit.md`
- **CORS** via `nelmio/cors-bundle`, origines restreintes par regex (dev: localhost only pour l'instant) — `.doc/cors.md`
- **Hashing des mots de passe** via `password_hashers: auto` (résout en bcrypt) — `.doc/password-hashing.md`
- **Secrets Vault** Symfony pour tout secret réel de prod (pas de secret en variable d'env brute sauf si fourni par l'hébergeur) — `.doc/secrets-vault.md`
- **`roave/security-advisories`** empêche l'installation d'un paquet avec une CVE connue

Pas encore en place : authenticator JWT (conçu, pas codé), `access_control` par rôle, Redis pour le rate limiter multi-instance — voir [État du projet](#état-du-projet--roadmap).

## CI

`.github/workflows/ci.yml`, déclenchée sur toute PR et sur push vers `main` :

- `composer validate --strict`
- `composer audit`
- lint YAML / container / syntaxe PHP
- PHPStan niveau 8 (`phpstan-symfony` + `phpstan-doctrine`)
- PHPUnit

Pas de job de formatage/CS-Fixer — le style de code n'est volontairement pas vérifié en CI.

## Workflow Git

- `main` est protégée sur GitHub — pas de push direct, tout passe par PR + CI verte.
- Développement courant sur des branches de feature, PR vers `main`.
- CODEOWNERS : `.github/CODEOWNERS`.

## État du projet / roadmap

- [ ] Authenticator JWT (`lexik/jwt-authentication-bundle`) pour `UseritiumAccountController` — conçu, pas installé
- [ ] `access_control` par rôle une fois de vrais controllers métier en place
- [ ] Auth déléguée OAuth2 (`league/oauth2-server-bundle`) pour les systèmes tiers (Odoo...)
- [ ] Refonte SSO cross-domaine (Authorization Code + PKCE) pour remplacer le mécanisme legacy
- [ ] Redis pour `cache.rate_limiter` + `symfony/lock` (nécessaire en multi-instance)
- [ ] Vérification de signature pour les webhooks entrants (Stripe, Lemon Squeezy, UptimeRobot, Proxmox)
- [ ] Initialisation du Secrets Vault prod

## Documentation complète

Le dossier `.doc/` contient toute la documentation technique détaillée (config actuelle, tuto de modification, explication du fonctionnement interne, vérifié en conditions réelles sur ce projet) :

| Fichier | Contenu |
|---|---|
| `repo-info.md` | fiche d'identité technique (versions exactes) |
| `cahier-des-charges.md` | cahier des charges complet (métier, vision produit) |
| `naming-conventions.md` | conventions de nommage détaillées |
| `api-response-format.md` | contrat de réponse API / TypeScript |
| `env-files.md` | fonctionnement des fichiers `.env*` |
| `rate-limit.md` | rate limiting |
| `cors.md` | CORS |
| `secrets-vault.md` | Secrets Vault Symfony |
| `password-hashing.md` | hashing des mots de passe |
| `useritium-auth.md` | design des 3 mécanismes d'authentification (JWT, OAuth2, SSO) |