#!/usr/bin/env bash
set -euo pipefail
NGINX_CONF=/opt/labsync/.docker/nginx/default.conf

# In-place content replace WITHOUT changing the inode (single-file bind mount!)
sed 's|http://172\.17\.0\.1:8090|http://172.18.0.1:8090|g' "$NGINX_CONF" > /tmp/default.conf.new
cat /tmp/default.conf.new > "$NGINX_CONF"
rm /tmp/default.conf.new

grep -c "proxy_pass http://172.18.0.1:8090" "$NGINX_CONF"

docker exec labsync-web-1 nginx -t
docker exec labsync-web-1 nginx -s reload
sleep 1

echo "== Verify loaded config =="
docker exec labsync-web-1 nginx -T 2>/dev/null | grep -c "proxy_pass http://172.18.0.1:8090"

echo "== Smoke tests =="
curl -s -m 20 -o /dev/null -w "https login: %{http_code}\n" https://luxmap.devokss.online/login
curl -s -m 20 -o /dev/null -w "https landing: %{http_code}\n" https://luxmap.devokss.online/app
curl -s -m 20 -o /dev/null -w "https favicon: %{http_code}\n" https://luxmap.devokss.online/storage/logos/luxmap.ico
curl -s -m 20 -o /dev/null -w "http redirect: %{http_code}\n" http://luxmap.devokss.online/login
curl -s -m 20 -o /dev/null -w "labsync still ok: %{http_code}\n" https://labsync.devokss.online
