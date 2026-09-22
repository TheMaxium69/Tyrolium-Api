#!/usr/bin/env bash
# Crée une nouvelle branche à jour avec origin/main et prépare l'environnement
# pour coder tout de suite (composer install + migrations + cache). Usage :
#   ./scripts/new-branch.sh ma-nouvelle-branche
# ou sans argument, il demande le nom.
set -e
cd "$(dirname "$0")/.."

if [ -n "$(git status --porcelain)" ]; then
  echo "Tu as des changements non commités. Commit ou stash-les avant de créer une branche."
  exit 1
fi

BRANCH_NAME="$1"
if [ -z "$BRANCH_NAME" ]; then
  read -r -p "Nom de la nouvelle branche : " BRANCH_NAME
fi

if [ -z "$BRANCH_NAME" ]; then
  echo "Nom de branche vide, annulé."
  exit 1
fi

echo "==> git fetch origin main"
git fetch origin main

echo "==> Création de '$BRANCH_NAME' depuis origin/main"
git checkout -b "$BRANCH_NAME" origin/main

echo "==> Composer install"
composer install

echo "==> Application des migrations"
php bin/console doctrine:migrations:migrate --no-interaction

echo "==> Cache clear (dev)"
php bin/console cache:clear --env=dev

echo ""
echo "==> Branche '$BRANCH_NAME' prête, à jour avec origin/main."
