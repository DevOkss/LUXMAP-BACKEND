# LuxMap (SOMS) — Production Deployment

**Live at:** https://luxmap.devokss.online (admin portal)
**Student PWA:** https://luxmap-topaz.vercel.app (repo: `D:\2026 FREELANCE\pwa-soms` — see its `DEPLOYMENT.md`)
**VPS:** `76.13.220.161` (Ubuntu 24.04, **shared** with LabSync + Hulagway)
**Server app root:** `/var/www/soms` (Laravel 12, PHP 8.3-fpm, MySQL db `soms`)

> SOMS is NOT standalone in production: it has no public port. TLS and the
> public vhost belong to the LabSync Docker nginx proxy. Full topology:
> `AVILA/labsync/DEPLOYMENT.md`. Fresh-box guide: `deploy/setup-vps.md`.

## Topology (one line)

Internet → labsync Docker nginx `web` container (80/443, TLS, cert `certs/luxmap/`)
→ `proxy_pass http://172.18.0.1:8090` → host nginx `deploy/nginx-soms.conf`
(installed at `/etc/nginx/sites-available/soms`) → `/var/www/soms/public` → php8.3-fpm.
Student PWA (Vercel) calls the JSON API at `https://luxmap.devokss.online/api/*`
(CORS via `CORS_ALLOWED_ORIGINS`).

## Config files (keep local ↔ VPS in sync)

| Purpose | Repo file | VPS location |
|---|---|---|
| Host nginx vhost (:8090) | `deploy/nginx-soms.conf` | `/etc/nginx/sites-available/soms` |
| Public TLS vhost (proxy) | `deploy/proxy/20-luxmap.conf` | `/opt/labsync/.docker/nginx/conf.d/20-luxmap.conf` (canonical copy also in the labsync repo — update both) |
| TLS cert | — | `/etc/letsencrypt/live/luxmap.devokss.online/` (host certbot), synced to `/opt/labsync/certs/luxmap/` on renewal by `/etc/letsencrypt/renewal-hooks/deploy/devokss-sync-certs.sh` |
| Env template | `deploy/env.production.example` | `/var/www/soms/.env` (never in git) |
| Deploy script | `deploy/deploy.sh` | run on server / by CI |
| Scheduler | — | `/etc/cron.d/luxmap-scheduler` (`* * * * * www-data cd /var/www/soms && php artisan schedule:run`) |

## HARD RULES (violations caused the Sept 2026 incidents)

1. **`SESSION_DOMAIN` must stay `null`** — a sibling app setting
   `.devokss.online` broke CSRF on every subdomain (perennial 419). Keep
   cookies host-scoped; the proxy purge-headers in `deploy/proxy/*.conf` clean
   up stale domain-scoped cookies — don't delete those blocks.
2. **Never `certbot --nginx`** — host nginx has no public vhost; certs are
   webroot-issued via `/opt/labsync/certbot/www` and synced by the renewal
   hook. `certbot certificates` must show all three devokss domains VALID.
3. **Never remove `trustProxies(at: '*')`** from `bootstrap/app.php` — behind
   the TLS-terminating proxy Laravel must see https for secure cookies.
4. `QR_ENCRYPTION_KEY` must be a 64-char hex key and match the PWA's
   `VITE_QR_KEY` — APP_KEY is base64 and produces invalid QR keys via hex2bin().
5. `.env` values containing `#` must be double-quoted (dotenv truncates).
6. Do NOT recreate `labsync-web-1` casually — it fronts all three sites.

## Deploy / update

CI/CD: push to `main` → tests workflow → `.github/workflows/deploy.yml` SSHes
in as root and runs `deploy/deploy.sh` (pull → composer → npm build → migrate →
caches → reload php-fpm). Secrets: `DEPLOY_SSH_KEY`, `DEPLOY_HOST=76.13.220.161`,
`DEPLOY_USER=root`. Manual:

```bash
ssh root@76.13.220.161
cd /var/www/soms && sudo -u www-data ./deploy/deploy.sh   # or --first-deploy on fresh box
```

## Verify after any infra change

```bash
"" | openssl s_client -connect 76.13.220.161:443 -servername luxmap.devokss.online 2>/dev/null | openssl x509 -noout -subject   # CN = luxmap.devokss.online
curl -sI https://luxmap.devokss.online/login | grep -i '^HTTP'          # 200
curl -sI https://luxmap.devokss.online/ | grep -i location              # must NOT leak raw IP
curl -sI https://hulagway.devokss.online/ https://labsync.devokss.online/login | grep -i '^HTTP'   # siblings unaffected
certbot certificates
```

## Incident history (2026-09-05)

- **ERR_CERT_COMMON_NAME_INVALID on luxmap**: the luxmap vhost was missing from
  the labsync proxy `default.conf` (clobbered while adding a sibling project);
  nginx fell back to the labsync default vhost → wrong cert; HSTS
  includeSubDomains made Chrome hard-block. Fixed by vhost restoration; proxy
  conf was split into per-project files in `.docker/nginx/conf.d/` so deploys
  can't clobber each other.
- **419 Page Expired on labsync**: sibling app leaked domain-scoped cookies —
  see HARD RULE 1. luxmap was at risk too (same shared `XSRF-TOKEN` name).
- Verified: certs (labsync adopted by certbot this day), conf.d split, purge
  headers, renewal hook covering all three domains.
- **500 on payment verify** (same day): `/var/www/soms/.env` had a duplicate
  empty `VAPID_PRIVATE_KEY=` line shadowing the real key (last wins); web push
  then threw `[VAPID] Private key should be 32 bytes long` inside
  `PaymentSubmissionService::verify`'s transaction → 500 + rollback. Fixed in
  `.env` + `config:cache`; `WebPushService::sendToUser` hardened to never
  rethrow. Rule: edit `.env` ⇒ ALWAYS re-run `php artisan config:cache`.
