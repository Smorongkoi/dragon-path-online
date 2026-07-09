#!/usr/bin/env bash
set -euo pipefail

php artisan migrate --force
php artisan db:seed --class=GameSeedSeeder --force
php artisan config:cache
php artisan route:cache
php artisan view:cache

php artisan serve --host=0.0.0.0 --port="${PORT:-8080}"
