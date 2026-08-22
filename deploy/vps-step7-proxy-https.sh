#!/usr/bin/env bash
set -euo pipefail

echo "== Pull latest code (trust-proxies fix + internal nginx config) =="
cd /var/www/soms
git pull --ff-only
composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist 2>&1 | tail -1
php artisan config:cache >/dev/null

echo "== Host nginx -> internal 127.0.0.1:8090 =="
cp /var/www/soms/deploy/nginx-soms.conf /etc/nginx/sites-available/soms
nginx -t
systemctl enable --now nginx || true
systemctl reload nginx || systemctl restart nginx
systemctl is-active nginx
curl -s -o /dev/null -w "internal login page: %{http_code}\n" http://127.0.0.1:8090/login

echo "== LuxMap vhost in labsync docker nginx (phase 1: HTTP + ACME) =="
NGINX_CONF=/opt/labsync/.docker/nginx/default.conf
if ! grep -q "luxmap.devokss.online" "$NGINX_CONF"; then
    cp "$NGINX_CONF" "${NGINX_CONF}.bak"
    cat >> "$NGINX_CONF" <<'VHOST'

# ── LuxMap (SOMS) — reverse proxy to host nginx on 127.0.0.1:8090 ───────────
server {
    listen 80;
    listen [::]:80;
    server_name luxmap.devokss.online;

    location /.well-known/acme-challenge/ {
        root /var/www/certbot;
    }

    location / {
        proxy_pass http://172.17.0.1:8090;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_http_version 1.1;
    }
}
VHOST
    echo "vhost appended"
else
    echo "vhost already present"
fi
docker exec labsync-web-1 nginx -t
docker exec labsync-web-1 nginx -s reload
sleep 1
curl -s -o /dev/null -w "public http login page: %{http_code}\n" -H "Host: luxmap.devokss.online" http://127.0.0.1/login

echo "== Issue certificate =="
certbot certonly --webroot -w /opt/labsync/certbot/www -d luxmap.devokss.online \
    --non-interactive --agree-tos -m admin@devokss.online --keep-until-expiring

echo "== Install certs where the container can see them =="
mkdir -p /opt/labsync/certs/luxmap
cp -L /etc/letsencrypt/live/luxmap.devokss.online/fullchain.pem /opt/labsync/certs/luxmap/
cp -L /etc/letsencrypt/live/luxmap.devokss.online/privkey.pem /opt/labsync/certs/luxmap/
chmod 644 /opt/labsync/certs/luxmap/*.pem
ls -la /opt/labsync/certs/luxmap/

echo "== LuxMap vhost phase 2: enable HTTPS block =="
cat >> "$NGINX_CONF" <<'VHOST'

# ── LuxMap (SOMS) — HTTPS ────────────────────────────────────────────────────
server {
    listen 443 ssl;
    listen [::]:443 ssl;
    http2 on;
    server_name luxmap.devokss.online;

    ssl_certificate     /etc/nginx/certs/luxmap/fullchain.pem;
    ssl_certificate_key /etc/nginx/certs/luxmap/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_session_cache shared:SSL_LUXMAP:10m;
    ssl_session_timeout 1d;

    client_max_body_size 20M;

    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;

    location / {
        proxy_pass http://172.17.0.1:8090;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto https;
        proxy_http_version 1.1;
        proxy_read_timeout 120s;
    }
}
VHOST
docker exec labsync-web-1 nginx -t
docker exec labsync-web-1 nginx -s reload
echo "HTTPS enabled"

echo "== Renewal deploy hook =="
mkdir -p /etc/letsencrypt/renewal-hooks/deploy
cat > /etc/letsencrypt/renewal-hooks/deploy/luxmap-cert-sync.sh <<'HOOK'
#!/bin/bash
set -e
cp -L /etc/letsencrypt/live/luxmap.devokss.online/fullchain.pem /opt/labsync/certs/luxmap/
cp -L /etc/letsencrypt/live/luxmap.devokss.online/privkey.pem /opt/labsync/certs/luxmap/
chmod 644 /opt/labsync/certs/luxmap/*.pem
docker exec labsync-web-1 nginx -s reload
HOOK
chmod +x /etc/letsencrypt/renewal-hooks/deploy/luxmap-cert-sync.sh
echo "hook installed"

echo "== Final smoke tests =="
sleep 1
curl -s -o /dev/null -w "https redirect: %{http_code}\n" http://luxmap.devokss.online/login
curl -s -o /dev/null -w "https login: %{http_code}\n" https://luxmap.devokss.online/login
curl -s -o /dev/null -w "https landing: %{http_code}\n" https://luxmap.devokss.online/app
curl -s -o /dev/null -w "https favicon: %{http_code}\n" https://luxmap.devokss.online/storage/logos/luxmap.ico
curl -s -o /dev/null -w "labsync still ok: %{http_code}\n" https://labsync.devokss.online
