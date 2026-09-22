# Image locale pour lancer l'API avec Apache (dev ou prod, voir compose.yaml et
# docker/php-apache/entrypoint.sh). Pas destinée au vrai déploiement prod (voir
# .doc/env-files.md / .doc/secrets-vault.md pour ça) — juste à tester le comportement
# "prod" (composer --no-dev, opcache, pas de debug) en local, sur cette machine.
FROM composer:2 AS composer

FROM php:8.4-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends libicu-dev unzip \
    && docker-php-ext-install pdo_mysql intl opcache \
    # Retire seulement les headers/outils de dev, PAS libicu-dev en --auto-remove
    # (ça a failli entraîner la lib runtime libicu*.so avec, cassant l'extension
    # intl déjà compilée — vérifié en réel, `php -m` levait un Warning au boot).
    && apt-get purge -y libicu-dev icu-devtools \
    && rm -rf /var/lib/apt/lists/* \
    && echo "ServerName localhost" >> /etc/apache2/apache2.conf \
    && a2enmod rewrite

COPY --from=composer /usr/bin/composer /usr/bin/composer
COPY docker/php-apache/vhost.conf /etc/apache2/sites-available/000-default.conf
COPY docker/php-apache/opcache.ini /usr/local/etc/php/conf.d/zz-tyrolium-opcache.ini
COPY docker/php-apache/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

WORKDIR /var/www/html

ENTRYPOINT ["entrypoint.sh"]
CMD ["apache2-foreground"]
