#!/usr/bin/env bash
set -euo pipefail
ufw allow from 172.16.0.0/12 to any port 8090 proto tcp comment 'docker containers -> luxmap host nginx'
ufw status | grep 8090

sleep 1
echo "== Smoke tests =="
curl -s -m 20 -o /dev/null -w "https login: %{http_code}\n" https://luxmap.devokss.online/login
curl -s -m 20 -o /dev/null -w "https landing: %{http_code}\n" https://luxmap.devokss.online/app
curl -s -m 20 -o /dev/null -w "https favicon: %{http_code}\n" https://luxmap.devokss.online/storage/logos/luxmap.ico
curl -s -m 20 -o /dev/null -w "http redirect: %{http_code}\n" http://luxmap.devokss.online/login
curl -s -m 20 -o /dev/null -w "labsync still ok: %{http_code}\n" https://labsync.devokss.online
