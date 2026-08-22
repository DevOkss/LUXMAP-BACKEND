#!/usr/bin/env bash
set -euo pipefail
cd /var/www/soms

sed -i 's/^DB_PASSWORD=.*/DB_PASSWORD="C@s1pong143#"/' .env
grep -c '^DB_PASSWORD="C@s1pong143#"' .env >/dev/null && echo "env fixed"

mysql -e "CREATE USER IF NOT EXISTS 'soms_user'@'localhost' IDENTIFIED BY 'C@s1pong143#';"
mysql -e "GRANT SELECT,INSERT,UPDATE,DELETE,CREATE,ALTER,INDEX,DROP,REFERENCES ON soms.* TO 'soms_user'@'localhost';"
mysql -e "FLUSH PRIVILEGES;"
echo "localhost grant added"

php artisan config:clear >/dev/null
bash deploy/deploy.sh --first-deploy 2>&1 | tail -25
