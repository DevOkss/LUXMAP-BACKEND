#!/usr/bin/env bash
set -uo pipefail
echo "== host =="
md5sum /opt/labsync/.docker/nginx/default.conf
grep -n "proxy_pass" /opt/labsync/.docker/nginx/default.conf
echo "== container =="
docker exec labsync-web-1 md5sum /etc/nginx/conf.d/default.conf
docker exec labsync-web-1 grep -n "proxy_pass" /etc/nginx/conf.d/default.conf
echo "== mount table (container) =="
docker exec labsync-web-1 sh -c "cat /proc/mounts | grep nginx"
echo "== host mountinfo =="
grep "nginx" /proc/self/mountinfo | head -3
