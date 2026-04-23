# Hetzner Cloud Deployment For Kynex AI Booking

This guide targets the current production architecture of the app:

- Laravel 13
- PHP 8.4
- PostgreSQL
- shared-database multi-tenancy with `business_id`
- split services:
  - `nginx`
  - `app` (PHP-FPM)
  - `worker`
  - `scheduler`
- database-backed queue as the primary supported mode
- Redis / Horizon only as an optional later optimization

## Recommended Topology

For a first stable production deployment on Hetzner Cloud:

- 1 Ubuntu 24.04 VM for app services
- 1 managed or dedicated PostgreSQL instance
- 1 optional Redis / Valkey instance only if you later switch queue/cache mode
- DNS pointing `ai.kynexsolutions.com` to the VM
- TLS via Nginx + Let's Encrypt or a fronting reverse proxy

If you want the lowest operational risk, keep PostgreSQL external or managed and keep queue/cache/session on database for the initial rollout.

## Two Supported Deployment Modes

### Mode 1: Current Recommended Mode

Use the existing split container stack from the repo:

- `docker-compose.prod.yml`
- `Dockerfile`
- `Dockerfile.nginx`

This keeps parity with the current Coolify-ready architecture.

### Mode 2: Direct VM Services

Run:

- system Nginx
- system PHP-FPM
- `php artisan queue:work`
- `php artisan schedule:work`

This is supported later if you want to move away from containers, but container mode should be the default for now.

## Prerequisites

1. Provision an Ubuntu 24.04 server on Hetzner Cloud.
2. Point DNS for your domain to the server IP.
3. Open firewall ports:
   - `22`
   - `80`
   - `443`
4. Install Docker and Compose plugin:

```bash
sudo apt update
sudo apt install -y ca-certificates curl gnupg lsb-release
sudo install -m 0755 -d /etc/apt/keyrings
curl -fsSL https://download.docker.com/linux/ubuntu/gpg | sudo gpg --dearmor -o /etc/apt/keyrings/docker.gpg
echo \
  "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] \
  https://download.docker.com/linux/ubuntu $(. /etc/os-release && echo "$VERSION_CODENAME") stable" \
  | sudo tee /etc/apt/sources.list.d/docker.list > /dev/null
sudo apt update
sudo apt install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
sudo usermod -aG docker $USER
```

5. Re-login so your user picks up Docker group membership.

## Application Checkout

```bash
cd /srv
sudo mkdir -p /srv/kynex-ai-booking
sudo chown -R $USER:$USER /srv/kynex-ai-booking
git clone https://github.com/SMQ05/OmniChannel-AI.git /srv/kynex-ai-booking
cd /srv/kynex-ai-booking
cp .env.example .env
```

## Required Environment

At minimum set these in `.env`:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://ai.kynexsolutions.com
ASSET_URL=https://ai.kynexsolutions.com
TRUSTED_PROXIES=*

DB_CONNECTION=pgsql
DB_HOST=your-postgres-host
DB_PORT=5432
DB_DATABASE=your-db
DB_USERNAME=your-user
DB_PASSWORD=your-password

QUEUE_CONNECTION=database
CACHE_STORE=database
SESSION_DRIVER=database
LOG_CHANNEL=stderr
LOG_LEVEL=error

QUEUE_WEBHOOK_NAME=webhooks
QUEUE_INTEGRATIONS_NAME=integrations
QUEUE_REMINDERS_NAME=reminders

FEATURE_USAGE_ENFORCEMENT=true
FEATURE_VOICE_AGENT=false
```

Add business-specific provider secrets only through env or app settings:

- Meta / WhatsApp
- Messenger
- Google OAuth
- Google Calendar
- Google Sheets
- OpenRouter / Anthropic / OpenAI / MiniMax as needed
- Telnyx / SIP / Deepgram / ElevenLabs if enabling voice later

## Build And Start The Split Stack

The repo already contains the production compose topology.

```bash
docker compose -f docker-compose.prod.yml build
docker compose -f docker-compose.prod.yml up -d
```

Expected services:

- `app`
- `nginx`
- `worker`
- `scheduler`

## First Bootstrap

Run bootstrap steps inside the PHP app container:

```bash
docker compose -f docker-compose.prod.yml exec app php artisan key:generate
docker compose -f docker-compose.prod.yml exec app php artisan migrate --force
docker compose -f docker-compose.prod.yml exec app php artisan optimize:clear
docker compose -f docker-compose.prod.yml exec app php artisan config:cache
docker compose -f docker-compose.prod.yml exec app php artisan view:cache
```

If this is the first production deploy, verify these tables exist after migration:

- `jobs`
- `failed_jobs`
- `job_batches`
- `sessions`
- `cache`
- `cache_locks`
- `queue_worker_heartbeats`
- `plans`
- `business_subscriptions`
- `usage_events`
- `voice_channels`
- `call_logs`

## Queue Worker Process

The current production-safe worker command is:

```bash
php artisan queue:work database --queue=webhooks,integrations,reminders,billing --sleep=1 --tries=3 --timeout=60 --max-time=3600
```

This is already encoded in the worker container entrypoint.

Keep database queue as the default until:

- outbound traffic volume is materially higher
- retry volume is substantial
- you want Horizon visibility

## Scheduler Process

Use:

```bash
php artisan schedule:work
```

This is already encoded in the scheduler container entrypoint.

## Nginx Notes

If you keep the containerized topology, Nginx is already configured to proxy to:

```nginx
fastcgi_pass app:9000;
```

If you switch to system Nginx later, use a standard Laravel server block:

- `root /var/www/kynex-ai-booking/public;`
- `try_files $uri $uri/ /index.php?$query_string;`
- `fastcgi_pass unix:/run/php/php8.4-fpm.sock;`
- forward proxy headers correctly

Critical production note:

- keep `TRUSTED_PROXIES=*`
- keep HTTPS forced in production

## TLS / Reverse Proxy

You can terminate TLS either:

- directly in Nginx with Let's Encrypt
- in a fronting proxy such as Traefik or Caddy

If you terminate TLS ahead of Laravel:

- forward `X-Forwarded-Proto`
- keep `TRUSTED_PROXIES=*`

## Validation After Deploy

Run these checks after every release:

```bash
docker compose -f docker-compose.prod.yml ps
docker compose -f docker-compose.prod.yml exec app php artisan about
docker compose -f docker-compose.prod.yml exec app php artisan migrate:status
docker compose -f docker-compose.prod.yml exec app php artisan queue:failed
docker compose -f docker-compose.prod.yml exec app php artisan ops:smoke --strict-runtime --max-failed-jobs=0
docker compose -f docker-compose.prod.yml exec app php artisan schedule:list
docker compose -f docker-compose.prod.yml exec app php artisan route:list --path=api/webhook
```

Then verify in the browser:

1. `https://ai.kynexsolutions.com/api/health`
2. tenant dashboard
3. `/settings/diagnostics`
4. webhook URLs shown in diagnostics
5. WhatsApp test send
6. Messenger test send
7. Google Calendar test
8. Google Sheets test

## Backup Guidance

Back up:

- PostgreSQL database
- `.env`
- deployment manifests / compose files

Do not rely on local container filesystems for business-critical persistence.

Database should be the source of truth for:

- jobs
- sessions
- cache
- inbound webhook records
- outbound attempt logs
- usage events
- voice channel metadata
- call logs

## Logging Guidance

Keep:

```env
LOG_CHANNEL=stderr
LOG_LEVEL=error
```

Then ship container logs with:

- Docker logging driver
- journald
- Loki / Grafana
- Datadog
- or another centralized log sink

## Restart Guidance

For normal deploys:

```bash
git pull --ff-only origin main
docker compose -f docker-compose.prod.yml build
docker compose -f docker-compose.prod.yml up -d
docker compose -f docker-compose.prod.yml exec app php artisan migrate --force
docker compose -f docker-compose.prod.yml exec app php artisan config:cache
```

To restart only queue processing:

```bash
docker compose -f docker-compose.prod.yml restart worker
```

To restart only the scheduler:

```bash
docker compose -f docker-compose.prod.yml restart scheduler
```

## Optional Future Redis / Horizon Mode

Only switch when you need it operationally.

When ready:

1. provision Redis or Valkey
2. set:

```env
QUEUE_CONNECTION=redis
CACHE_STORE=redis
```

3. start Horizon or a Redis worker strategy
4. expose `/horizon` only behind authenticated admin access

Do not switch until you are ready to operate Redis as part of production.

See `docs/deployment/pilot-operations-runbook.md` for rollback, backup, restore, and alerting requirements.
