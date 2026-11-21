#!/bin/sh
set -e

# No secrets are committed: generate an ephemeral key when none is provided.
# The API is stateless (no sessions or encrypted payloads), so a per-start key is safe for the PoC.
if [ -z "${APP_KEY}" ]; then
    APP_KEY="$(php artisan key:generate --show --no-ansi)"
    export APP_KEY
fi

touch "${DB_DATABASE}"
php artisan migrate --force --no-interaction

php artisan package:discover --ansi >/dev/null

exec "$@"
