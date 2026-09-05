# LuxMap (SOMS) — Hostinger VPS Setup Guide (Ubuntu 22.04/24.04, Native LEMP)

Target: fresh Ubuntu VPS at `76.13.220.161`, app served at **https://luxmap.devokss.online**.

> ⚠️ On the CURRENT production box, SOMS shares the VPS with two other apps and
> is NOT standalone: TLS + public ports belong to the LabSync Docker nginx
> proxy. Read `DEPLOYMENT.md` (repo root) before touching anything, and never
> run `certbot --nginx` here — see section 7.

> Run every command as root (or with sudo) unless noted. The deploy user in the
> examples is `deploy` — adjust if you use another.

---

## 1. Base packages

```bash
apt update && apt upgrade -y
apt install -y nginx mysql-server unzip git curl ufw

# PHP 8.3 + required extensions
apt install -y apt-transport-https lsb-release ca-certificates software-properties-common
add-apt-repository -y ppa:ondrej/php
apt update
apt install -y php8.3-fpm php8.3-cli php8.3-mysql php8.3-mbstring \
    php8.3-gd php8.3-zip php8.3-xml php8.3-curl php8.3-bcmath \
    php8.3-intl php8.3-gmp

# Node 20 (for building frontend assets)
curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
apt install -y nodejs

# Composer
curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Let's Encrypt
apt install -y certbot python3-certbot-nginx
```

## 2. Firewall

```bash
ufw allow OpenSSH
ufw allow 'Nginx Full'
ufw --force enable
```

## 3. MySQL database (fresh install)

```bash
mysql_secure_installation   # set root auth as you prefer

mysql -e "CREATE DATABASE soms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -e "CREATE USER 'soms_user'@'127.0.0.1' IDENTIFIED BY '<MYSQL_PASSWORD>';"
mysql -e "GRANT SELECT,INSERT,UPDATE,DELETE,CREATE,ALTER,INDEX,DROP,REFERENCES ON soms.* TO 'soms_user'@'127.0.0.1';"
mysql -e "FLUSH PRIVILEGES;"
```

Use the same password in `.env` (`DB_PASSWORD`).

## 4. Get the code

```bash
adduser --disabled-password --gecos "" deploy
usermod -aG www-data deploy
mkdir -p /var/www/soms
chown deploy:www-data /var/www/soms

sudo -iu deploy
git clone <YOUR_REPO_URL> /var/www/soms
cd /var/www/soms
```

If the repo is private, add a deploy key first:
`ssh-keygen -t ed25519 -f ~/.ssh/id_ed25519 -N ""` then upload the public key
to the repo host as a read-only deploy key.

## 5. Environment

```bash
cp deploy/env.production.example .env
nano .env    # fill: DB_PASSWORD, PWA_URL, CORS_ALLOWED_ORIGINS,
             #       QR_ENCRYPTION_KEY, VAPID_*
php artisan key:generate
```

Generate secrets:

| Secret | Command |
|---|---|
| `QR_ENCRYPTION_KEY` | `openssl rand -hex 32` |
| `VAPID_PUBLIC_KEY` / `VAPID_PRIVATE_KEY` | `php artisan webpush:vapid` (prints a pair) — or any VAPID generator |

**Important:** `CORS_ALLOWED_ORIGINS` must be the exact deployed PWA origin
(e.g. `https://luxmap.vercel.app`) and the PWA's `VITE_API_URL` must be set to
`https://luxmap.devokss.online` in the PWA project before it will talk to this API.

## 6. First deploy

```bash
chmod +x deploy/deploy.sh
./deploy.sh --first-deploy
```

This pulls deps, builds assets, migrates, seeds, links storage (`public/storage`
→ `storage/app/public`, which exposes `/storage/logos/luxmap.ico|png`), caches
config, and reloads PHP-FPM.

## 7. Nginx + HTTPS

This box's nginx only listens on internal ports — the LabSync Docker proxy
owns public 80/443 (see `DEPLOYMENT.md`). **Do NOT run `certbot --nginx`.**

```bash
cp /var/www/soms/deploy/nginx-soms.conf /etc/nginx/sites-available/soms
ln -sf /etc/nginx/sites-available/soms /etc/nginx/sites-enabled/soms
nginx -t && systemctl reload nginx

# ufw: let the Docker network reach the internal port
ufw allow from 172.16.0.0/12 to any port 8090

# Public vhost (proxy side): copy deploy/proxy/20-luxmap.conf to
# /opt/labsync/.docker/nginx/conf.d/20-luxmap.conf, then:
docker exec labsync-web-1 nginx -t && docker exec labsync-web-1 nginx -s reload
```

Certificate (DNS A record `luxmap.devokss.online` → `76.13.220.161` must exist):

```bash
certbot certonly --webroot -w /opt/labsync/certbot/www -d luxmap.devokss.online \
  --non-interactive --agree-tos -m your@email.com
# sync into the proxy + install renewal hook (see AVILA/labsync repo):
cd /opt/labsync && bash scripts/install-renewal-hook.sh
```

## 8. Scheduler cron (fee-due reminders, daily 08:00)

```bash
crontab -u www-data -e
```

Add:

```
* * * * * cd /var/www/soms && php artisan schedule:run >> /dev/null 2>&1
```

## 9. Smoke tests

1. `https://luxmap.devokss.online/login` — admin login renders with the LuxMap favicon
2. `https://luxmap.devokss.online/app` — PWA landing page renders, hero image loads
3. Log in as an officer/head — dashboard renders charts
4. From the deployed PWA origin: log in as a student (tests CORS + Sanctum token)
5. Generate a QR on an event and scan it from the PWA (tests QR encryption key)

## Updating later

```bash
cd /var/www/soms && ./deploy.sh
```

Or just push to `main` — CI/CD handles it (see below).

---

## CI/CD — automatic deploy on push to `main`

The repo ships `.github/workflows/deploy.yml`: after the `tests` workflow
passes on `main`, it SSHes into this VPS and runs `deploy/deploy.sh`.
Manual runs are also possible from the GitHub Actions tab
(**deploy-production → Run workflow**).

One-time setup:

1. **Authorize a key for GitHub Actions on the VPS** — generate one with
   `ssh-keygen -t ed25519 -f github_actions_deploy -N ""` and append the
   public key to `/root/.ssh/authorized_keys`:
   ```bash
   echo 'ssh-ed25519 <...> github-actions-luxmap-deploy' >> /root/.ssh/authorized_keys
   ```
2. **Add three repository secrets** (GitHub → Settings → Secrets and
   variables → Actions):
   | Secret | Value |
   |---|---|
   | `DEPLOY_SSH_KEY` | full contents of the **private** key (`github_actions_deploy`) |
   | `DEPLOY_HOST` | `76.13.220.161` |
   | `DEPLOY_USER` | `root` |

Flow: push to `main` → tests run (PHP 8.3, Pest) → if green, VPS pulls code,
rebuilds assets, migrates, and reloads PHP-FPM.

> Note: the VPS must be able to read the repo for `git pull`. If the GitHub
> repo is private, add a read-only **deploy key** (another ed25519 pair's
> *public* half) in GitHub → repo → Settings → Deploy keys.

---

## Troubleshooting

- **500 errors**: `tail -f /var/www/soms/storage/logs/laravel.log`
- **Blank page / mixed content**: confirm `APP_URL=https://...` and rerun `./deploy.sh`
- **CORS errors from the PWA**: `CORS_ALLOWED_ORIGINS` must match the PWA origin exactly
- **Queue jobs never run?** Not needed — the app has no queued jobs; pushes are synchronous
