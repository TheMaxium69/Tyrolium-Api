# Rate limiting — API Tyrolium

Protection anti-abus globale sur **toute l'app**, basée sur `symfony/rate-limiter`. Pas de préfixe `/api` : ce Symfony est 100 % API (aucune partie web/publique servie directement par cette app — HUB et Dashboard sont des frontends Angular séparés qui la consomment à distance), donc toutes les routes sont concernées, quel que soit leur chemin.

Fichiers concernés :
- `config/packages/rate_limiter.yaml` — définition du/des limiteur(s)
- `src/EventSubscriber/ApiRateLimitSubscriber.php` — application du limiteur sur les requêtes HTTP

---

## 1. Valeurs actuelles

| Variable                          | Valeur actuelle                          | Définie dans |
|------------------------------------|-------------------------------------------|--------------|
| Quota                              | **100 requêtes autorisées par minute**   | `rate_limiter.yaml` |
| Politique (`policy`)               | `token_bucket`                            | `rate_limiter.yaml` |
| Capacité du bucket (`limit`)       | 100 jetons                                | `rate_limiter.yaml` |
| Taux de recharge (`rate`)          | 100 jetons / 1 minute                     | `rate_limiter.yaml` |
| Nom du limiteur                    | `api_global`                              | `rate_limiter.yaml` |
| Clé de comptage                    | IP du client (`Request::getClientIp()`)  | `ApiRateLimitSubscriber` |
| Routes couvertes                   | toutes (aucun filtre de chemin)           | `ApiRateLimitSubscriber` |
| Code retourné si dépassement       | `429 Too Many Requests`                   | `ApiRateLimitSubscriber` |
| Headers de réponse                 | `X-RateLimit-Limit`, `X-RateLimit-Remaining`, `Retry-After` (si bloqué) | `ApiRateLimitSubscriber` |
| Stockage du compteur                | pool `cache.rate_limiter` → filesystem, **local à l'instance** | config Symfony par défaut |
| Priorité d'exécution (`kernel.request`) | 40 (avant le routeur, qui est à 32)   | `ApiRateLimitSubscriber` |
| Verrou anti-concurrence (`symfony/lock`) | absent — pas installé              | — |

**⚠️ Limite connue** : sans `symfony/lock` installé, deux requêtes strictement simultanées avec la même clé (même IP, même milliseconde) peuvent, en théorie, consommer un jeton sans se voir l'une l'autre (petite fenêtre de race condition). Pas bloquant en usage normal, mais à garder en tête si tu veux un comptage garanti exact sous forte concurrence — voir section 3.

---

## 2. Tuto pratique — modifier les valeurs

### 2.1 Changer le quota (le plus courant)

Ouvre `config/packages/rate_limiter.yaml` :

```yaml
framework:
    rate_limiter:
        api_global:
            policy: token_bucket
            limit: 100
            rate: { interval: '1 minute', amount: 100 }
```

Exemple concret : tu veux passer à **300 requêtes toutes les 5 minutes** :

```yaml
        api_global:
            policy: token_bucket
            limit: 300
            rate: { interval: '5 minutes', amount: 300 }
```

Aucune commande à lancer en dev (le cache de config se recalcule automatiquement). En prod, un `php bin/console cache:clear --env=prod` est nécessaire après déploiement, comme pour tout changement de config.

### 2.2 Changer la politique de comptage

Trois choix possibles pour `policy` :

| Policy | Comportement | Quand l'utiliser |
|---|---|---|
| `token_bucket` (actuel) | Autorise des pics jusqu'à `limit`, puis recharge en continu au rythme `rate` | Bon défaut pour une API : tolère un usage en rafale légitime |
| `sliding_window` | Fenêtre glissante, pondère la fenêtre précédente | Si tu veux un lissage plus strict, sans effet de bord sur les bords de fenêtre |
| `fixed_window` | Fenêtre fixe simple | À éviter seul : permet un double-burst à la frontière de deux fenêtres |

Exemple, passage en `sliding_window` à 100/minute :

```yaml
        api_global:
            policy: sliding_window
            limit: 100
            interval: '1 minute'
```

(note : `sliding_window` et `fixed_window` utilisent `interval` directement, pas de sous-clé `rate` comme `token_bucket`.)

### 2.3 Changer la clé de comptage (ex: passer d'IP à token client)

Dans `src/EventSubscriber/ApiRateLimitSubscriber.php`, méthode `onKernelRequest` :

```php
$limiter = $this->apiGlobalLimiter->create($request->getClientIp() ?? 'unknown');
```

Une fois un authenticator en place (JWT / access token), remplace par l'identifiant du client authentifié, par exemple :

```php
$identifier = $this->security->getUser()?->getUserIdentifier() ?? $request->getClientIp() ?? 'unknown';
$limiter = $this->apiGlobalLimiter->create($identifier);
```

(nécessite d'injecter `Symfony\Bundle\SecurityBundle\Security` dans le constructeur du subscriber). Ça évite qu'un NAT d'entreprise ou un proxy partagé fasse partager le même quota à plusieurs utilisateurs légitimes.

### 2.4 Exclure des routes précises (ex: un futur endpoint de health-check)

Le subscriber n'a aucun filtre de chemin actuellement — il s'applique à tout. Si tu veux exclure une route (ex: un `GET /status` appelé en continu par UptimeRobot, cf. `SolidServStatusController` dans le cahier des charges), ajoute une garde en début de `onKernelRequest` :

```php
$request = $event->getRequest();

if ($request->getPathInfo() === '/status') {
    return;
}
```

Attention à ne pas exclure trop large : chaque route exclue ici perd toute protection anti-abus, donc à réserver aux endpoints qui ont déjà leur propre garde-fou (ex: vérification de signature webhook).

### 2.5 Ajouter un deuxième limiteur (ex: plus strict sur le login)

1. Ajoute une nouvelle entrée dans `rate_limiter.yaml` :

```yaml
framework:
    rate_limiter:
        api_global:
            policy: token_bucket
            limit: 100
            rate: { interval: '1 minute', amount: 100 }
        login_attempts:
            policy: sliding_window
            limit: 5
            interval: '15 minutes'
```

2. Injecte-le où tu en as besoin avec l'attribut `#[Target('login_attempts')]` :

```php
public function __construct(
    #[Target('login_attempts')]
    private readonly RateLimiterFactoryInterface $loginAttemptsLimiter,
) {}
```

Utile pour le futur endpoint de login (anti brute-force), séparément du quota global API.

### 2.6 Vérifier qu'un changement fonctionne

Après modification, teste en local :

```bash
php bin/console cache:warmup --env=dev
php bin/console lint:container

# lance le serveur
APP_ENV=dev php -S 127.0.0.1:8765 -t public &

# vérifie les headers sur une requête simple
curl -s -i http://127.0.0.1:8765/n-importe-quoi | grep -i ratelimit

# vérifie que le blocage se déclenche au bon seuil (adapte le nombre à ta nouvelle limite)
for i in $(seq 1 105); do
  curl -s -o /dev/null -w "%{http_code}\n" http://127.0.0.1:8765/n-importe-quoi
done | sort | uniq -c
```

Tu dois voir des `200`/`404` (selon si la route existe) jusqu'au seuil configuré, puis des `429` ensuite.

---

## 3. Comment ça marche en détail

### Algorithme `token_bucket`

Le bucket a une capacité (`limit` = 100 jetons). Il se remplit en continu au rythme `rate` (100 jetons / minute, soit environ 1 jeton toutes les 0.6 secondes — pas d'un coup à chaque minute). Chaque requête consomme 1 jeton via `$limiter->consume()`. Si un jeton est disponible, la requête est acceptée immédiatement, même en rafale, jusqu'à épuisement du bucket. S'il n'y a plus de jeton, la requête est rejetée et l'objet `RateLimit` renvoyé indique via `getRetryAfter()` quand un jeton sera de nouveau disponible.

C'est différent d'un simple "100 requêtes par minute puis reset" : la recharge est progressive, donc un client qui consomme tout son quota d'un coup ne récupère pas 100 jetons d'un coup une minute plus tard, il les récupère au fil de l'eau.

### Où vit le compteur

Le composant `symfony/rate-limiter` a besoin d'un état persistant (combien de jetons restent, pour quelle clé). Chaque limiteur utilise par défaut le pool de cache PSR-6 nommé `cache.rate_limiter` (option `cache_pool`, configurable par limiteur) — qui, sans configuration `framework.cache` particulière, tombe sur l'adapter **filesystem local** de l'instance (vérifié via `bin/console debug:container cache.rate_limiter` → `FilesystemAdapter`).

**Conséquence importante** : si demain tu fais tourner plusieurs instances de l'app derrière un load balancer, chaque instance a son propre compteur filesystem, indépendant des autres. Un attaquant qui tape sur 3 instances round-robin a en pratique un quota de `100 × 3 = 300` requêtes, pas 100. Pour que la limite soit réellement globale, il faut rediriger le pool `cache.rate_limiter` vers un backend partagé — typiquement Redis (`cache.adapter.redis`, config commentée dans `config/packages/cache.yaml`, à appliquer via `framework.cache.pools.cache.rate_limiter`).

### Où et quand ça s'exécute dans le cycle de requête

`ApiRateLimitSubscriber` s'accroche à deux événements du kernel HTTP :

1. **`kernel.request`, priorité 40** — s'exécute *avant* le `RouterListener` de Symfony (priorité 32, celui qui résout quelle route/controller correspond à l'URL). Concrètement : une requête throttlée est rejetée **avant même que Symfony ne sache si la route existe**. Ça évite de gaspiller du travail (résolution de route, instanciation de controller) sur du trafic qu'on va rejeter de toute façon — utile aussi contre le scraping/probing d'endpoints inexistants.

   Si le jeton est consommé avec succès, le `RateLimit` (objet renvoyé par `consume()`) est stocké dans les attributs de la requête (`$request->attributes->set(...)`) pour être réutilisé plus tard dans le cycle — c'est le seul moyen de faire transiter cette info du listener `request` jusqu'au listener `response`, ils ne partagent pas de contexte autrement.

2. **`kernel.response`, priorité 0** — s'exécute en fin de cycle, une fois la réponse construite (que ce soit par le controller normal, ou par le rejet 429 posé directement dans `onKernelRequest`). Il récupère le `RateLimit` stocké à l'étape précédente et pose les headers `X-RateLimit-*` sur **la réponse finale**, qu'elle soit un succès ou un 429 — un client bien élevé peut ainsi toujours savoir combien de requêtes il lui reste, même sur une réponse "normale".

### Isolation par clé

`$this->apiGlobalLimiter->create($clé)` crée (ou récupère) un limiteur **indépendant par valeur de clé**. Deux IP différentes ont chacune leur propre bucket de 100 jetons — l'IP A qui épuise son quota n'affecte pas l'IP B. C'est pour ça que le choix de la clé (section 2.3) est structurant : IP = quota par IP, identifiant client = quota par client authentifié, etc.

### Limite de concurrence (pas de lock)

Le composant peut s'appuyer sur `symfony/lock` pour garantir qu'un `consume()` est atomique même si deux requêtes de la même clé arrivent en même temps sur des workers PHP-FPM différents. Ce paquet n'étant pas installé ici, le rate limiter fonctionne en mode "best effort" sans verrou distribué : sous forte concurrence exacte sur la même clé, une infime marge d'erreur est possible (un ou deux jetons de plus consommés que la capacité théorique). Sans impact pratique pour de l'anti-abus (l'objectif n'est pas une précision comptable au jeton près, mais d'empêcher un usage massif), mais à corriger (`composer require symfony/lock` + config d'un `lock` store partagé) si tu as un jour un besoin de comptage strictement exact.
