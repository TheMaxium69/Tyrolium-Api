# Cahier des Charges : Tyrolium API & Tyrolium HUB

*Converti depuis `Cahier des Charges : Tyrolium API & Tyrolium HUB.pdf`, sans perte d'info. 8 août 2026.*

**Tyrolium**
91027536100012
252, Avenue Jean Jaurès
69150 Décines-Charpieu
contact@tyrolium.fr
tyrolium.fr

---

## 1. Vision & Architecture Globale

L'objectif de cette refonte majeure est de **unifier l'écosystème Tyrolium**. Nous abandonnons le découpage historique (Backends fragmentés pour Gamenium, TyroServ, etc.) au profit d'une **architecture API-First ultra-centralisée**.

Schéma d'architecture :

```
[ TYROLIUM HUB (Angular + Tyrolium-UI) ]
              │
              │ (REST JSON / SSO)
              ▼
     [ TYROLIUM API (Symfony) ]
              │
   ┌──────────┼──────────┬─────────────┐
   ▼          ▼          ▼             ▼
[Useritium] [Tyrolium Core] [SolidServ] [Filiales...]
```

- **SÉPARATION STRICTE INTERNE / PUBLIC :**
  - **Tyrolium HUB** (`hub.tyrolium.fr`) : Application frontend Angular strictement réservée aux équipes et au personnel interne de la société (accès restreint selon les rôles employés).
  - **Useritium Dashboard** (`dashboard.useritium.fr`) : Espace client et gestion 100 % publique (historiquement en PHP, qui sera intégralement refait en Angular avec `tyrolium-ui`).
  - **Tyrolium App** (`app.tyrolium.fr`) : Instance Odoo auto-hébergée (reliée au SSO Useritium) assurant la gestion de projet, la facturation globale, le recrutement (`jobs.tyrolium.fr`) et la formation (`formation.tyrolium.fr`).
- **Tyrolium API** (`api.tyrolium.fr`) : API RESTful JSON stricte développée sous Symfony. Elle centralise la logique métier, la sécurité, le SSO et la refonte intégrale de la brique de comptes **Useritium**.

---

## 2. Spécifications Backend : Tyrolium API (Symfony)

L'API suit une organisation stricte par sous-dossiers de Controllers et de Services.

### A. Namespace Useritium

Refonte complète du système de comptes Useritium avec préparation du script de migration de l'ancienne BDD.

- **`UseritiumController`** : Inscription, connexion, gestion du profil utilisateur, sécurité (déconnexion de tout les appareil, mots de passe oublié).
- **`UseritiumOAuthController`** : Serveur OAuth2 pour l'authentification tierce.
- **`UseritiumSSOController`** : Gestion des sessions unifiées (Single Sign-On) pour l'ensemble des sites de l'écosystème.
- **`UseritiumDashboardController`** : Endpoints pour l'espace utilisateur public :
  - Consultation des prestations actives, accès aux serveurs, facturation et documents/cloud.
  - Interface d'ouverture et de suivi des tickets de support.
  - **Gestion des Connexions Externes :** Visualisation des comptes et services tiers liés au profil Useritium (compte TyroServ, Gamenium, sites clients, etc.).
- **`UseritiumDriveController`** : API de gestion du stockage cloud client, à definir plus en détail plutard.
- **`UseritiumAdminController`** : Administration des comptes utilisateurs (signalements, modération, ban).

### B. Namespace Tyrolium

- **`TyroliumAnalyticsController`** : Centralisation des métriques et données de fréquentation. (Fusion avec l'api déjà codé)
- **`TyroliumPrestationController`** : Gestion des offres/prestations clients. Synchronisation avec les gateways de paiement (**Lemon Squeezy / Stripe**) et liaison des achats/devis sur-mesure aux comptes clients pour affichage dans `UseritiumDashboardController`.
- **`TyroliumWebSiteController`** : Inventaire centralisé et audit de tous les noms de domaine et sites web gérés par la holding. Liaison directe avec les instances de serveurs SolidServ, suivi du propriétaire (Filiale ou Client Prestation) et suivi du renouvellement/échéances.
- **`TyroliumSupportController`** :
  - **Gestion centrale du système de tickets :** Traitement des demandes d'assistance ouvertes par les clients depuis leur Useritium Dashboard.
  - Attribution des tickets aux collaborateurs (ex: assignation à un développeur/technicien), suivi des statuts (*Ouvert*, *En cours*, *En attente client*, *Résolu*), et notifications en temps réel.
- **`TyroliumPermissionController`** : Gestion RBAC (Role-Based Access Control) des employés et administrateurs. Attribution des permissions d'accès au Tyrolium HUB et aux périmètres de gestion (TyroServ, Gamenium, Vturias, SolidServ, etc.).
- **`TyroliumApiKeyController`** :
  - Panneau de création et de gestion des clés API (`tyrokey_live...`) avec permissions/scopes granulaires par projet (TyroServ, Gamenium, sites clients, etc.).
  - Système de **Webhooks** sortants pour notifier les applications tierces en temps réel.

### C. Namespaces Filiales

- **`SolidServ/SolidServProductController`** :
  - Creation et gestion du catalogue d'offres et grille tarifaire.
  - **Distinction Catalogue :** Distingue les produits **publics répertoriés** (visibles sur `solidserv.fr`) des produits **non répertoriés / sur-mesure** (dédiés aux contrats spécifiques type *Cabinet Marthelot*, devis personnalisés, etc.).
  - Gestion des stocks / ruptures de stock, activation/désactivation des offres, et liaison directe des produits avec les offres Stripe/Lemon Squeezy ou des prestations Tyrolium.
- **`SolidServ/SolidServDashboardController`** :
  - Inventaire et état de l'ensemble du parc serveur.
  - **Liaison Produit/Abonnement :** Association stricte de chaque instance de serveur à un `Product` SolidServ (afin de connaître immédiatement l'offre souscrite, le type d'abonnement et la tarification appliquée).
  - Statuts de paiement (Lemon Squeezy/Stripe), statuts de provisionnement ("en cours de création", "actif"), transmission sécurisée des identifiants au client, et synchronisation avec la page publique `tyrolium.fr/server`.
- **`SolidServ/SolidServStatusController`** : Proxy / Webhook léger synchronisé avec **UptimeRobot** pour remonter l'état de santé de l'infrastructure sur le HUB, Dashboard public et `tyrolium.fr/server`.
- **`SolidServ/SolidServProxmoxController`** : Intégration de l'API Proxmox VE interne pour le contrôle automatisé des VM/LXI.
- **`SolidServ/SolidServLogController`** :
  - Centralisation et ingestion de l'ensemble des événements, logs système, erreurs applicatives Symfony, état des nœuds Proxmox et métriques des serveurs de bases de données.
- **`TyroServ`** : Migration/Portage progressif de l'API PHP/Java existante.
- **`Gamenium`** : Portage de l'API Symfony existante dans le nouveau modèle.
- **`Vturias` / `Influnias` / `TyroCiel`** : Structures réservées pour intégrations futures.

---

## 3. Spécifications Frontend : Tyrolium HUB (Angular Workspace)

- **Design System :** Utilisation obligatoire et exclusive de la librairie **`tyrolium-ui`** (`design.tyrolium.fr`) pour toutes les applications frontales (internes et publiques).
- **Tyrolium HUB (Interne) :**
  - Navigation par Sub-bar découpée par **espaces thématiques / filiales** (Holding, Useritium Admin, Support, Infrastructure, SolidServ, TyroServ, Gamenium).
  - Interface dédiée pour la réponse aux tickets support, la consultation des logs système/applicatifs et la gestion du registre des domaines.
  - Contrôle d'accès strict via `TyroliumPermissionController` (réservé aux collaborateurs).
- **Useritium Dashboard (Public) :**
  - Refonte complète de l'ancien dashboard PHP vers une application Angular moderne utilisant `tyrolium-ui`.
  - Espace client permettant la consultation des services, factures, documents cloud, création/suivi des tickets de support et gestion du compte.

---

## 4. Modélisation de la Base de Données

*(Section laissée libre : le schéma relationnel, les entités ORM Doctrine et le nommage des tables seront définis dans un second temps).*

---

## 5. Infrastructure & Ingestion Base de Données (DevOps)

- **Architecture BDD Distribuée :** Migration hors du serveur web principal vers un cluster dédié de serveurs de bases de données (ex: `database701.tyrolium.fr`, `database702.tyrolium.fr`).
- **Plan de Sauvegarde :** Mise en place par l'équipe SysAdmin d'une stratégie de backup avancée.
- **Migration de données :** Rédaction d'un script d'importation/mappage depuis la BDD historique Useritium vers la nouvelle structure Symfony.

---

## 6. WebApp & Notifications (PWA)

- **Progressive Web App (PWA) & Manifest :**
  - Intégration d'un Web App Manifest et d'un Service Worker sur Tyrolium HUB afin de pouvoir l'installer comme une application native de bureau / mobile (à la manière d'Odoo).
- **Centre de Notifications Unifié :**
  - Système de notifications en temps réel (Web Push, Badges d'application et notifications transitoires via `tyrolium-ui`).
  - Alertes instantanées pour les collaborateurs lors de la création d'un ticket support, d'un événement critique de log, ou d'une modification système.
