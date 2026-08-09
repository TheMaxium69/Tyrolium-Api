#!/usr/bin/env bash
# Première installation du projet (nouveau poste, nouveau dev). Voir scripts/pull.sh
# pour la mise à jour au quotidien une fois le setup fait.
set -e
cd "$(dirname "$0")/.."

echo "==> Installation des dépendances Composer"
composer install

if [ ! -f .env.local ]; then
  echo "==> Création de .env.local depuis le template"
  cp .env.local.template .env.local
  echo ""
  echo "!! .env.local vient d'être créé avec des identifiants d'exemple."
  echo "!! Édite-le avec ta vraie config DB locale (voir .doc/env-files.md), puis relance ce script."
  exit 0
fi

echo "==> Création de la base de données (si elle n'existe pas déjà)"
php bin/console doctrine:database:create --if-not-exists

echo "==> Application des migrations"
php bin/console doctrine:migrations:migrate --no-interaction

if [ ! -f config/jwt/private.pem ]; then
  echo "==> Génération des clés JWT (dev)"
  php bin/console lexik:jwt:generate-keypair --env=dev --no-interaction
fi

if [ ! -f config/jwt/private-test.pem ]; then
  echo "==> Génération des clés JWT (test)"
  php bin/console lexik:jwt:generate-keypair --env=test --no-interaction
fi

echo ""
echo "==> Setup terminé. Tu peux lancer le serveur avec : symfony server:start"
