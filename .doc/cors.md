# CORS — API Tyrolium

Géré par `nelmio/cors-bundle`. But : autoriser les frontends Angular (HUB, Dashboard) à appeler cette API depuis un autre domaine, sans ouvrir en grand à n'importe quelle origine.

Fichiers concernés :
- `config/packages/nelmio_cors.yaml` — règles CORS (méthodes, headers, credentials, périmètre)
- `.env` — `CORS_ALLOW_ORIGIN`, la liste des origines autorisées (regex)

---

## 1. Valeurs actuelles

| Variable | Valeur actuelle | Définie dans |
|---|---|---|
| Origines autorisées (`CORS_ALLOW_ORIGIN`) | `^https?://(localhost\|127\.0\.0\.1)(:[0-9]+)?$` — **dev only** | `.env` |
| Mode de matching (`origin_regex`) | `true` (la valeur ci-dessus est une regex, pas une liste exacte) | `nelmio_cors.yaml` |
| Méthodes autorisées | `GET, OPTIONS, POST, PUT, PATCH, DELETE` | `nelmio_cors.yaml` |
| Headers autorisés en requête | `Content-Type, Authorization` | `nelmio_cors.yaml` |
| Headers exposés en réponse | `Link` | `nelmio_cors.yaml` |
| Credentials (cookies/auth via cookie) | `false` (par défaut du bundle, non surchargé) | `nelmio_cors.yaml` |
| Durée de cache du preflight (`max_age`) | 3600s (1h) | `nelmio_cors.yaml` |
| Routes couvertes (`paths`) | `^/` — toute l'app (cohérent avec le firewall/rate-limiter, voir `.doc/rate-limit.md`) | `nelmio_cors.yaml` |

**⚠️ À AJOUTER — ce qui manque encore, indispensable avant tout déploiement hors local :**

Le `.env` généré par défaut n'autorise que `localhost`/`127.0.0.1`. **Aucun domaine de prod n'est configuré.** D'après le cahier des charges (`.doc/Cahier des Charges...pdf`), les deux frontends qui consommeront cette API sont :
- `https://hub.tyrolium.fr` (Tyrolium HUB, interne)
- `https://dashboard.useritium.fr` (Useritium Dashboard, public)

Il faudra aussi connaître **le port exact du serveur de dev Angular** (`ng serve` — souvent `4200`, mais à confirmer, `tyrolium-ui`/HUB/Dashboard peuvent avoir une config différente) pour être sûr que le regex dev actuel matche bien.

Rien de tout ça n'est deviné ou ajouté à ta place ici — voir section 2 pour l'ajouter proprement le moment venu.

---

## 2. Tuto pratique — ajouter une origine

### 2.1 Ajouter les domaines de prod (hub + dashboard)

Dans `.env` (ou mieux, en variable d'environnement serveur en prod plutôt qu'en dur dans `.env` — voir `.doc/how-to-document.md` / convention secrets), étends la regex avec une alternative `|` :

```
CORS_ALLOW_ORIGIN='^https?://(localhost|127\.0\.0\.1)(:[0-9]+)?$|^https://(hub\.tyrolium\.fr|dashboard\.useritium\.fr)$'
```

Ou, plus lisible, sépare dev et prod par environnement (`.env.prod` committé, sans le `localhost` — cohérent avec la convention `.env.$APP_ENV` vue précédemment) :

```
# .env.prod
CORS_ALLOW_ORIGIN='^https://(hub\.tyrolium\.fr|dashboard\.useritium\.fr)$'
```

Pas de `localhost` en prod : chaque environnement n'autorise que ses origines légitimes.

### 2.2 Ajouter une origine ponctuelle (ex: un nouvel environnement de staging)

Ajoute une alternative dans la regex existante :

```
CORS_ALLOW_ORIGIN='^https?://(localhost|127\.0\.0\.1)(:[0-9]+)?$|^https://staging\.hub\.tyrolium\.fr$'
```

### 2.3 Autoriser un nouveau header custom (ex: un header applicatif type `X-Tyrolium-Client`)

Dans `config/packages/nelmio_cors.yaml` :

```yaml
nelmio_cors:
    defaults:
        allow_headers: ['Content-Type', 'Authorization', 'X-Tyrolium-Client']
```

### 2.4 Activer les credentials (si un jour l'auth passe par cookie httpOnly)

**Ne pas faire ça sans y réfléchir** — voir section 3 pour l'implication sécurité (CSRF). Si nécessaire :

```yaml
nelmio_cors:
    defaults:
        allow_credentials: true
```

Attention : avec `allow_credentials: true`, une origine `*` est interdite par la spec CORS elle-même (le bundle lève une erreur), donc `CORS_ALLOW_ORIGIN` doit rester une liste stricte de domaines exacts — c'est déjà le cas ici.

### 2.5 Vérifier qu'un changement fonctionne

```bash
php bin/console cache:warmup --env=dev
APP_ENV=dev php -S 127.0.0.1:8765 -t public &

# requete preflight depuis une origine qui doit passer
curl -s -i -X OPTIONS http://127.0.0.1:8765/n-importe-quelle-route \
  -H "Origin: https://hub.tyrolium.fr" \
  -H "Access-Control-Request-Method: POST" \
  -H "Access-Control-Request-Headers: Authorization" \
  | grep -i access-control

# requete preflight depuis une origine qui ne doit PAS passer
curl -s -i -X OPTIONS http://127.0.0.1:8765/n-importe-quelle-route \
  -H "Origin: https://evil.example.com" \
  -H "Access-Control-Request-Method: POST" \
  -H "Access-Control-Request-Headers: Authorization" \
  | grep -i access-control
```

Le premier doit renvoyer un header `Access-Control-Allow-Origin: <ton origine>`. Le second doit renvoyer **200 OK mais sans header `Access-Control-Allow-Origin`** — c'est le comportement normal (le serveur ne "refuse" pas explicitement, c'est le navigateur du client qui bloquera la réponse faute du bon header). Testé en conditions réelles sur ce projet le 2026-08-09, comportement confirmé.

---

## 3. Comment ça marche en détail

### Le rôle du preflight `OPTIONS`

Pour toute requête "non simple" (méthode autre que GET/HEAD/POST simple, ou avec un header custom comme `Authorization`), le navigateur envoie d'abord une requête `OPTIONS` automatique avant la vraie requête, avec les headers `Origin`, `Access-Control-Request-Method` et `Access-Control-Request-Headers`. `nelmio/cors-bundle` intercepte cette requête (`CorsListener` sur `kernel.request`, en priorité très haute — avant même le rate limiter et le firewall) et répond directement, sans jamais atteindre le controller. Si la réponse contient les bons headers `Access-Control-Allow-*`, le navigateur envoie ensuite la vraie requête ; sinon il bloque côté client, l'API ne voit même pas d'erreur.

### Pourquoi le serveur répond 200 même à une origine refusée

Le serveur HTTP ne "sait" pas vraiment refuser une requête CORS — CORS est une politique **appliquée par le navigateur**, pas par le serveur. Le bundle répond toujours 200 à un preflight, mais n'ajoute le header `Access-Control-Allow-Origin` que si l'origine matche `CORS_ALLOW_ORIGIN`. C'est le navigateur qui, en l'absence de ce header (ou s'il ne correspond pas à l'origine appelante), bloque la lecture de la réponse par le JS appelant. Un `curl` direct ou un appel serveur-à-serveur n'est **jamais** bloqué par CORS — cette protection ne protège que le contexte navigateur, pas les appels API-à-API.

### `allow_credentials: false` — pourquoi c'est le bon choix ici, pour l'instant

Cette API n'a pas encore d'authenticator (voir `.doc` sur le firewall stateless). L'hypothèse de travail est une auth par token dans le header `Authorization`, pas par cookie de session — dans ce cas, `allow_credentials` (qui contrôle si les cookies/auth HTTP sont inclus dans les requêtes cross-origin) n'a pas besoin d'être activé : le header `Authorization` passe très bien sans lui, du moment qu'il est listé dans `allow_headers` (c'est le cas). Si un jour l'auth passe par un cookie httpOnly (protection XSS), il faudra activer `allow_credentials: true` **et** repasser en CSRF-aware côté firewall (voir le lien entre les deux dans `.doc` du firewall stateless) — les deux décisions sont couplées, l'une ne va pas sans l'autre.

### Pourquoi une regex plutôt qu'une liste stricte

`origin_regex: true` permet à `CORS_ALLOW_ORIGIN` de matcher un pattern (utile pour couvrir tous les ports possibles d'un serveur de dev local, ou un sous-domaine variable type preview de PR). Le risque : une regex mal écrite peut être plus permissive que prévu (ex: oublier d'ancrer avec `^...$` autoriserait `evil-hub.tyrolium.fr.attacker.com` si le `.` n'est pas échappé et l'ancrage absent). La regex actuelle est bien ancrée (`^` et `$`) et le `.` des IP/domaines est échappé (`\.`) — à vérifier avec la même rigueur à chaque ajout (section 2.1/2.2).

### Ordre d'exécution vs firewall / rate limiter

`nelmio_cors.cors_listener` s'accroche à `kernel.request` en **priorité 250** (vérifié dans `vendor/nelmio/cors-bundle/Resources/config/services.php`) — bien avant `ApiRateLimitSubscriber` (40) ou le `RouterListener` du firewall (32). Sur un preflight, il appelle `$event->setResponse(...)`.

Point précis à connaître : `Symfony\Component\HttpKernel\Event\RequestEvent::setResponse()` appelle `stopPropagation()` en interne (`vendor/symfony/http-kernel/Event/RequestEvent.php`). Ce n'est donc pas juste "une histoire de priorité plus haute" : dès qu'un preflight reçoit sa réponse par Nelmio, **la propagation de l'événement `kernel.request` s'arrête net** — aucun listener suivant ne s'exécute, ni le rate limiter, ni le firewall, ni la résolution de route. Vérifié en réel le 2026-08-09 : un preflight `OPTIONS` ne renvoie **aucun** header `X-RateLimit-*`, alors qu'une requête normale en renvoie systématiquement (cf. `.doc/rate-limit.md`) — preuve que `ApiRateLimitSubscriber::onKernelRequest` n'est jamais atteint sur un preflight.
