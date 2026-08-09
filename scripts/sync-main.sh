#!/usr/bin/env bash
# À lancer sur SA branche de feature (jamais sur main) : récupère les derniers
# changements de main (typiquement après qu'une PR a été mergée) et les
# fusionne dans la branche courante. Ne touche jamais à main directement,
# ne force-push jamais.
set -e
cd "$(dirname "$0")/.."

CURRENT_BRANCH=$(git rev-parse --abbrev-ref HEAD)

if [ "$CURRENT_BRANCH" = "main" ]; then
  echo "Tu es déjà sur main — utilise scripts/pull.sh, pas celui-ci."
  exit 1
fi

if [ -n "$(git status --porcelain)" ]; then
  echo "Tu as des changements non commités. Commit ou stash-les avant de synchroniser avec main."
  exit 1
fi

REMOTE="origin"
if ! git remote | grep -qx "$REMOTE"; then
  REMOTE=$(git remote | head -n1)
fi

if [ -z "$REMOTE" ]; then
  echo "Aucun remote Git configuré."
  exit 1
fi

echo "==> git fetch $REMOTE main"
git fetch "$REMOTE" main

echo "==> Fusion de $REMOTE/main dans '$CURRENT_BRANCH'"
if ! git merge "$REMOTE/main"; then
  echo ""
  echo "!! Conflit de fusion. Résous les conflits (git status pour voir les fichiers concernés),"
  echo "!! puis 'git add' + 'git commit' pour terminer la fusion."
  exit 1
fi

echo "==> Composer install (au cas où main a ajouté des dépendances)"
composer install

echo "==> Application des migrations (au cas où main en a ajouté)"
php bin/console doctrine:migrations:migrate --no-interaction

echo ""
echo "==> '$CURRENT_BRANCH' est à jour avec $REMOTE/main."
