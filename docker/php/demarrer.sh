#!/usr/bin/env bash
# =============================================================================
#  Demarrage de l'API sinistres dans le conteneur.
#
#  Il attend MySQL, installe les dependances si besoin, prepare la base et sert
#  l'application. Rejouable sans dommage : relancer « docker compose up » ne
#  reinstalle rien et ne rejoue pas les migrations deja passees.
# =============================================================================
set -euo pipefail

titre() { printf '\n\033[36m=== %s ===\033[0m\n' "$1"; }
bon()   { printf '  \033[32m[ok]\033[0m  %s\n' "$1"; }
info()  { printf '  [..]  %s\n' "$1"; }
souci() { printf '  \033[31m[!!]\033[0m  %s\n' "$1"; }

# ------------------------------------------------------------------ 1. la base
titre "MySQL"
info "attente de la disponibilite du moteur (jusqu'a 120 s)"

for i in $(seq 1 60); do
    if mysqladmin ping -h "${DB_HOST}" -u"${DB_USERNAME}" -p"${DB_PASSWORD}" --silent 2>/dev/null; then
        bon "moteur joignable sur ${DB_HOST}"
        break
    fi
    if [ "$i" -eq 60 ]; then
        souci "MySQL ne repond pas. Reprendre avec : docker compose down -v && docker compose up"
        exit 1
    fi
    sleep 2
done

# ----------------------------------------------------------- 2. les dependances
titre "Dependances"
if [ ! -f vendor/autoload.php ]; then
    info "premiere installation, comptez deux a trois minutes"
    composer install --no-interaction --no-progress --prefer-dist
    bon "dependances installees"
else
    bon "deja installees"
fi

# ------------------------------------------------------------ 3. configuration
titre "Configuration"
if [ ! -f .env ]; then
    cp .env.example .env
    bon ".env cree depuis .env.example"
fi

# Les variables du conteneur l'emportent sur .env : Laravel ne reecrit jamais une
# valeur deja presente dans l'environnement. Seule la clef doit etre dans le fichier.
if ! grep -qE '^APP_KEY=base64:' .env; then
    php artisan key:generate --force --no-interaction
    bon "clef applicative generee"
else
    bon "clef applicative deja posee"
fi

mkdir -p storage/app/sinistres storage/framework/{cache,sessions,views} bootstrap/cache
chmod -R 777 storage bootstrap/cache 2>/dev/null || true

php artisan config:clear --quiet || true

# -------------------------------------------------------------- 4. les donnees
titre "Base de donnees"
if php artisan migrate:status >/dev/null 2>&1; then
    info "migrations deja en place, mise a jour seule"
    php artisan migrate --force --no-interaction
    bon "schema a jour"
else
    info "premiere mise en place, avec les dossiers de demonstration"
    php artisan migrate --seed --force --no-interaction
    bon "schema et donnees de demonstration en place"
fi

# ------------------------------------------------------------------ 5. service
titre "API sinistres Vie"
printf '\n'
printf '  L API repond sur  \033[1mhttp://localhost:%s\033[0m\n' "${PORT_HOTE:-8000}"
printf '  Verification      http://localhost:%s/api/v1/ping\n' "${PORT_HOTE:-8000}"
printf '\n'
printf '  Comptes de demonstration, mot de passe « password » :\n'
printf '    courrier@nsia-vie.test        agent du bureau courrier\n'
printf '    gestionnaire1@nsia-vie.test   gestionnaire sinistres\n'
printf '    medecin@nsia-vie.test         medecin-conseil\n'
printf '    responsable@nsia-vie.test     responsable sinistres\n'
printf '    comptable@nsia-vie.test       comptable\n'
printf '    admin@nsia-vie.test           administrateur\n'
printf '\n'

# 0.0.0.0 et non 127.0.0.1 : sans cela le serveur n ecoute que dans le conteneur et
# la redirection de port ne mene nulle part.
exec php artisan serve --host=0.0.0.0 --port=8000
