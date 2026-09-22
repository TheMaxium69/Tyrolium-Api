#!/usr/bin/env bash
# Mise à jour quotidienne : récupère le code, réinstalle les dépendances si besoin,
# et surtout rejoue les migrations créées par les autres — jamais "diff"/"make:migration"
# ici, seulement "migrate". Voir la discussion sur le workflow de migrations partagées.
set -e
cd "$(dirname "$0")/.."

echo "==> git pull"
git pull

echo "==> Composer install (no-op si rien n'a changé)"
composer install

echo "==> Application des migrations"
php bin/console doctrine:migrations:migrate --no-interaction

echo "==> Cache clear (dev)"
php bin/console cache:clear --env=dev

echo ""
echo "==> À jour."
