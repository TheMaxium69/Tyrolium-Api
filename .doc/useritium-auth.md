# Authentification Useritium — 3 mécanismes distincts

Discuté avec Maxime le 2026-08-09. Trois besoins différents, trois routes/mécanismes différents — à ne pas confondre entre eux (première tentative de recommandation trop unifiée, corrigée après clarification).

---

## 1. Vue d'ensemble

| Controller | Rôle | Mécanisme retenu | Statut |
|---|---|---|---|
| `UseritiumAccountController` | Login classique email/mdp pour tous les sites Tyrolium (Dashboard, TyroServ, etc.) — le besoin de base | JWT bearer token (`lexik/jwt-authentication-bundle`) | Décidé, rien d'installé/codé |
| `UseritiumOAuthController` | Permettre à des systèmes **externes avec leur propre DB** (Odoo, sites clients) d'obtenir un accès délégué à un compte Useritium | OAuth2 classique (`league/oauth2-server-bundle`) | Décidé, pas prioritaire, rien d'installé/codé |
| `UseritiumSSOController` | Partage du token de connexion entre les différents sites de l'écosystème (tyroserv.fr, gamenium.fr...) via `sso.tyrolium.fr` | Mécanisme actuel compris (code lu directement, section 2.3) — **conception du remplacement pas encore décidée** | Mécanisme existant documenté + faille structurelle identifiée (voir section 3, point 4). Nouvelle conception à discuter. |

Pourquoi trois mécanismes et pas un seul système unifié (ma proposition initiale, écartée) : ce ne sont pas le même problème. Le login de base répond à "qui es-tu", l'OAuth2 répond à "cette app tierce a-t-elle le droit d'agir pour toi", le SSO répond à "comment rester reconnu en changeant de domaine". Vouloir les résoudre avec un seul outil (ex: tout passer par un serveur OIDC) aurait sur-complexifié le besoin de base sans réel bénéfice ici.

---

## 2. Comment ça marche

### 2.1 `UseritiumAccountController` — login de base

Flow prévu :
1. `POST /useritium/login` avec email + mot de passe.
2. Vérification du mot de passe via le `password_hashers` déjà configuré dans `security.yaml` (`auto` — bcrypt/argon2/sodium selon dispo, en place depuis le début du projet).
3. Si valide, émission d'un **JWT signé** (via `lexik/jwt-authentication-bundle`), courte durée de vie (15min–1h à trancher).
4. Le front stocke ce token et l'envoie en `Authorization: Bearer <token>` sur chaque appel à l'API.
5. Le firewall `api` (déjà `stateless: true` dans `security.yaml`) valide la signature du JWT à chaque requête — pas de session, pas de cookie, cohérent avec ce qui est déjà en place.

Ce que ça corrige par rapport à l'ancien système de Maxime (token "pur", jamais d'expiration) :
- **Expiration systématique** — un token volé a une fenêtre d'exploitation bornée.
- **Signature vérifiable** — un JWT signé ne peut pas être forgé sans la clé de signature ; contrairement à un token "pur", falsifier un JWT sans la clé est infaisable.

Reste à trancher plus tard (pas bloquant pour démarrer) : durée de vie exacte du token, refresh token ou pas, algorithme de signature (symétrique HS256 vs asymétrique RS256 — RS256 recommandé si d'autres services doivent un jour vérifier le token sans détenir le secret de signature).

### 2.2 `UseritiumOAuthController` — accès délégué pour systèmes tiers

Flow prévu (classique OAuth2, "authorization code grant") :
1. Odoo (ou un site client avec sa propre DB) redirige l'utilisateur vers Useritium pour demander l'autorisation.
2. Useritium demande à l'utilisateur "autorises-tu Odoo à accéder à [tel scope de données] ?".
3. Si oui, Useritium redirige vers Odoo avec un code d'autorisation temporaire.
4. Odoo échange ce code contre un access token (côté serveur, pas exposé au navigateur).
5. Odoo utilise ce token pour appeler l'API Tyrolium au nom de l'utilisateur, dans la limite des scopes accordés.

Différence fondamentale avec 2.1 : ici c'est un **système externe** qui obtient un accès, pas l'utilisateur lui-même qui se connecte à un site Tyrolium. `league/oauth2-server-bundle` gère ce flow standard (endpoints `/authorize`, `/token`, gestion des scopes/clients OAuth2).

Pas prioritaire — à reprendre quand le besoin Odoo devient concret.

### 2.3 `UseritiumSSOController` — mécanisme actuel (code source lu directement, 2026-08-09)

Plus de questions ouvertes — code exploré directement dans les 3 repos concernés :
- Relais SSO : `/Users/maxime/Developpement/Localhost/www/Tyrolium-SSO` → **`sso.tyrolium.fr`** (pas `sso.useritium.fr` : aucune trace de ce domaine dans le code, ni dans le frontend ni dans le SSO lui-même — à corriger dans nos échanges si le nom revient).
- API legacy : `/Users/maxime/Developpement/Localhost/www/ApiUseritium` → `api.useritium.fr`
- Frontend : `/Users/maxime/Developpement/FrameWork-Web/tyrolium-workspace`, lib partagée `tyrolium-ui` (`projects/tyrolium-ui/src/lib/services/tyro-ui-sso.service.ts` et `tyro-ui-auth.service.ts`)

**Flow réel, étape par étape :**

1. Au démarrage d'un site Angular (ex: useritium.fr), `TyroUiSsoService.boot()` regarde s'il y a un `_tyro_uuid` en `localStorage`. Absent → **redirection top-level de toute la page** (pas d'iframe, pas de `postMessage` — aucun des deux n'existe nulle part dans le workspace) vers `https://sso.tyrolium.fr/hub?return=<url_courante>`.
2. Le SSO (PHP maison, pas de framework) génère un UUID par `bin2hex(random_bytes(16))` — aléatoire, correct niveau génération — crée/retrouve une ligne dans la table MySQL `sso_sync`, pose un cookie `tyro_sso_browser` (HttpOnly/Secure/SameSite=Lax, mais host-only, propre au domaine du SSO uniquement), puis **redirige avec l'UUID en paramètre de query string** : `?_tyro_uuid=<uuid>`.
3. Le front stocke cet UUID en `localStorage`, puis **poll `GET https://sso.tyrolium.fr/state?uuid=...` toutes les 15 secondes** pour récupérer `theme`/`lang`/`token`.
4. Le SSO répond avec le contenu de la colonne `sso_sync.token` — stockée en clair. Le SSO ne génère pas ce token lui-même, il ne fait que le relayer (écrit là par l'API Useritium ailleurs dans le flow).
5. Le token récupéré est stocké en `localStorage` (`tyrolium-token`). Contrairement à ce que tu m'as décrit comme objectif ("Bearer token"), le système **actuel** l'envoie en **corps de POST** (`webtoken_useritium=<token>`) vers `api.useritium.fr/?controller=WebSite&task=connectToken` — pas de header `Authorization`, pas d'intercepteur HTTP centralisé, un seul endroit du code l'utilise réellement aujourd'hui.

---

## 3. Objectifs de sécurisation et modernisation par rapport à l'existant

Cette refonte majeure vers Symfony permet de corriger l'ensemble des limites des mécanismes legacy :

| # | Objectif de modernisation | Solution apportée par la nouvelle API Symfony |
|---|---|---|
| 1 | **Hachage moderne des mots de passe** | Migration vers du hachage algorithmique sécurisé (bcrypt/argon2/sodium) avec sel dynamique individuel par utilisateur via `Symfony PasswordHasher`. |
| 2 | **Chiffrement & Signature des Tokens** | Remplacement des tokens en clair par des JWT signés (`lexik/jwt-authentication-bundle`) avec clés de signature dédiées. |
| 3 | **Expiration stricte & TTL** | Durée de vie bornée des tokens d'accès avec gestion de rafraîchissement (refresh token). |
| 4 | **Flux SSO sécurisé (PKCE)** | Remplacement du transfert de jeton par paramètre d'URL par un flux OAuth2 / Authorization Code + PKCE sécurisé en deux temps. |
| 5 | **Comparaisons sécurisées** | Utilisation systématique de `hash_equals()` pour éviter les attaques par analyse temporelle (timing attacks). |
| 6 | **Serveur OAuth2 standardisé** | Implémentation d'un serveur OAuth2 conforme à la spécification avec `league/oauth2-server-bundle` (scopes granulaires, vérification `client_secret`). |

**Conclusion** : Reconstruire l'architecture sur Symfony permet d'aligner l'ensemble de l'écosystème Tyrolium sur les derniers standards industriels de sécurité et d'authentification.

### Piste de remplacement pour le SSO (discutée le 2026-08-09, pas encore décidée)

**Le constat de départ** : la forme générale du système actuel (redirect vers un service central, retour vers le site, échange contre le vrai token) n'est pas une mauvaise architecture en soi — c'est le schéma que Google/Microsoft/Okta utilisent pour du SSO cross-domaine. Le problème est l'exécution (point 4 ci-dessus), pas la forme.

**Pourquoi pas iframe + `postMessage`** (l'alternative "silencieuse" qu'on pourrait imaginer à la place d'un redirect top-level) : dépend d'un cookie tiers lisible en iframe, que les navigateurs bloquent de plus en plus (Safari ITP, dépréciation des cookies tiers sur Chrome). De moins en moins fiable dans le temps — le redirect top-level reste la solution la plus robuste face à ça, donc on garde cette forme plutôt que d'essayer d'aller vers de l'iframe.

**La piste retenue pour discussion : remplacer "UUID brut qui vaut un token" par un échange en deux temps, façon OAuth2 Authorization Code + PKCE** (le standard précis pour des apps front pures, sans secret possible côté client) :

1. `sso.tyrolium.fr` devient un serveur d'autorisation (léger — juste ce flow, pas un IdP complet).
2. Chaque site (tyroserv.fr, gamenium.fr, HUB...) est enregistré comme client public avec PKCE.
3. Au boot, un site sans session redirige vers `sso.tyrolium.fr/authorize?...&code_challenge=...` (le `code_challenge` dérive d'un secret éphémère généré côté client, jamais transmis avant l'échange final).
4. Si une session existe déjà chez `sso.tyrolium.fr` (cookie posé lors d'un login précédent, peu importe sur quel site), retour immédiat avec un **code d'autorisation à usage unique, valide 30-60 secondes** — plus de token brut dans une URL.
5. Le site échange ce code contre le vrai token via un `POST` (donc hors logs/historique navigateur), en fournissant le `code_verifier` d'origine — même si le code fuite entre-temps, il est inutilisable sans ce secret que seul le site d'origine détient.
6. Sans session existante, l'utilisateur voit l'écran de login une fois ; ensuite, tous les autres sites récupèrent la session via ce même mécanisme sans re-demander de mot de passe.

Ça ferme directement le point 4 : plus rien de longue durée ne transite jamais par une URL, et un code intercepté meurt en moins d'une minute et est inutilisable sans le `code_verifier` correspondant.

**Option ouverte, pas tranchée** : ce mécanisme (Authorization Code + PKCE) est le même moteur technique que celui déjà prévu pour `UseritiumOAuthController` (section 2.2) avec `league/oauth2-server-bundle`. Possibilité de faire tourner **un seul serveur OAuth2** pour les deux besoins, avec deux profils de clients différents (sites internes en PKCE public / Odoo en confidential avec secret) plutôt que deux systèmes séparés à maintenir — à peser plus tard, pas obligatoire.
