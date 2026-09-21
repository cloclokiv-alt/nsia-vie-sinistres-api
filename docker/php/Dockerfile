# Atelier PHP de l'API sinistres.
#
# L'image officielle apporte PHP 8.4 ; il ne reste qu'a y ajouter les extensions dont
# Laravel a besoin et Composer. Aucun serveur web : « php artisan serve » suffit pour
# un poste de travail, et evite une configuration Nginx a maintenir pour rien.
FROM php:8.4-cli-bookworm

# Bibliotheques exigees par les extensions compilees ensuite. Sans libicu-dev, intl
# echoue a la configuration et le message n'apparait que plusieurs etapes plus loin.
RUN apt-get update \
 && apt-get install -y --no-install-recommends \
        git unzip libicu-dev libzip-dev libonig-dev default-mysql-client \
 && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install -j"$(nproc)" pdo_mysql intl zip bcmath

# Composer vient de son image officielle : pas d'installateur a verifier a la main.
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Verification a la construction : mieux vaut echouer ici, avec un message clair, que
# de decouvrir une extension manquante au premier appel.
RUN php -m | grep -q pdo_mysql && php -m | grep -q intl \
 && echo "extensions chargees"

WORKDIR /app

COPY demarrer.sh /usr/local/bin/demarrer
RUN chmod +x /usr/local/bin/demarrer

CMD ["demarrer"]
