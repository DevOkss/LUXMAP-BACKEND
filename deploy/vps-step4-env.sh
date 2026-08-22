#!/usr/bin/env bash
set -euo pipefail
cd /var/www/soms

echo "== Writing .env =="
cat > .env <<'ENVEOF'
APP_NAME=LuxMap
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://luxmap.devokss.online

APP_LOCALE=en
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=en_US

APP_MAINTENANCE_DRIVER=file

PHP_CLI_SERVER_WORKERS=4

BCRYPT_ROUNDS=12

LOG_CHANNEL=stack
LOG_STACK=single
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=soms
DB_USERNAME=soms_user
DB_PASSWORD=C@s1pong143#

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=null

BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local
QUEUE_CONNECTION=database

CACHE_STORE=database
CACHE_PREFIX=

MEMCACHED_HOST=127.0.0.1

REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

MAIL_MAILER=log
MAIL_SCHEME=null
MAIL_HOST=127.0.0.1
MAIL_PORT=2525
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_FROM_ADDRESS="hello@example.com"
MAIL_FROM_NAME="${APP_NAME}"

AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=
AWS_USE_PATH_STYLE_ENDPOINT=false

VITE_APP_NAME="${APP_NAME}"

PWA_URL=https://luxmap-topaz.vercel.app

CORS_ALLOWED_ORIGINS=https://luxmap-topaz.vercel.app

VAPID_SUBJECT=mailto:admin@devokss.online
VAPID_PUBLIC_KEY=
VAPID_PRIVATE_KEY=

QR_ENCRYPTION_KEY=__QR_KEY__

PAYMONGO_API_URL=https://api.paymongo.com/v2
PAYMONGO_SECRET_KEY=
PAYMONGO_WEBHOOK_SECRET=
PAYMONGO_SUCCESS_URL=
PAYMONGO_CANCEL_URL=

INSTITUTION_API_URL=
ENVEOF

QR_KEY=$(openssl rand -hex 32)
sed -i "s/__QR_KEY__/${QR_KEY}/" .env
chmod 600 .env
echo ".env written (QR key generated)"

echo "== Composer install =="
composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist 2>&1 | tail -3

echo "== APP key =="
php artisan key:generate --force

echo "== First deploy =="
bash deploy/deploy.sh --first-deploy 2>&1 | tail -15
