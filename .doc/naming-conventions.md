# Conventions de nommage — Tyrolium API

Comment un dossier de controller, une méthode, une URL et un nom de route se déduisent les uns des autres. Décidé avec Maxime le 2026-08-09, à appliquer à tous les futurs controllers "métier" (`Useritium/`, `Tyrolium/`, `SolidServ/`... — voir `.doc/useritium-auth.md` et le cahier des charges pour la liste complète des namespaces prévus). Ne s'applique pas à `TestController` (`src/Controller/Debug/`), qui est un controller de debug jetable, pas un controller métier.

---

## 1. Les règles

| Élément | Règle | Transformation |
|---|---|---|
| Dossier de controller | Le nom du dossier = premier segment de l'URL, en minuscule | `Useritium/` → `useritium/` |
| Nom de classe | `{Dossier}{Spécifique}Controller` — le dossier redondant dans le nom de fichier n'est pas une erreur, c'est voulu (évite les collisions entre namespaces, ex: deux `SsoController` dans deux dossiers différents) | `UseritiumSsoController` = `Useritium` + `Sso` + `Controller` |
| Partie "spécifique" du nom de classe | Deuxième segment de l'URL, en kebab-case | `Sso` → `sso` |
| Nom de méthode | `{verbeHttp}{Contexte}` en camelCase — toujours commencer par le verbe (`get`, `post`, `put`, `delete`, `patch`), suivi de ce que ça fait | `getAllUser`, `getOneUser` |
| Dernier segment de l'URL | Nom de la méthode converti en kebab-case | `getAllUser` → `get-all-user` |
| Nom de la route Symfony (`name:`) | Chemin complet joint par des `_`, chaque segment en snake_case | `useritium_sso_get_all_user` |
| Variables PHP | Toujours camelCase, jamais de snake_case, quel que soit le contexte | `test`, `testTest`, `testTestTest` |

---

## 2. Exemple complet, bout en bout

Fichier : `src/Controller/Useritium/UseritiumSsoController.php`

```php
namespace App\Controller\Useritium;

class UseritiumSsoController extends AbstractController
{
    #[Route('/useritium/sso/get-all-user', name: 'useritium_sso_get_all_user', methods: ['GET'])]
    public function getAllUser(): JsonResponse
    {
        // ...
    }

    #[Route('/useritium/sso/get-one-user', name: 'useritium_sso_get_one_user', methods: ['GET'])]
    public function getOneUser(): JsonResponse
    {
        // ...
    }
}
```

Résultat : `GET api.tyrolium.fr/useritium/sso/get-all-user`, route Symfony nommée `useritium_sso_get_all_user`.

**Décomposition de la transformation camelCase → kebab-case / snake_case** : on insère un séparateur avant chaque majuscule (sauf la première), puis on met tout en minuscule.
- `getAllUser` → kebab: `get-all-user` (séparateur `-`)
- `getAllUser` → snake: `get_all_user` (séparateur `_`)

Un deuxième exemple pour vérifier que la règle généralise, avec un autre namespace du cahier des charges :

Fichier : `src/Controller/SolidServ/SolidServProductController.php`

```php
namespace App\Controller\SolidServ;

class SolidServProductController extends AbstractController
{
    #[Route('/solidserv/product/post-create-offer', name: 'solidserv_product_post_create_offer', methods: ['POST'])]
    public function postCreateOffer(): JsonResponse
    {
        // ...
    }
}
```

`SolidServProductController` = `SolidServ` (dossier) + `Product` (spécifique) + `Controller` → URL `solidserv/product/...`. Même mécanique.

---

## 3. Pourquoi

- **Prévisible** : en lisant juste le chemin du fichier et le nom de la méthode, on connaît l'URL et le nom de route sans avoir à ouvrir le fichier — utile dès que le nombre de controllers grandit (et il va grandir, vu le nombre de namespaces prévus au cahier des charges : Useritium, Tyrolium, SolidServ, TyroServ, Gamenium...).
- **Le verbe HTTP en préfixe de méthode** (`get`/`post`/`put`/`delete`) rend l'intention de chaque route lisible directement dans la liste des méthodes de la classe, sans avoir à checker l'attribut `#[Route(methods: [...])]` de chaque méthode pour savoir ce qu'elle fait.
- **Pas de redite entre le nom de route et l'URL** : les deux dérivent de la même source (chemin de fichier + nom de méthode), donc ils ne peuvent pas diverger silencieusement au fil du temps — renommer une méthode oblige à renommer sa route de la même façon, pas de risque d'avoir une méthode `getAllUser` accessible sur une route nommée `useritium_sso_legacy_endpoint`.
- **camelCase pour les variables** : convention PHP/PSR standard, déjà ce qui est utilisé partout dans le code existant du projet (`$hashedPassword`, `$isValid`, `$plainPassword`...) — cette doc formalise ce qui était déjà appliqué en pratique, pas un changement.
