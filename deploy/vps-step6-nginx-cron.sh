#!/usr/bin/env bash
set -euo pipefail
systemctl enable --now nginx
systemctl is-active nginx

echo "== Scheduler cron =="
cat > /etc/cron.d/luxmap-scheduler <<'CRON'
* * * * * www-data cd /var/www/soms && php artisan schedule:run >> /dev/null 2>&1
CRON
chmod 644 /etc/cron.d/luxmap-scheduler
systemctl restart cron
echo "CRON_OK"

echo "== HTTP smoke test =="
sleep 1
curl -s -o /dev/null -w "login: %{http_code}\n" http://127.0.0.1/login -H "Host: luxmap.devokss.online"
curl -s -o /dev/null -w "app landing: %{http_code}\n" http://127.0.0.1/app -H "Host: luxmap.devokss.online"
curl -s -o /dev/null -w "favicon: %{http_code}\n" http://127.0.0.1/storage/logos/luxmap.ico -H "Host: luxmap.devokss.online"
