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

Projet repris de zéro en solo par Maxime en septembre 2026, après une première tentative en équipe (juillet-août 2026) mise en pause — voir `.doc/journal-ia.md`. Le squelette technique (Symfony, CORS, rate limiting, helpers, tooling, CI, scripts) est repris tel quel de cette première tentative ; le métier (comptes, RBAC, offres, analytics, sites web, SolidServ...) a été reconstruit depuis, module par module — voir l'[État du projet](#état-du-projet--roadmap).

## Stack

| Élément | Version |
|---|---|
| Symfony | 8.1 (`symfony/skeleton`, pas API Platform) |
| PHP | `>=8.4` (`composer.json`) |
| Base de données | MySQL, partout (dev et prod) |
| ORM | Doctrine ORM 3.6 / DBAL 4.4 |
| Gestionnaire de paquets | Composer 2.x + Symfony Flex |

Autres briques déjà en place : `symfony/rate-limiter`, `nelmio/cors-bundle`, `symfony/serializer`, `symfony/validator`, `doctrine/doctrine-migrations-bundle`, `lexik/jwt-authentication-bundle` (JWT RS256, branché sur le firewall `api` avec le provider `app_user_provider`), `roave/security-advisories` (bloque l'installation de paquets aux CVE connues).

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

Ou sous Apache/Docker (voir `.doc/env-files.md` section 2.4 pour le détail) :

```bash
docker compose up -d database app              # dev — http://127.0.0.1:8000
APP_ENV=prod docker compose up -d database app  # prod
```

## Structure du projet

```
config/
  packages/        # config par bundle (security, rate_limiter, nelmio_cors, doctrine...)
  secrets/         # Symfony Secrets Vault (par environnement)
src/
  Controller/      # controllers métier, un dossier par namespace (Useritium/, Tyrolium/, SolidServ/...)
    Useritium/     # Account (compte, JWT), Admin (administration des comptes), Dashboard (carte utilisateur, mes prestations / serveurs / sites), Note (notes façon Keep), Task et TaskProject (tâches et kanban)
    Tyrolium/      # Permission (RBAC), ApiKey, Prestation (offres + prestations), Analytics, WebSite
    SolidServ/     # Product (catalogue), Dashboard (parc de serveurs + types de serveurs)
    Test/          # exemples pédagogiques de permissions — pas du métier
  Entity/          # 20 entités à plat (User, Permission, ApiKey, Offre, Prestation, Website, SolidServProduct, Server, Note, Task, TaskProject...) — jamais triées par namespace, contrairement aux Controller
  Repository/      # un repository par entité
  Security/        # UserProvider, UserChecker, Authentication{Success,Failure}Handler, OwnerBypassVoter, ApiKeyTokenHandler
  Enum/            # AccessLevel, Filiale, OffreVisibility, PrestationStatus, ServerStatus, WebsiteOwnerType, WebsiteStatus, ApiKeyEnvironment
  Service/         # TaskPlacement (positions / glisser-déposer), TaskPresenter (sortie JSON des tâches)
  Command/         # app:note:purge-trash (corbeille des notes, à planifier en prod)
  EventSubscriber/ # ApiRateLimitSubscriber
  Helper/          # ApiResponseHelper (fonctions globales, hors PSR-4), Pagination
migrations/        # 27 migrations Doctrine
public/             # index.php, analytics.js (script de tracking à coller sur les sites suivis), uploads/ (photos de profil, gitignoré)
tests/             # bootstrap seulement — aucun test PHPUnit pour l'instant
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

- **Authentification** JWT (RS256) sur `UseritiumAccountController` (16 routes : register, login, verify-email, forgot/reset-password, gestion multi-email, logout-all-devices, `get-me`, photo de profil et sa suppression, nom affiché, changement de pseudo et de mot de passe, suppression du compte) — `.doc/useritium-auth.md`, `.doc/auth-endpoints-guide.md`
- **RBAC à deux étages** : niveau d'accès (`user` / `interne` / `owner`, `owner` uniquement en base) puis permissions granulaires `filiale.perimetre.action` (rôles `PERMS_…`, permissions « parapluie », bypass `OwnerBypassVoter`). Les changements sont immédiats, sans reconnexion — `.doc/permissions.md`
- **Clés API** pour les systèmes tiers (en-tête `X-Api-Key`, clé hashée en SHA-256, révocable, jamais de rôle owner/interne) — `.doc/permissions.md`
- **Administration des comptes** avec bannissement (login refusé et sessions invalidées) ; un owner est intouchable via l'API — `.doc/modules.md`
- **Pagination** de toutes les listes (`?page=1&limit=20`, max 100) — `.doc/modules.md` §1.4

Pas encore en place : `access_control` par rôle (tout est `PUBLIC_ACCESS` pour l'instant, chaque route sensible se protège elle-même via `#[IsGranted]`), proxies de confiance (nécessaires derrière un reverse proxy pour lire la vraie IP), Redis pour le rate limiter multi-instance — voir [État du projet](#état-du-projet--roadmap).

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

**Fait** (inventaire complet des routes, permissions et entités : `.doc/modules.md`)

- [x] Comptes Useritium : inscription, connexion JWT, emails multiples, photo de profil, nom affiché, `get-me`
- [x] RBAC (niveaux + permissions + parapluies), clés API
- [x] Administration des comptes : liste, détail, édition, bannissement, révocation des sessions
- [x] Offres et prestations (catalogue + lien client, dépôt git)
- [x] Analytics : projets suivis, enregistrement public des visites, totaux calculés en base
- [x] Registre des sites web : domaines, échéances, SSL, dépôt git, serveur hébergeur
- [x] SolidServ : catalogue de produits, types de serveurs, parc de serveurs
- [x] Pagination sur toutes les listes
- [x] Permissions utilisées créées par migration (idempotentes)

**À faire**

- [ ] Corriger les 24 erreurs PHPStan niveau 8 (la CI échouerait sur une PR) et écrire de vrais tests PHPUnit (`tests/` est vide)
- [ ] Mettre en prod : proxies de confiance, `access_control` par rôle, Redis pour `cache.rate_limiter` + `symfony/lock`, initialisation du Secrets Vault prod
- [ ] Retirer les données de démonstration et les controllers `Test/` avant la prod (`.doc/modules.md` §2.2)
- [ ] Importer les ~130 serveurs réels de la page publique `tyrolium.fr/server` (le modèle est prêt)
- [ ] Controllers restants : `UseritiumOAuth`, `UseritiumSso`, `UseritiumDrive`, `TyroliumSupport`, `TyroliumCompta` (devis/achats, paiement), `SolidServStatus`, `SolidServProxmox`, `SolidServLog`, `TyroServ`, `Gamenium` — voir `.doc/cahier-des-charges.md`
- [ ] Signalements et modération dans `UseritiumAdminController`
- [ ] Auth déléguée OAuth2 (`league/oauth2-server-bundle`) pour les systèmes tiers (Odoo...)
- [ ] Refonte SSO cross-domaine (Authorization Code + PKCE) — prérequis pour lier une visite analytics à un compte
- [ ] Vérification de signature pour les webhooks entrants (Stripe, Lemon Squeezy, UptimeRobot, Proxmox)
- [ ] Timing side-channel sur le login (connu, reporté — `.doc/journal-ia.md`)

## Documentation complète

Le dossier `.doc/` contient toute la documentation technique détaillée (config actuelle, tuto de modification, explication du fonctionnement interne) :

| Fichier | Contenu |
|---|---|
| `context-ia.md` | règles pour toute IA travaillant sur ce repo — à lire en premier |
| `journal-ia.md` | historique du projet, ancien repo vs nouveau, décisions datées |
| `modules.md` | inventaire des controllers/routes/permissions/entités, ajouter un module, dette connue |
| `permissions.md` | RBAC : niveaux d'accès, permissions, parapluies, clés API |
| `repo-info.md` | fiche d'identité technique (versions exactes) |
| `cahier-des-charges.md` | cahier des charges complet (métier, vision produit) |
| `naming-conventions.md` | conventions de nommage détaillées |
| `api-response-format.md` | contrat de réponse API / TypeScript |
| `env-files.md` | fonctionnement des fichiers `.env*` |
| `rate-limit.md` | rate limiting |
| `cors.md` | CORS |
| `secrets-vault.md` | Secrets Vault Symfony |
| `password-hashing.md` | hashing des mots de passe |
| `db-migrations.md` | workflow de migration Doctrine |
| `git-workflow.md` | scripts `scripts/` et workflow git |
| `useritium-auth.md` | design des 3 mécanismes d'authentification (JWT, OAuth2, SSO) |
| `auth-endpoints-guide.md` | spec des 9 routes d'auth (payloads, curl) de la première tentative |
