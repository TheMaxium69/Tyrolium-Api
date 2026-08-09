# Format de Réponse API & Synchronisation JSON — Tyrolium API

Ce document définit la spécification stricte et le contrat d'interface (TypeScript / JSON) de l'enveloppe de réponse de l'API Tyrolium pour la synchronisation avec les frontends Angular (Tyrolium HUB et Useritium Dashboard).

---

## 1. Spécifications Globales & Interface TypeScript (Angular)

Toutes les routes de l'API Tyrolium doivent renvoyer un objet JSON respectant une structure d'enveloppe unifiée.

```typescript
/**
 * Enveloppe globale de réponse de l'API Tyrolium
 * @template T - Type du payload métier retourné dans 'data' (ex: User, User[], Product, etc.)
 */
export interface ApiResponse<T = any> {
  /**
   * Statut de la requête : true en cas de succès (2xx), false en cas d'erreur (4xx/5xx)
   */
  success: boolean;

  /**
   * Code de statut HTTP miroir (ex: 200, 201, 400, 401, 403, 404, 422, 500)
   */
  code: number;

  /**
   * Message descriptif lisible pour l'utilisateur ou le développeur
   */
  message: string;

  /**
   * Données métiers renvoyées par le serveur. Contient l'objet ou le tableau en cas de succès, null en cas d'erreur.
   */
  data: T | null;

  /**
   * Liste détaillée des erreurs de validation (principalement pour les erreurs 422 / formulaires).
   * Null ou omis en cas de succès.
   */
  errors?: ApiValidationError[] | null;

  /**
   * Métadonnées complémentaires (ex: pagination, filtres, timestamp).
   * Null ou omis si non applicable.
   */
  meta?: ApiMeta | null;
}

/**
 * Structure d'une erreur de validation unitaire sur un champ
 */
export interface ApiValidationError {
  /**
   * Nom du champ/propriété en faute (ex: "email", "password", "address.city")
   */
  field: string;

  /**
   * Message d'erreur explicatif pour ce champ
   */
  message: string;

  /**
   * Code d'erreur applicatif ou règle violée (ex: "INVALID_EMAIL", "MIN_LENGTH", "UNIQUE")
   */
  rule?: string;
}

/**
 * Métadonnées globales (pagination, etc.)
 */
export interface ApiMeta {
  /**
   * Horodatage ISO-8601 de la réponse serveur
   */
  timestamp?: string;

  /**
   * Identifiant unique de la requête pour la traçabilité des logs
   */
  requestId?: string;

  /**
   * Version courante de l'API (définie dans .env sous API_VERSION)
   */
  apiVersion?: string;

  /**
   * Adresse IP cliente détectée par le serveur
   */
  clientIp?: string;

  /**
   * Temps d'exécution de la requête en millisecondes
   */
  executionTimeMs?: number;

  /**
   * Informations de pagination pour les listes (si applicable)
   */
  pagination?: {
    page: number;
    limit: number;
    total: number;
    pages: number;
    hasNextPage: boolean;
    hasPrevPage: boolean;
  };
}
```

---

## 2. Exemples Concrets (Good vs Error)

### 2.1 Exemple de Succès (Good) — `200 OK`

**Cas** : Mise à jour réussie d'un profil utilisateur / mot de passe.

```json
{
  "success": true,
  "code": 200,
  "message": "Le mot de passe a été mis à jour avec succès.",
  "data": {
    "id": "usr_8f9a2b10",
    "email": "dev@tyrolium.fr",
    "updatedAt": "2026-08-09T02:18:33+02:00"
  },
  "errors": null,
  "meta": {
    "timestamp": "2026-08-09T02:18:33+02:00"
  }
}
```

### 2.2 Exemple d'Erreur (Err) — `422 Unprocessable Entity`

**Cas** : Échec de validation du formulaire de modification de mot de passe (mot de passe trop court + confirmation non identique).

```json
{
  "success": false,
  "code": 422,
  "message": "Les données soumises sont invalides.",
  "data": null,
  "errors": [
    {
      "field": "password",
      "message": "Le mot de passe doit comporter au moins 12 caractères.",
      "rule": "MIN_LENGTH"
    },
    {
      "field": "confirmPassword",
      "message": "La confirmation ne correspond pas au mot de passe saisi.",
      "rule": "MISMATCH"
    }
  ],
  "meta": {
    "timestamp": "2026-08-09T02:18:33+02:00"
  }
}
```

---

## 3. Exemples CRUD Complet (Create, Read, Update, Delete)

### 3.1 Create (Créer un produit SolidServ) — `POST /solid-serv/products`

**Réponse HTTP 201 Created** :

```json
{
  "success": true,
  "code": 201,
  "message": "Produit SolidServ créé avec succès.",
  "data": {
    "id": "prod_solid_01",
    "name": "VPS Pro NVMe v1",
    "slug": "vps-pro-nvme-v1",
    "priceMonthly": 19.99,
    "isPublic": true,
    "createdAt": "2026-08-09T02:18:33+02:00"
  },
  "errors": null,
  "meta": {
    "timestamp": "2026-08-09T02:18:33+02:00"
  }
}
```

### 3.2 Read List (Lister les produits avec pagination) — `GET /solid-serv/products?page=1&limit=2`

**Réponse HTTP 200 OK** :

```json
{
  "success": true,
  "code": 200,
  "message": "Liste des produits récupérée.",
  "data": [
    {
      "id": "prod_solid_01",
      "name": "VPS Pro NVMe v1",
      "slug": "vps-pro-nvme-v1",
      "priceMonthly": 19.99,
      "isPublic": true
    },
    {
      "id": "prod_solid_02",
      "name": "Serveur Dédié Storage v2",
      "slug": "dedie-storage-v2",
      "priceMonthly": 89.99,
      "isPublic": false
    }
  ],
  "errors": null,
  "meta": {
    "timestamp": "2026-08-09T02:18:33+02:00",
    "pagination": {
      "page": 1,
      "limit": 2,
      "total": 14,
      "pages": 7,
      "hasNextPage": true,
      "hasPrevPage": false
    }
  }
}
```

### 3.3 Read Single (Consulter un produit par son ID) — `GET /solid-serv/products/prod_solid_01`

**Réponse HTTP 200 OK** :

```json
{
  "success": true,
  "code": 200,
  "message": "Détails du produit récupérés.",
  "data": {
    "id": "prod_solid_01",
    "name": "VPS Pro NVMe v1",
    "slug": "vps-pro-nvme-v1",
    "specs": {
      "vCpu": 4,
      "ramGb": 8,
      "storageGb": 100
    },
    "priceMonthly": 19.99,
    "isPublic": true
  },
  "errors": null
}
```

**Cas d'erreur 404 (Produit non trouvé)** :

```json
{
  "success": false,
  "code": 404,
  "message": "Le produit demandé 'prod_solid_999' n'existe pas.",
  "data": null,
  "errors": null,
  "meta": {
    "timestamp": "2026-08-09T02:18:33+02:00"
  }
}
```

### 3.4 Update (Modifier un produit) — `PUT /solid-serv/products/prod_solid_01`

**Réponse HTTP 200 OK** :

```json
{
  "success": true,
  "code": 200,
  "message": "Produit 'VPS Pro NVMe v1' mis à jour.",
  "data": {
    "id": "prod_solid_01",
    "name": "VPS Pro NVMe v1 (Mis à jour)",
    "priceMonthly": 24.99,
    "updatedAt": "2026-08-09T02:18:33+02:00"
  },
  "errors": null
}
```

### 3.5 Delete (Supprimer un produit) — `DELETE /solid-serv/products/prod_solid_01`

**Réponse HTTP 200 OK** :

```json
{
  "success": true,
  "code": 200,
  "message": "Le produit 'prod_solid_01' a été supprimé avec succès.",
  "data": {
    "id": "prod_solid_01",
    "deletedAt": "2026-08-09T02:18:33+02:00"
  },
  "errors": null
}
```
