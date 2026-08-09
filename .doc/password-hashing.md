# Hashing des mots de passe — comment ça marche vraiment

Pourquoi deux hash du même mot de passe sont différents, et pourquoi la vérification fonctionne quand même. Complète `.doc/useritium-auth.md` (section sur `UseritiumAccountController`).

---

## 1. Valeurs actuelles

| Variable | Valeur actuelle | Défini dans |
|---|---|---|
| Config du hasher | `auto` pour toute classe implémentant `PasswordAuthenticatedUserInterface` | `config/packages/security.yaml` |
| Algo réellement utilisé pour un nouveau hash | **bcrypt** (`$2y$`, coût 13) | résolu par Symfony au runtime, voir section 3 |
| Pourquoi bcrypt et pas argon2/sodium (les deux dispo sur cette machine) | `auto` construit une chaîne `['native', 'sodium', 'pbkdf2']`, et `native` (= bcrypt, en dur dans `NativePasswordHasher`) est tenté **en premier** pour tout nouveau hash — les autres algos de la chaîne ne servent qu'à *vérifier* d'anciens hash produits avec eux, pas à en créer de nouveaux | `vendor/symfony/password-hasher/Hasher/NativePasswordHasher.php:28` (`private string $algorithm = \PASSWORD_BCRYPT;`) |
| Classe utilisée pour hash/vérifier | `Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface`, autowirée | testé dans `src/Controller/Debug/TestController.php` |
| Condition pour que ça marche | `User` doit implémenter `PasswordAuthenticatedUserInterface` (fait le 2026-08-09) | `src/Entity/User.php` |

---

## 2. Tuto pratique

### 2.1 Hasher un mot de passe

```php
public function register(UserPasswordHasherInterface $passwordHasher): JsonResponse
{
    $user = new User();
    $user->setUsername('maxime');

    $hashedPassword = $passwordHasher->hashPassword($user, 'motdepasse123');
    $user->setPassword($hashedPassword); // c'est CE qu'on stocke en DB, jamais le mot de passe en clair
}
```

### 2.2 Vérifier un mot de passe au login

```php
public function login(UserPasswordHasherInterface $passwordHasher, User $user, string $motDePasseTape): bool
{
    // $user vient de la DB (déjà chargé, avec son password déjà hashé dedans)
    return $passwordHasher->isPasswordValid($user, $motDePasseTape);
}
```

**Jamais** de comparaison manuelle (`$hash === $autreHash`) — toujours passer par `isPasswordValid()`, pour la raison expliquée en section 3.

### 2.3 Vérifier que ça marche vraiment

Exemple testé en réel sur ce projet, deux hash du même mot de passe :

```
motdepasse123 → $2y$13$gAOgZir6bmTv5149aUKAIOBv8NsFuhPuUM0.tBB6PHbsSX.9clZAm
motdepasse123 → $2y$13$YMfPxeR7jkYQOCDByBNETOoGzp/WS.ez2Tb3oTLAM6SoPWtvtMxPO
```

Complètement différents, et pourtant `isPasswordValid()` reconnaît `motdepasse123` comme correct dans les deux cas — voir pourquoi en section 3.

---

## 3. Comment ça marche en détail

### Le salt est stocké EN CLAIR, à l'intérieur même du hash

Décortiquons `$2y$13$gAOgZir6bmTv5149aUKAIOBv8NsFuhPuUM0.tBB6PHbsSX.9clZAm` (format bcrypt) :

```
$2y$    13$    gAOgZir6bmTv5149aUKAIO    Bv8NsFuhPuUM0.tBB6PHbsSX.9clZAm
 ↑       ↑              ↑                              ↑
algo   coût     salt aléatoire (22 car.)        le vrai hash (31 car.)
```

Le salt n'est **pas** un secret caché ailleurs (dans une variable d'env, un fichier de config...) — il fait partie intégrante de la chaîne de 60 caractères stockée telle quelle dans la colonne `password` en DB. Générer un mot de passe une deuxième fois tire un salt aléatoire différent → chaîne finale différente, même mot de passe.

### Pourquoi `isPasswordValid()` fonctionne quand même

Il ne compare **pas** deux hash entre eux. Concrètement :
1. Il lit le hash stocké en DB (ex: `$2y$13$gAOgZir6...`).
2. Il en extrait l'algo, le coût et le salt — tout est là, en clair, dans les 29 premiers caractères.
3. Il recalcule un hash du mot de passe **que tu viens de taper**, en réutilisant **ce même salt extrait** (pas un nouveau salt aléatoire).
4. Il compare le résultat obtenu à la partie "vrai hash" (les 31 derniers caractères) de ce qui est stocké.

Le salt aléatoire n'est donc généré qu'**une seule fois, à la création** — ensuite il est toujours relu depuis le hash stocké, jamais régénéré. C'est pour ça que deux hash différents du même mot de passe vérifient quand même correctement chacun de leur côté : chaque hash est cohérent avec son propre salt embarqué.

### Pourquoi éviter les schémas de hachage simplistes à sel statique

Dans un schéma de hachage simpliste (ex: MD5/SHA1 avec un sel statique unique), le sel est **identique pour tous les utilisateurs**. Deux utilisateurs avec le même mot de passe obtiennent donc **le même hash** — un attaquant qui accède à la base de données n'a besoin de précalculer qu'**une seule** table d'attaque (rainbow table) pour l'ensemble de la base. Avec bcrypt (ou argon2/sodium), le sel aléatoire embarqué et unique par hash rend cette attaque inopérante : compromettre un hash ne renseigne en rien sur les autres, même pour un mot de passe identique.

### Pourquoi c'est volontairement lent

bcrypt/argon2/sodium sont conçus pour être **coûteux en calcul** (le "coût" `13` dans `$2y$13$...` contrôle ça — chaque incrément double le temps de calcul). C'est voulu : ça ralentit un attaquant qui essaie de deviner un mot de passe par force brute (des milliers de tentatives/seconde deviennent quelques-unes/seconde), sans gêner un utilisateur légitime qui ne tape son mot de passe qu'une fois. C'est l'opposé exact de MD5, conçu pour être *rapide* (fait pour vérifier l'intégrité de fichiers, pas pour protéger des secrets) — un GPU moderne calcule des milliards de MD5/seconde, ce qui rend un mot de passe même correctement salé beaucoup plus vite cassable qu'avec bcrypt/argon2.
