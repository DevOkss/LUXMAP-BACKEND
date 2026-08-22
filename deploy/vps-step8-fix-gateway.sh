#!/usr/bin/env bash
set -euo pipefail
NET=$(docker inspect labsync-web-1 --format '{{range .NetworkSettings.Networks}}{{.Gateway}}{{end}}')
echo "labsync network gateway: $NET"

NGINX_CONF=/opt/labsync/.docker/nginx/default.conf
sed -i "s|http://172\.17\.0\.1:8090|http://${NET}:8090|g" "$NGINX_CONF"
grep -n "proxy_pass" "$NGINX_CONF" | tail -2

echo "== Host nginx must also listen on the gateway IP =="
sed -i "s|listen 127.0.0.1:8090;|listen 127.0.0.1:8090;\n    listen ${NET}:8090;|" /etc/nginx/sites-available/soms
grep -n "listen" /etc/nginx/sites-available/soms | head -3
nginx -t && systemctl reload nginx

docker exec labsync-web-1 nginx -t
docker exec labsync-web-1 nginx -s reload
sleep 1

echo "== Smoke tests =="
curl -s -m 15 -o /dev/null -w "https login: %{http_code}\n" https://luxmap.devokss.online/login
curl -s -m 15 -o /dev/null -w "https landing: %{http_code}\n" https://luxmap.devokss.online/app
curl -s -m 15 -o /dev/null -w "https favicon: %{http_code}\n" https://luxmap.devokss.online/storage/logos/luxmap.ico
curl -s -m 15 -o /dev/null -w "http redirect: %{http_code}\n" http://luxmap.devokss.online/login
curl -s -m 15 -o /dev/null -w "labsync still ok: %{http_code}\n" https://labsync.devokss.online
