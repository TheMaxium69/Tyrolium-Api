# Fichiers `.env` — Tyrolium API

Comment Symfony gère la config par environnement, et comment on s'en sert sur ce projet.

Rappel du contexte : historiquement (Symfony 3/4) un seul `.env` gitignored + un `.env.dist` committé comme template suffisaient. Depuis Symfony 5+, c'est éclaté en plusieurs fichiers avec un rôle précis chacun — objectif : séparer clairement "config partagée par l'équipe" de "config perso à ta machine", et "non sensible, committable" de "secret, jamais committé".

---

## 1. Valeurs actuelles

| Fichier | Existe sur ce repo ? | Committé (git) ? | Contenu actuel |
|---|---|---|---|
| `.env` | Oui | Oui | `APP_ENV=dev`, `APP_SECRET=` (vide), `APP_SHARE_DIR`, `DEFAULT_URI`, config CORS (`CORS_ALLOW_ORIGIN` limité à localhost), `DATABASE_URL` placeholder **MySQL** (`app:!ChangeMe!`, non fonctionnel — moteur choisi le 2026-08-09, voir section 2.2) |
| `.env.local` | **Oui, créé le 2026-08-09** | Non (dans `.gitignore`) | `DATABASE_URL` MAMP réel (`root:root@127.0.0.1:8889/tyrolium_api`) — connexion pas encore vérifiée en conditions réelles, voir section 2.2 |
| `.env.dev` | Oui | Oui | `APP_SECRET` généré par Flex, dédié dev (voir section 3) |
| `.env.dev.local` | Non | Non | — |
| `.env.test` | Oui | Oui | `KERNEL_CLASS`, `APP_SECRET` factice de test (généré par `symfony/test-pack`) |
| `.env.prod` | **Oui, créé le 2026-08-09** | Oui | `CORS_ALLOW_ORIGIN` (hub.tyrolium.fr / dashboard.useritium.fr) ; pas de `DATABASE_URL` (secret, voir section 2.2) |
| `.env.prod.local` | Non | Non | — |
| `.env.local.php` | Non | Non (généré, jamais à la main) | — |

`doctrine/doctrine-bundle` (3.3) et `doctrine/orm` (3.6) sont installés (2026-08-09). Le recipe Flex a généré par défaut une `DATABASE_URL` **PostgreSQL 16** dans `.env` (alternatives SQLite/MySQL/MariaDB laissées en commentaire juste au-dessus, au cas où) :

```
DATABASE_URL="postgresql://app:!ChangeMe!@127.0.0.1:5432/app?serverVersion=16&charset=utf8"
```

`app` / `!ChangeMe!` est un placeholder générique du recipe officiel, pas une vraie connexion — il ne pointe vers rien de fonctionnel tant que tu n'as pas un vrai Postgres qui écoute avec ces identifiants. Cohérent avec la règle "committable = pas sensible" de ce document : c'est un template, pas un secret. **Le choix Postgres vs MySQL n'a pas été discuté** — le cahier des charges (section 4) laisse la modélisation DB explicitement ouverte ("laissée libre") ; si tu veux MySQL/MariaDB à la place, décommente la ligne correspondante et supprime/commente celle de Postgres.

Connexion testée en réel (SQLite temporaire, `SELECT 1` exécuté avec succès puis nettoyé) : le câblage Doctrine fonctionne bout en bout, pas juste la config qui parse.

---

## 2. Tuto pratique

### 2.1 Créer `.env.local`

C'est juste un fichier texte au même format que `.env`, à la racine du projet :

```bash
touch .env.local
```

```
# .env.local — jamais committé, propre à ta machine
APP_SECRET=un_secret_different_de_celui_du_repo_si_tu_veux
```

Rien d'obligatoire dedans — ce fichier n'existe que pour **surcharger** des valeurs de `.env`/`.env.dev` sur ta machine à toi, sans que ça impacte le reste de l'équipe. Tant que tu n'as rien à surcharger en solo, il n'a pas besoin d'exister (c'est le cas actuellement).

### 2.2 Connecter une base de données

`doctrine/doctrine-bundle` + `doctrine/orm` sont installés. `.env` contient déjà le placeholder généré par le recipe :

```
DATABASE_URL="postgresql://app:!ChangeMe!@127.0.0.1:5432/app?serverVersion=16&charset=utf8"
```

**Pour connecter ta vraie DB locale**, crée `.env.local` avec ta vraie chaîne de connexion (elle écrase celle de `.env`, cf. ordre de priorité en section 3) :

```
# .env.local
DATABASE_URL="postgresql://ton_user:ton_password@127.0.0.1:5432/ta_db?serverVersion=16&charset=utf8"
```

**Cas MAMP (MySQL) — choix retenu pour le dev local sur cette machine** : le placeholder par défaut de `.env` est en PostgreSQL, mais Maxime utilise MAMP (MySQL) en local. Exemple de `DATABASE_URL` pour MAMP, à mettre dans `.env.local` :

```
# .env.local
DATABASE_URL="mysql://root:root@127.0.0.1:8889/nom_de_ta_db?serverVersion=8.0&charset=utf8mb4"
```

Port `8889` et identifiants `root`/`root` = les valeurs par défaut classiques de MAMP (pas MAMP PRO), **à vérifier dans tes préférences MAMP** (onglet "Ports") — configurable, donc pas garanti identique sur toute installation. `serverVersion` doit correspondre à la version MySQL réellement utilisée par ton MAMP (visible dans MAMP > Preferences > Server, ou `mysql --version`).

Comme le schéma passe de `postgresql://` à `mysql://`, `config/packages/doctrine.yaml` n'a rien à changer (le `driver` est déduit automatiquement du schéma de l'URL par DBAL) — seule la ligne `DATABASE_URL` compte.

**Docker (généré par le recipe, non utilisé ici)** : `compose.yaml` + `compose.override.yaml` ont aussi été générés à la racine (service Postgres 16 avec les mêmes identifiants que le placeholder de `.env`) — ignorables tant que MAMP est le choix retenu. Non testés (pas lancé Docker sur cette machine).

**Où va quoi, au final :**

- `.env` : reste le placeholder générique du recipe (`app:!ChangeMe!`), jamais de vrai identifiant.
- `.env.local` : ta vraie connexion DB **locale**, propre à ta machine.
- `.env.prod` (committé) : rien de sensible — au mieux le host non secret si applicable (ex: `database701.tyrolium.fr`, cf. cahier des charges sur le cluster DB), jamais le mot de passe.
- En prod réelle : le mot de passe DB va en variable d'environnement serveur ou dans le Secrets Vault, jamais dans un fichier `.env.prod` committé.

**Vérifier que la connexion marche vraiment**, une fois `.env.local` rempli :

```bash
php bin/console dbal:run-sql "SELECT 1"
```

Si ça renvoie une ligne avec `1`, la connexion est bonne. Erreur de connexion (mauvais host/port/creds) → message DBAL explicite à ce moment-là.

### 2.3 Créer `.env.prod`

Pour l'instant, une seule chose concrète identifiée à y mettre : le `CORS_ALLOW_ORIGIN` de prod (cf. `.doc/cors.md` section 2.1), sans `localhost` :

```
# .env.prod
CORS_ALLOW_ORIGIN='^https://(hub\.tyrolium\.fr|dashboard\.useritium\.fr)$'
```

Rien d'autre n'est identifié à ce jour — ce fichier se remplira au fur et à mesure des besoins de prod (DB, futurs services tiers).

### 2.4 Vérifier quelle valeur Symfony charge réellement

En cas de doute sur quel fichier gagne :

```bash
php bin/console debug:dotenv
```

Liste toutes les variables résolues et **de quel fichier elles viennent**, dans l'ordre de priorité réel. Le réflexe à avoir avant de se demander "pourquoi ma valeur n'est pas prise en compte".

---

## 3. Comment ça marche en détail

### Ordre de chargement (le dernier lu écrase les précédents)

```
.env  →  .env.local  →  .env.$APP_ENV  →  .env.$APP_ENV.local
```

Et au-dessus de tout : une **vraie variable d'environnement système** (définie par le shell, Docker, la CI, le serveur) écrase toujours n'importe quelle valeur venant d'un fichier `.env*`. Symfony ne réécrit jamais une variable déjà présente dans l'environnement réel.

### Le piège `.env.local` + tests

`.env.local` est **volontairement ignoré quand `APP_ENV=test`**. Comportement de conception : les tests doivent donner le même résultat sur n'importe quelle machine (la tienne, un collègue, la CI), donc ils ne doivent jamais dépendre d'un override personnel. Une valeur mise dans `.env.local` ne s'appliquera donc jamais à `vendor/bin/phpunit` — seul `.env.test` / `.env.test.local` compte pour cet environnement.

### Pourquoi `.env` et `.env.$APP_ENV` sont committés, mais pas `.local`

Le critère : **"cette valeur peut-elle être vue par n'importe qui sur GitHub sans risque ?"**

- Oui (structurel, non sensible : `KERNEL_CLASS`, un flag de feature, une URL publique) → committé (`.env` ou `.env.$APP_ENV`).
- Non (mot de passe, clé API, secret de signature) → jamais dans un fichier committé. En local : `.local`. En prod partagée/déployée : variable d'environnement serveur ou **Secrets Vault** Symfony (`bin/console secrets:set`), qui chiffre la valeur et la stocke hors du code source — pas un fichier `.env` du tout. C'est le changement fondamental par rapport à l'ancienne pratique "tout dans le `.env` gitignored" : il y a maintenant deux mécanismes dédiés aux vrais secrets, séparés du système `.env` par environnement.

### Pourquoi `.env.dev` contient un vrai secret alors que `.env.prod` n'en aura jamais

Ce n'est pas une incohérence : `.env.dev` sert uniquement en local, jamais déployé, donc son secret n'a pas d'enjeu de sécurité réel (voir la discussion précédente sur le firewall stateless / la convention Flex 7.2+). `.env.prod`, lui, est déployé sur un vrai serveur exposé — un secret dedans serait visible par quiconque a accès au repo GitHub, ce qui est un vrai risque. D'où la règle : `.env.$APP_ENV` committé ne doit **jamais** contenir de secret pour un environnement réellement déployé (prod, staging), même si Flex l'a fait pour `dev`/`test` par confort.

### `.env.local.php` — le seul fichier qu'on ne remplit jamais à la main

Généré par `composer dump-env prod` (voir `.doc/how-to-document.md` et notre test précédent lors de la mise en place de la CI). C'est une compilation de **toutes** les variables déjà résolues en un seul fichier PHP, pour que Symfony n'ait pas à reparser/merger tous les fichiers `.env*` à chaque requête en prod (gain de perf). Généré au déploiement, jamais committé, jamais édité directement.
