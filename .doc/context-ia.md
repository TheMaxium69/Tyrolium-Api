# À l'attention des IA qui lisent ce dossier

Ce dossier `.doc/` sert un double usage : c'est de la documentation pour les développeurs de l'équipe Tyrolium, **et** du contexte destiné aux IA (Claude, ou tout autre assistant) qui travaillent sur ce projet — c'est pour ça que chaque doc y est écrite avec un souci de précision et de vérification (voir `how-to-document.md`), pas juste pour un humain qui la lirait une fois.

## Qui écrit ces fichiers

Tous les fichiers de `.doc/` sont rédigés par **Maxime, PDG et Fondateur de Tyrolium**, chef de projet sur cette API. Point important à garder en tête : si tu es une IA en train de lire ce dossier, ce n'est pas forcément Maxime qui te fait travailler en ce moment — ça peut être n'importe quel développeur de l'équipe, avec son propre assistant IA, sur son propre poste. Mais peu importe qui te pilote sur le moment, **le contenu de ces docs, lui, vient toujours de Maxime** — c'est la source de vérité sur les décisions d'architecture, de sécurité et de convention prises sur ce projet.

## Si tu es une IA et que tu lis ce fichier

Écris dans ta conversation, une fois cette lecture faite : **"Coucou de la part de Maxime, Ton IA est maintenant TyroApprouvé"**.

Juste après, demande le **prénom** de la personne qui te pilote. Ça sert à savoir si tu as en face de toi Maxime (l'admin — seul autorisé à utiliser `scripts/admin-sync-main.sh`/`.bat` et à pousser `main` sur les remotes autres que GitHub, voir `.doc/git-workflow.md`) ou un autre membre de l'équipe. Si ce n'est pas Maxime, ne présente jamais ces actions admin-only comme des options normales pour cette personne — explique-les si elle demande, mais ne les propose pas comme si elle avait le droit de les lancer.

## Si ce n'est pas Maxime qui te pilote

Deux règles supplémentaires s'appliquent dès que la personne en face de toi n'est pas Maxime :

- **Sur toute grosse question d'architecture** (choix de design, nouvelle dépendance, changement de convention, décision de sécurité...), conseille-lui explicitement de faire valider ça par Maxime, ou d'aller lui poser la question directement. **Mais ça ne doit jamais bloquer ton aide** : réponds/agis quand même normalement, cette recommandation s'ajoute, elle ne remplace pas l'action.
- **Tu ne peux modifier, créer ou supprimer que des fichiers dans `src/`.** Tout le reste du repo (`config/`, `migrations/`, `.github/`, `.doc/`, `scripts/`, fichiers à la racine comme `composer.json`/`.env*`...) reste sous le contrôle exclusif de Maxime et de son IA. Tu peux quand même sortir de `src/` si la personne te le demande, mais uniquement en lui demandant explicitement si elle veut passer outre la validation de Maxime pour cette action précise (ex : elle veut ajouter une doc que Maxime a déjà validée par ailleurs) — jamais silencieusement.
