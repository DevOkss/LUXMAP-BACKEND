#!/usr/bin/env bash
set -euo pipefail
echo "== Restarting labsync-web to pick up luxmap vhost (brief blip) =="
docker restart labsync-web-1
sleep 3
docker ps --filter name=labsync-web-1 --format '{{.Names}} {{.Status}}'

echo "== Verify loaded config =="
docker exec labsync-web-1 grep -c "proxy_pass http://172.18.0.1:8090" /etc/nginx/conf.d/default.conf

echo "== Smoke tests =="
curl -s -m 20 -o /dev/null -w "https login: %{http_code}\n" https://luxmap.devokss.online/login
curl -s -m 20 -o /dev/null -w "https landing: %{http_code}\n" https://luxmap.devokss.online/app
curl -s -m 20 -o /dev/null -w "https favicon: %{http_code}\n" https://luxmap.devokss.online/storage/logos/luxmap.ico
curl -s -m 20 -o /dev/null -w "http redirect: %{http_code}\n" http://luxmap.devokss.online/login
curl -s -m 20 -L -o /dev/null -w "labsync still ok: %{http_code}\n" https://labsync.devokss.online
