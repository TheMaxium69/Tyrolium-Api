#!/usr/bin/env bash
set -e
cd /var/www/html

APP_ENV="${APP_ENV:-dev}"
echo "==> APP_ENV=${APP_ENV}"

# vendor/ est bind-monté depuis l'hôte (déjà installé nativement) — jamais de
# --no-dev ici, ça supprimerait phpstan/phpunit/maker-bundle du vendor/ que
# `symfony server:start` utilise aussi en dehors de Docker. La bascule
# dev/prod se fait sur le comportement Symfony (cache, debug, opcache), pas
# sur les dépendances Composer installées.
if [ ! -f vendor/autoload.php ]; then
  echo "==> composer install (vendor absent)"
  composer install --no-interaction
fi

mkdir -p config/jwt
if [ ! -f config/jwt/private.pem ]; then
  echo "==> Génération des clés JWT (aucune trouvée dans config/jwt/)"
  php bin/console lexik:jwt:generate-keypair --no-interaction
fi

echo "==> cache:clear --env=${APP_ENV}"
php bin/console cache:clear --env="$APP_ENV" --no-interaction

if [ "$APP_ENV" = "prod" ]; then
  php bin/console cache:warmup --env=prod --no-interaction
fi

# var/ et config/jwt/ doivent rester accessibles en écriture à Apache (www-data) :
# composer/bin console ci-dessus tournent en root, apache2-foreground démarre en
# root puis fork ses workers en www-data (config par défaut de l'image).
chown -R www-data:www-data var config/jwt

exec "$@"
