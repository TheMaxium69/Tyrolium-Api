# Guide de test & Endpoints d'Authentification Useritium

Ce document liste l'ensemble des 9 routes d'authentification et de gestion de compte disponibles sur l'API Tyrolium (Useritium), avec les formats de requêtes JSON, les réponses attendues et les commandes `curl` pour tester directement en local.

---

## 📋 Synthèse des Routes

| Route | Méthode | Accès | Description |
|---|---|---|---|
| `/useritium/account/post-register` | `POST` | Public | Création d'un nouveau compte utilisateur |
| `/useritium/account/post-verify-email` | `POST` | Public | Confirmation d'une adresse email via token |
| `/useritium/account/post-login` | `POST` | Public | Connexion et obtention du token JWT |
| `/useritium/account/post-forgot-password` | `POST` | Public | Demande de réinitialisation de mot de passe |
| `/useritium/account/post-reset-password` | `POST` | Public | Modification du mot de passe avec le token de reset |
| `/useritium/account/post-add-email` | `POST` | `Bearer JWT` | Ajout d'une adresse email secondaire |
| `/useritium/account/post-set-default-email` | `POST` | `Bearer JWT` | Passage d'un email vérifié en email par défaut |
| `/useritium/account/post-logout-all-devices` | `POST` | `Bearer JWT` | Révoque tous les tokens JWT (Déconnexion globale) |
| `/useritium/account/delete-email/{id}` | `DELETE` | `Bearer JWT` | Suppression d'un email secondaire |

---

## 1. Inscription (`POST /useritium/account/post-register`)

Permet de créer un compte avec un `username`, un `email` (qui devient l'email par défaut) et un `password`.

### Requête Body JSON
```json
{
  "username": "maxime_dev",
  "email": "dev@tyrolium.fr",
  "password": "superPassword123!"
}
```

### Commande cURL
```bash
curl -X POST http://127.0.0.1:8000/useritium/account/post-register \
  -H "Content-Type: application/json" \
  -d '{
    "username": "maxime_dev",
    "email": "dev@tyrolium.fr",
    "password": "superPassword123!"
  }'
```

> 💡 **Note Dev** : En environnement `dev`, la réponse contient la clé `debugVerificationToken` dans `data` pour pouvoir valider l'email sans envoyer de mail réel.

---

## 2. Vérification d'Email (`POST /useritium/account/post-verify-email`)

Valide l'adresse email rattachée au token de vérification.

### Requête Body JSON
```json
{
  "token": "VOTRE_TOKEN_DE_VERIFICATION"
}
```

### Commande cURL
```bash
curl -X POST http://127.0.0.1:8000/useritium/account/post-verify-email \
  -H "Content-Type: application/json" \
  -d '{
    "token": "VOTRE_TOKEN_DE_VERIFICATION"
  }'
```

---

## 3. Connexion JWT (`POST /useritium/account/post-login`)

Permet d'obtenir le Bearer Token JWT. L'identifiant peut être le `username` **ou** l'`email par défaut vérifié`.

### Requête Body JSON
```json
{
  "identifier": "maxime_dev",
  "password": "superPassword123!"
}
```

### Commande cURL
```bash
curl -X POST http://127.0.0.1:8000/useritium/account/post-login \
  -H "Content-Type: application/json" \
  -d '{
    "identifier": "maxime_dev",
    "password": "superPassword123!"
  }'
```

### Réponse de succès (`200 OK`)
```json
{
  "success": true,
  "code": 200,
  "message": "Connexion réussie.",
  "data": {
    "token": "eyJhbGciOiJSUzI1NiIs..."
  },
  "errors": null,
  "meta": {
    "timestamp": 1770653100,
    "requestId": "req_...",
    "apiVersion": "1.0.0",
    "clientIp": "127.0.0.1",
    "executionTimeMs": 45
  }
}
```

---

## 4. Ajout d'un Email Secondaire (`POST /useritium/account/post-add-email`)

Ajoute une nouvelle adresse mail au compte (limite de 5 emails par compte max). Requiert l'en-tête `Authorization: Bearer <token>`.

### Requête Body JSON
```json
{
  "email": "secondaire@tyrolium.fr"
}
```

### Commande cURL
```bash
curl -X POST http://127.0.0.1:8000/useritium/account/post-add-email \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer VOTRE_JWT_ICI" \
  -d '{
    "email": "secondaire@tyrolium.fr"
  }'
```

---

## 5. Passer un Email en Par Défaut (`POST /useritium/account/post-set-default-email`)

Bascule un email secondaire vérifié en email principal/par défaut.

### Requête Body JSON
```json
{
  "emailId": 2
}
```

### Commande cURL
```bash
curl -X POST http://127.0.0.1:8000/useritium/account/post-set-default-email \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer VOTRE_JWT_ICI" \
  -d '{
    "emailId": 2
  }'
```

---

## 6. Déconnexion de Tous les Appareils (`POST /useritium/account/post-logout-all-devices`)

Révoque immédiatement tous les tokens JWT émis avant cet instant pour l'utilisateur.

### Commande cURL
```bash
curl -X POST http://127.0.0.1:8000/useritium/account/post-logout-all-devices \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer VOTRE_JWT_ICI"
```

---

## 7. Suppression d'un Email Secondaire (`DELETE /useritium/account/delete-email/{id}`)

Supprime une adresse email secondaire du compte (impossible de supprimer l'email par défaut ou le dernier email).

### Commande cURL
```bash
curl -X DELETE http://127.0.0.1:8000/useritium/account/delete-email/2 \
  -H "Authorization: Bearer VOTRE_JWT_ICI"
```

---

## 8. Demande de Mot de Passe Oublié (`POST /useritium/account/post-forgot-password`)

Génère un token de réinitialisation. Sécurité anti-énumération : le message renvoyé est identique que le compte existe ou non.

### Requête Body JSON
```json
{
  "identifier": "dev@tyrolium.fr"
}
```

### Commande cURL
```bash
curl -X POST http://127.0.0.1:8000/useritium/account/post-forgot-password \
  -H "Content-Type: application/json" \
  -d '{
    "identifier": "dev@tyrolium.fr"
  }'
```

> 💡 **Note Dev** : En environnement `dev`, la réponse contient `data.debugResetToken`.

---

## 9. Réinitialisation du Mot de Passe (`POST /useritium/account/post-reset-password`)

Modifie le mot de passe de l'utilisateur à l'aide du token de réinitialisation.

### Requête Body JSON
```json
{
  "token": "VOTRE_TOKEN_DE_RESET",
  "newPassword": "nouveauPassword123!"
}
```

### Commande cURL
```bash
curl -X POST http://127.0.0.1:8000/useritium/account/post-reset-password \
  -H "Content-Type: application/json" \
  -d '{
    "token": "VOTRE_TOKEN_DE_RESET",
    "newPassword": "nouveauPassword123!"
  }'
```
