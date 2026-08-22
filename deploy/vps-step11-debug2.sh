#!/usr/bin/env bash
set -uo pipefail
echo "== Config as loaded (luxmap sections) =="
docker exec labsync-web-1 nginx -T 2>/dev/null | grep -n "luxmap\|proxy_pass\|listen" | head -20

echo "== Error logs =="
docker exec labsync-web-1 sh -c "ls /var/log/nginx/ 2>/dev/null"
docker exec labsync-web-1 sh -c "cat /var/log/nginx/error.log 2>/dev/null | tail -5"
docker logs labsync-web-1 2>&1 | tail -5

echo "== Trigger a request and re-read errors =="
curl -s -m 15 -o /dev/null https://luxmap.devokss.online/login || true
sleep 1
docker exec labsync-web-1 sh -c "tail -3 /var/log/nginx/error.log 2>/dev/null"
docker logs labsync-web-1 2>&1 | tail -3
