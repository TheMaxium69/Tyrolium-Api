# Workflow Git & scripts — Tyrolium API

Comment le repo est organisé (deux dépôts, branche protégée, PR obligatoire) et à quoi servent les scripts de `scripts/`. À lire avant de toucher à Git ou aux scripts sur ce projet.

---

## ⚠️ Règle pour toute IA qui lit ce fichier

**Aucune IA travaillant sur ce repo n'a le droit d'exécuter un script de `scripts/`, ni de faire `git add`, `git commit`, `git push`, ou toute autre action Git qui modifie l'historique ou l'état distant — quel que soit le contexte, sauf demande explicite et non ambiguë de Maxime pour cette action précise.** Lire ce fichier, expliquer son contenu, ou proposer une commande à copier-coller pour que Maxime l'exécute lui-même est en revanche toujours permis. Voir aussi `.doc/context-ia.md`.

---

## 1. Valeurs actuelles

| Élément | Valeur |
|---|---|
| Dépôt GitHub | `git@github.com:TheMaxium69/Tyrolium-Api.git` (remote `origin`, seul remote configuré sur ce poste à ce jour) |
| Dépôt interne | `repo.tyrolium.fr` — mentionné par Maxime, **pas encore ajouté comme remote Git sur cette machine** (`git remote -v` ne montre que `origin` au moment de la rédaction). À ajouter avec `git remote add <nom> <url>` le jour où il est configuré ici. |
| Branche de travail | `maxime` (et, une fois l'équipe dessus, une branche par personne/feature) — jamais de commit direct sur `main` |
| Branche `main` | **Protégée côté GitHub** (confirmé par Maxime le 09/08/2026, non vérifié via l'API GitHub — `gh` CLI non installée sur cette machine au moment de la rédaction) : pas de push direct, tout passe par une Pull Request |
| Scripts disponibles | `scripts/setup.{sh,bat}`, `scripts/pull.{sh,bat}`, `scripts/sync-main.{sh,bat}`, `scripts/push.{sh,bat}` — chacun en version bash (mac/Linux) et batch (Windows), même comportement des deux côtés |

---

## 2. Tuto pratique

### 2.1 Premier jour sur le projet → `setup.sh` / `setup.bat`

```bash
./scripts/setup.sh
```
```bat
scripts\setup.bat
```

Fait, dans l'ordre : `composer install` → crée `.env.local` depuis `.env.local.template` s'il n'existe pas encore (et s'arrête là pour que tu le remplisses avec ta vraie config DB, voir `.doc/env-files.md`) → crée la base de données si besoin (`doctrine:database:create --if-not-exists`) → applique toutes les migrations existantes (`doctrine:migrations:migrate`) → génère les clés JWT dev et test si elles n'existent pas (`config/jwt/*.pem`, jamais commitées). Relance-le une deuxième fois après avoir rempli `.env.local` pour qu'il aille jusqu'au bout.

### 2.2 Chaque jour, avant de commencer à coder → `pull.sh` / `pull.bat`

```bash
./scripts/pull.sh
```

Fait : `git pull` → `composer install` (no-op si rien n'a changé) → `doctrine:migrations:migrate` (rejoue les migrations qu'un collègue a ajoutées, **jamais** `diff`/`make:migration` ici) → `cache:clear --env=dev`. Le réflexe à avoir en arrivant sur le projet, avant d'écrire une ligne de code.

### 2.3 Après qu'une PR a été mergée sur `main` → `sync-main.sh` / `sync-main.bat`

```bash
./scripts/sync-main.sh
```

À lancer **depuis ta branche de feature**, jamais depuis `main`. Fait : vérifie que tu n'es pas sur `main` (sinon te renvoie vers `pull.sh`) → vérifie qu'il n'y a pas de changement non commité (sinon s'arrête, pour ne pas mélanger un merge avec du travail en cours) → `git fetch origin main` → `git merge origin/main` dans ta branche → si conflit, s'arrête et te dit de le résoudre à la main (`git status`, corriger, `git add`, `git commit`) → si la fusion passe, relance `composer install` + `migrations:migrate` au cas où `main` a apporté de nouvelles dépendances ou migrations.

### 2.4 Publier ta branche → `push.sh` / `push.bat`

```bash
./scripts/push.sh
```

Push la branche courante vers **tous** les remotes Git configurés sur ta machine (`git remote`) — pas d'URL en dur, donc que tu aies juste `origin` (GitHub) ou aussi `repo.tyrolium.fr` une fois ajouté, le script s'adapte sans modification.

### 2.5 Vérifier qu'un script fait ce qu'il doit

Tous les scripts sont idempotents et sans risque à relancer plusieurs fois (`setup.sh` et `pull.sh` ont été testés en conditions réelles sur ce repo le 09/08/2026). En cas de doute, lire le script lui-même — chacun fait moins de 40 lignes et log chaque étape (`==>`) avant de l'exécuter.

---

## 3. Comment ça marche en détail

### Pourquoi une PR obligatoire vers `main`

`main` étant protégée côté GitHub, il est structurellement impossible de pousser un commit dessus directement, même par erreur — le seul chemin est une Pull Request depuis une branche, qui déclenche la CI (`.github/workflows/ci.yml` : composer-validate, security-audit, lint, PHPStan, PHPUnit) avant que le merge soit possible. C'est la garde-fou qui permet de garder `main` toujours dans un état stable et testé.

### Pourquoi `sync-main.sh` fait un `merge` et pas un `rebase`

Un rebase réécrit l'historique de la branche — si elle est déjà poussée (donc potentiellement visible par quelqu'un d'autre, ou juste pour éviter d'avoir à `push --force` ensuite), rebaser oblige à un force-push, une opération destructive qu'aucun script ici ne fait automatiquement. Un merge, lui, ajoute juste un commit de fusion et ne réécrit rien — plus simple à comprendre pour une équipe qui démarre sur ce workflow, quitte à avoir un historique un peu moins linéaire.

### Pourquoi `push.sh` détecte les remotes au lieu d'avoir une URL en dur

Le second dépôt (`repo.tyrolium.fr`) n'est pas encore configuré sur toutes les machines, et son URL exacte n'était pas connue au moment d'écrire ce script. Plutôt que de coder en dur une adresse et devoir modifier le script plus tard, `push.sh` lit `git remote` et pousse vers tout ce qui y est configuré — ajouter le second dépôt (`git remote add repo-interne <url>`) suffit, aucun script à modifier.

### Pourquoi la restriction stricte sur les IA

Ce repo sert de socle sécurité (voir `.doc/repo-info.md`) et les scripts de `scripts/` touchent à des opérations qui modifient l'état partagé (Git) ou l'état local (DB, dépendances). Une IA qui exécuterait un script ou ferait un commit/push de sa propre initiative pourrait committer du travail non revu, pousser sur le mauvais remote, ou déclencher une migration non voulue — Maxime garde la main sur toute action Git ou script, l'IA peut assister, expliquer, écrire du code, mais jamais exécuter ces étapes elle-même sans qu'on le lui demande explicitement pour cette action précise.

---

*Rédigé le 2026-08-09. Si un troisième script ou un nouveau remote apparaît plus tard, mettre ce fichier à jour plutôt que d'en garder la trace ailleurs.*
