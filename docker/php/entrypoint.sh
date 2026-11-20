#!/bin/sh
set -eu

# Config, routes, events and views are cached when the container starts, not at build time:
# the image holds no .env and no secrets, so configuration only exists once the environment
# is injected. Migrations are NOT run here; they are a separate one-off command (README).
php artisan optimize --no-interaction

exec "$@"
