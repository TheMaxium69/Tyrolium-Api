# Secrets Vault — API Tyrolium

Comment ajouter un vrai secret (mot de passe DB de prod, clé Stripe/Lemon Squeezy, secret de signature JWT...) sans jamais le committer en clair. Approche retenue pour ce projet : **le Vault Symfony, pas de variable d'environnement serveur brute**, sauf pour ce que l'hébergeur fournit lui-même dynamiquement (ex: une DB managée qui génère sa propre URL).

Complète `.doc/env-files.md` (qui couvre les fichiers `.env*`) — le Vault est le troisième mécanisme, pour les vrais secrets partagés/déployés.

---

## 1. Valeurs actuelles

| Élément | État actuel |
|---|---|
| Vault initialisé ? | **Non** — `config/secrets/` est vide, aucune paire de clés générée, aucun environnement (`dev`/`test`/`prod`) n'a de vault |
| Secrets stockés | Aucun |
| Protection git de la clé privée | `.gitignore` exclut uniquement `config/secrets/prod/prod.decrypt.private.php` |
| Environnements autorisés | `prod`, `dev`, `test` uniquement (`App\Kernel::getAllowedEnvs()`) — impossible de créer un vault pour un nom d'environnement arbitraire |

**Sur la protection git** : seule la clé privée de **prod** est exclue par défaut. Si tu génères un jour des clés pour `dev`/`test`, leur clé privée serait committée par défaut — **c'est voulu**, même logique que `.env.dev`/`.env.test` (secrets non sensibles vus précédemment) : un vault de dev/test n'a jamais protégé de donnée réellement déployée, donc pas d'enjeu à ce qu'il soit lisible dans le repo. Seule la clé privée de **prod** doit rester hors de git.

---

## 2. Tuto pratique

### 2.1 Initialiser le vault d'un environnement

```bash
php bin/console secrets:generate-keys --env=prod
```

Génère deux fichiers dans `config/secrets/prod/` :
- `prod.encrypt.public.php` — clé publique, **committée**, sert uniquement à chiffrer
- `prod.decrypt.private.php` — clé privée, **jamais committée** (déjà dans `.gitignore`), à garder uniquement sur le serveur de prod (ou dans un coffre séparé type 1Password si tu veux pouvoir la ressortir)

Sans `--env`, la commande cible l'environnement courant (`dev` par défaut en local).

### 2.2 Ajouter un secret

```bash
php bin/console secrets:set STRIPE_SECRET_KEY --env=prod
```

Te demande la valeur de façon interactive (rien ne s'affiche à l'écran en tapant). Alternative en pipe, pratique pour un script/CI :

```bash
echo -n "sk_live_xxx" | php bin/console secrets:set STRIPE_SECRET_KEY - --env=prod
```

Pour générer une valeur aléatoire directement (utile pour un `APP_SECRET` ou une clé de signature) :

```bash
php bin/console secrets:set JWT_SIGNING_KEY --random=64 --env=prod
```

Ça écrit un fichier chiffré `config/secrets/prod/prod.STRIPE_SECRET_KEY.<hash>.php` — **ce fichier est fait pour être committé**, il est illisible sans la clé privée.

### 2.3 Utiliser le secret dans la config

Exactement la même syntaxe qu'une variable `.env` ou une vraie variable serveur :

```yaml
# config/packages/quelque_chose.yaml
some_service:
    api_key: '%env(STRIPE_SECRET_KEY)%'
```

Aucune différence de code entre les trois sources — c'est transparent.

### 2.4 Vérifier

```bash
php bin/console secrets:list --reveal --env=prod
```

Affiche tous les secrets et leur valeur en clair — nécessite d'avoir la clé privée en local (donc ça ne marchera que sur la machine/serveur qui l'a). Sans `--reveal`, seuls les noms sont listés, pas les valeurs.

Testé en réel pendant la rédaction de cette doc (avec un environnement `test` jetable, nettoyé après) : `secrets:set` puis `secrets:list --reveal` renvoient bien la valeur d'origine en clair — le chiffrement/déchiffrement round-trip fonctionne.

### 2.5 Retirer un secret

```bash
php bin/console secrets:remove STRIPE_SECRET_KEY --env=prod
```

### 2.6 Tester une valeur différente en local sans toucher au vault partagé

```bash
php bin/console secrets:set STRIPE_SECRET_KEY --local --env=prod
```

Crée un override **local**, non committé (vault local séparé du vault partagé), qui prend le dessus uniquement sur ta machine. Utile pour tester une clé Stripe de test sans modifier le vrai secret de prod que l'équipe partage.

### 2.7 Piège à connaître : le Vault n'est jamais prioritaire

Si `STRIPE_SECRET_KEY` existe déjà dans `.env`, `.env.local` ou une vraie variable d'environnement serveur, **le Vault est ignoré silencieusement** pour ce nom-là (voir section 3 pour le mécanisme exact). Concrètement : ne laisse jamais le même nom de variable défini à la fois dans un fichier `.env*` committé et dans le Vault — vérifie avec `bin/console debug:dotenv` (cf. `.doc/env-files.md`) que le nom n'apparaît nulle part ailleurs avant de te fier à la valeur du Vault.

---

## 3. Comment ça marche en détail

### Chiffrement asymétrique (libsodium)

`secrets:generate-keys` génère une paire de clés Sodium : la clé publique chiffre, la clé privée déchiffre. `secrets:set` chiffre la valeur avec la clé publique et écrit le résultat dans un fichier PHP (`config/secrets/{env}/{env}.{NOM}.{hash}.php`) — committable sans risque, illisible sans la clé privée correspondante. Un fichier d'index (`{env}.list.php`) garde la liste des noms de secrets présents.

### Isolation par environnement

Chaque environnement (`dev`, `test`, `prod` — les seuls autorisés par `App\Kernel::getAllowedEnvs()`) a sa **propre** paire de clés, dans son propre sous-dossier `config/secrets/{env}/`. Un secret chiffré pour `prod` est illisible avec la clé de `dev` — même nom de secret, valeurs et clés totalement indépendantes d'un environnement à l'autre.

### Priorité réelle : Vault toujours en dernier recours

Vérifié directement dans le code source de `symfony/dependency-injection` (`EnvVarProcessor.php`, ~ligne 173) : la résolution d'une variable `%env(NOM)%` regarde d'abord `$_ENV`/`$_SERVER`/`getenv()` — c'est-à-dire une vraie variable d'environnement système **ou** une valeur chargée depuis un fichier `.env*` par le composant Dotenv (qui peuple `$_ENV` au démarrage). **Seulement si rien n'est trouvé à cette étape**, Symfony interroge les `EnvVarLoaderInterface` enregistrés — dont le Vault (`SodiumVault`) fait partie. Le Vault est donc structurellement une **source de dernier recours**, jamais prioritaire sur `.env`/`.env.local`/une vraie variable serveur. C'est cohérent avec l'ordre de chargement des fichiers `.env` déjà documenté dans `.doc/env-files.md` — le Vault s'ajoute tout en bas de cette hiérarchie, pas au milieu.

### Pourquoi la clé privée de prod ne doit vraiment jamais être committée

Elle est la seule chose qui protège tous les secrets de prod chiffrés dans le repo. Si elle fuit (committée par erreur, exposée dans un log, etc.), **tous** les secrets de prod chiffrés dans `config/secrets/prod/` deviennent lisibles par quiconque a accès au repo — il faudrait alors régénérer une nouvelle paire de clés et re-chiffrer tous les secrets existants avec la nouvelle clé publique. D'où l'entrée déjà présente dans `.gitignore` depuis le début du projet, et le message d'avertissement explicite que `secrets:generate-keys` affiche lui-même à l'écran ("DO NOT COMMIT THE DECRYPTION KEY FOR THE PROD ENVIRONMENT").
