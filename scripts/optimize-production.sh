#!/usr/bin/env bash
set -euo pipefail

# Run after deploy on production/staging to cache config, routes, views, and events.
# Usage: ./scripts/optimize-production.sh

php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

echo "Production caches warmed (config, routes, views, events)."
