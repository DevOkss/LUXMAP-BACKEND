#!/usr/bin/env bash
set -euo pipefail
cd /var/www/soms

echo "== VAPID keys =="
if grep -q '^VAPID_PUBLIC_KEY=$' .env; then
  php -r '
require "vendor/autoload.php";
$keys = Minishlink\WebPush\VAPID::createVapidKeys();
file_put_contents("/tmp/vapid.env", "VAPID_PUBLIC_KEY={$keys["publicKey"]}\nVAPID_PRIVATE_KEY={$keys["privateKey"]}\n");
echo "VAPID generated\n";
'
  sed -i '/^VAPID_PUBLIC_KEY=$/{r /tmp/vapid.env
d}' .env
  rm -f /tmp/vapid.env
else
  echo "VAPID already set"
fi
grep -q '^VAPID_PUBLIC_KEY=.\+' .env && echo "VAPID_OK"

php artisan config:cache >/dev/null

echo "== Nginx =="
cp deploy/nginx-soms.conf /etc/nginx/sites-available/soms
ln -sf /etc/nginx/sites-available/soms /etc/nginx/sites-enabled/soms
rm -f /etc/nginx/sites-enabled/default
nginx -t
systemctl reload nginx
echo "NGINX_OK"

echo "== Scheduler cron =="
cat > /etc/cron.d/luxmap-scheduler <<'CRON'
* * * * * www-data cd /var/www/soms && php artisan schedule:run >> /dev/null 2>&1
CRON
chmod 644 /etc/cron.d/luxmap-scheduler
systemctl restart cron
echo "CRON_OK"
