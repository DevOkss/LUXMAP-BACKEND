#!/usr/bin/env bash
set -uo pipefail
echo "== Host listening sockets on 8090 =="
ss -tlnp | grep 8090 || echo "NOT LISTENING"

echo "== Test from inside the container =="
docker exec labsync-web-1 sh -c "wget -qO- -T5 http://172.18.0.1:8090/login 2>&1 >/dev/null; echo wget_exit=\$?" || true
docker exec labsync-web-1 sh -c "wget -S -T5 -O /dev/null http://172.18.0.1:8090/up 2>&1 | head -3" || true

echo "== Container nginx error log tail =="
docker exec labsync-web-1 sh -c "tail -5 /var/log/nginx/error.log 2>/dev/null || echo 'no error log'"

echo "== iptables INPUT for 8090 =="
iptables -L INPUT -n --line-numbers | grep -E "8090|DROP|REJECT|policy" | head -8

echo "== ufw status verbose =="
ufw status verbose | grep -E "8090|Default"
