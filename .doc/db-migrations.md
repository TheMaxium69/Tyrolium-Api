# Migrations & Entités — Tyrolium API

Comment faire évoluer le schéma DB quand tu modifies une entité, et comment ça se propage à l'équipe une fois le fichier commité. Complète `.doc/env-files.md` (connexion DB) et `.doc/git-workflow.md` (où `migrations:migrate` s'insère dans les scripts).

---

## 1. Valeurs actuelles

| Variable | Valeur actuelle | Défini dans |
|---|---|---|
| Table de suivi des migrations appliquées | `doctrine_migration_versions`, colonne `version` (défaut du bundle, non surchargé — pas de `table_storage` dans la config) | vérifié en direct via `doctrine:migrations:status` |
| Namespace des classes de migration | `DoctrineMigrations` (volontairement différent de `App\Migrations` pour ne pas être autoloadé) | `config/packages/doctrine_migrations.yaml` |
| Dossier des fichiers de migration | `migrations/` (racine du projet) | `config/packages/doctrine_migrations.yaml` (`migrations_paths`) |
| Convention de nommage des fichiers | `Version{YYYYMMDDHHMMSS}.php` | ex: `migrations/Version20260809145248.php` |
| Mapping des entités | Attributs PHP (`#[ORM\...]`), `src/Entity/`, namespace `App\Entity` | `config/packages/doctrine.yaml` (`orm.mappings.App`) |
| Naming strategy | `doctrine.orm.naming_strategy.underscore` — ex: propriété `$tokensValidSince` → colonne `tokens_valid_since` | `config/packages/doctrine.yaml` |
| Entités actuelles | `User`, `UserEmail` | `src/Entity/` |
| État actuel (vérifié le 2026-08-10) | 4 migrations, toutes exécutées, 0 en attente | sortie `doctrine:migrations:status`, voir ci-dessous |

Sortie réelle de `php bin/console doctrine:migrations:status` (2026-08-10) :

```
Versions   | Current | DoctrineMigrations\Version20260809145248
           | Next    | Already at latest version
Migrations | Executed | 4   Available | 4   New | 0
```

---

## 2. Tuto pratique

### 2.1 Tu modifies ou crées une entité → générer la migration

Exemple : ajout d'une propriété sur `User` (`src/Entity/User.php`) :

```php
#[ORM\Column(length: 20, nullable: true)]
private ?string $phoneNumber = null;
```

Génère le fichier de migration :

```bash
php bin/console make:migration
```

Crée `migrations/Version<timestamp>.php` avec `up()`/`down()` en SQL brut. **Relis-le avant de continuer** — MakerBundle diff le mapping contre la DB réelle, mais ne devine pas toujours l'intention exacte (un renommage de colonne est détecté comme un `DROP` + `ADD`, par exemple).

### 2.2 Appliquer la migration sur TA base locale

```bash
php bin/console doctrine:migrations:migrate
```

Demande confirmation, exécute chaque migration non encore appliquée dans l'ordre du timestamp, met à jour `doctrine_migration_versions`.

### 2.3 Vérifier qu'il ne reste aucun drift

```bash
php bin/console doctrine:migrations:diff
```

**Comportement à connaître** (vérifié en direct) : si tout est synchronisé, cette commande ne dit pas juste "OK" — elle lève une exception `NoChangesDetected` (exit code non-zero, message "No changes detected in your mapping information."). C'est le résultat **attendu**, pas un vrai échec : ton mapping d'entité correspond exactement au schéma DB après application de ta migration. Si elle génère un nouveau fichier à la place d'échouer, ta migration précédente ne capture pas tout ce que tu as changé — à corriger avant de commit.

### 2.4 Commit

Le fichier de migration part **dans le même commit/PR** que le changement d'entité — jamais séparément, sinon un collègue qui pull entre les deux se retrouve avec une entité qui référence une colonne qui n'existe pas encore chez lui.

### 2.5 Côté collègue, après ton merge

Il n'a rien à générer. Son `pull.sh`/`new-branch.sh`/`sync-main.sh` (`.doc/git-workflow.md`) fait automatiquement :

```bash
php bin/console doctrine:migrations:migrate
```

qui repère que le fichier existe déjà chez lui (venu du `git pull`) mais n'est pas encore dans sa `doctrine_migration_versions`, et l'applique. Il n'a jamais besoin de lancer `make:migration` pour une migration que quelqu'un d'autre a déjà écrite.

### 2.6 Vérifier l'état à tout moment

```bash
php bin/console doctrine:migrations:status
```

Colonne `New` à `0` = ta DB locale est à jour avec tous les fichiers présents dans `migrations/`. `New` > `0` = il te manque un `migrations:migrate`.

---

## 3. Comment ça marche en détail

### Deux choses séparées : le fichier (git) et l'état "appliqué" (DB)

Le fichier de migration est partagé par git, identique pour toute l'équipe. L'état "cette migration a tourné ou pas" est stocké dans la table `doctrine_migration_versions`, **qui vit dans chaque base de données** — la tienne, celle d'un collègue, celle de la CI, celle de prod, chacune indépendante. Git ne synchronise jamais cette table, seulement les fichiers `.php`. C'est pour ça qu'un même fichier de migration doit être rejoué (`migrate`) séparément sur chaque environnement/machine — il n'est "appliqué" nulle part tant que personne n'a lancé la commande sur cette DB précise.

### Pourquoi jamais `make:migration`/`diff` dans les scripts d'équipe

Ces deux commandes comparent tes entités **locales** à ta DB **locale**. Si un script les lançait automatiquement pour tout le monde à chaque `pull`, un dev dont les entités sont temporairement en avance ou en retard sur sa DB (cas fréquent juste après un merge, avant le `migrate`) générerait une migration parasite — un diff qui ne reflète pas une vraie intention de changement de schéma, juste un décalage transitoire. `pull.sh`/`new-branch.sh`/`sync-main.sh` ne font donc que `migrate` (rejouer ce qui existe déjà), jamais `diff`/`make:migration` (créer du nouveau) — ce geste reste toujours manuel et déclenché par la personne qui modifie réellement une entité.

### Ordre d'application : timestamp, pas ordre de merge

Deux migrations créées en parallèle sur deux branches différentes s'appliquent, une fois mergées, dans l'ordre de leur nom de fichier (`Version20260809142006` avant `Version20260809145248`, peu importe laquelle a été mergée en premier sur `main`). Rien ne détecte automatiquement un conflit de schéma entre deux migrations parallèles (ex: les deux ajoutent une colonne du même nom sur la même table) — Doctrine les exécute l'une après l'autre, et la deuxième échoue au moment du `migrate` si elle est réellement incompatible avec l'état laissé par la première. À tester en local avec une DB fraîche si deux migrations touchent la même table à peu d'intervalle.

### Pourquoi le mapping est en attributs PHP et pas en YAML/XML

Pas un choix propre à cette doc — déjà fixé dans `config/packages/doctrine.yaml` (`mappings.App.type: attribute`), cohérent avec les usages actuels de Symfony (YAML/XML restent supportés mais sont la voie legacy). Toute nouvelle entité doit suivre ce mapping par attributs `#[ORM\...]`, pas une définition YAML séparée dans `config/`.
