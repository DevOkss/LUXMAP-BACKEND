#!/usr/bin/env bash
#
# LuxMap (SOMS) deployment script — runs ON the VPS.
# Usage:
#   ./deploy.sh                # normal update (code + assets + migrate + caches)
#   ./deploy.sh --first-deploy # first install: also seeds the database
#
set -euo pipefail

APP_DIR="/var/www/soms"
FIRST_DEPLOY=false
[[ "${1:-}" == "--first-deploy" ]] && FIRST_DEPLOY=true

cd "$APP_DIR"

if [[ ! -f .env ]]; then
    echo "ERROR: $APP_DIR/.env not found."
    echo "Create it from deploy/env.production.example first (see deploy/setup-vps.md)."
    exit 1
fi

echo "==> Pulling latest code"
git pull --ff-only

echo "==> Installing PHP dependencies"
composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

echo "==> Installing Node dependencies & building assets"
npm ci --no-audit --fund=false
npm run build

echo "==> Running migrations"
php artisan migrate --force

if $FIRST_DEPLOY; then
    echo "==> Linking storage"
    php artisan storage:link

    echo "==> Seeding database (--first-deploy)"
    php artisan db:seed --force
else
    # storage:link is idempotent; keep it in normal deploys too in case it was lost
    php artisan storage:link || true
fi

echo "==> Caching configuration"
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache || true

echo "==> Fixing permissions"
sudo chown -R www-data:www-data storage bootstrap/cache
sudo find storage bootstrap/cache -type d -exec chmod 775 {} +
sudo find storage bootstrap/cache -type f -exec chmod 664 {} +

echo "==> Reloading PHP-FPM"
sudo systemctl reload php8.3-fpm

echo "✅ Deploy complete."
