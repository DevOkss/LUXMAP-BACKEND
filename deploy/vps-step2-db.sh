#!/usr/bin/env bash
set -euo pipefail

echo "== MySQL =="
systemctl is-active --quiet mysql || systemctl enable --now mysql
mysql -e "CREATE DATABASE IF NOT EXISTS soms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -e "CREATE USER IF NOT EXISTS 'soms_user'@'127.0.0.1' IDENTIFIED BY 'C@s1pong143#';"
mysql -e "GRANT SELECT,INSERT,UPDATE,DELETE,CREATE,ALTER,INDEX,DROP,REFERENCES ON soms.* TO 'soms_user'@'127.0.0.1';"
mysql -e "FLUSH PRIVILEGES;"
mysql -h127.0.0.1 -usoms_user -p'C@s1pong143#' -e "SELECT 'DB_LOGIN_OK';" >/dev/null && echo "DB_OK: database + user verified"

echo "== Firewall =="
ufw allow OpenSSH >/dev/null 2>&1 || true
ufw allow "Nginx Full" >/dev/null 2>&1 || true
ufw --force enable | grep -v "^$" | tail -1 || true
ufw status | head -6
