# Coolify Deployment For kynex OmniChannel AI

This repository now supports a split Coolify deployment:

- public Nginx web service
- internal PHP-FPM app service
- queue worker service
- scheduler service

The previous single-container `artisan serve` + Supervisor topology has been removed from the Docker runtime.

## Services

### 1. Nginx web service

- Source: GitHub repository
- Build pack: Dockerfile
- Dockerfile location: `./Dockerfile.nginx`
- Port: `8080`
- Public: yes
- Required env:
  - `APP_UPSTREAM=app:9000`
  - `PORT=8080`

### 2. PHP-FPM app service

- Source: GitHub repository
- Build pack: Dockerfile
- Dockerfile location: `./Dockerfile`
- Port: `9000`
- Public: no
- Start command: default image command

### 3. Queue worker service

- Source: GitHub repository
- Build pack: Dockerfile
- Dockerfile location: `./Dockerfile`
- Port: none
- Public: no
- Start command: `/usr/local/bin/start-queue`

### 4. Scheduler service

- Source: GitHub repository
- Build pack: Dockerfile
- Dockerfile location: `./Dockerfile`
- Port: none
- Public: no
- Start command: `/usr/local/bin/start-scheduler`

## Required Environment Variables

Set at minimum:

- `APP_NAME`
- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_KEY`
- `APP_URL`
- `DB_CONNECTION`
- `DB_HOST`
- `DB_PORT`
- `DB_DATABASE`
- `DB_USERNAME`
- `DB_PASSWORD`

Recommended defaults:

- `QUEUE_CONNECTION=database`
- `CACHE_STORE=database`
- `SESSION_DRIVER=database`
- `LOG_CHANNEL=stderr`
- `LOG_LEVEL=error`

Set only on the PHP-FPM app service when you want a one-time schema rollout:

- `RUN_MIGRATIONS=true`

## Runtime Behavior

- PHP-FPM app service listens on port `9000`
- Queue worker runs:
  `php artisan queue:work database --queue=webhooks,integrations,reminders --sleep=1 --tries=3 --timeout=60 --max-time=3600`
- Scheduler runs:
  `php artisan schedule:work`
- Nginx handles HTTP on port `8080` and forwards PHP requests to the internal app service

## First Deploy Checklist

1. Add the environment variables in Coolify before the first deploy.
2. Create the PHP-FPM app service first and keep it internal-only.
3. Create the Nginx service and point `APP_UPSTREAM` at the PHP-FPM service hostname on port `9000`.
4. Create separate worker and scheduler services using the same app image.
5. Enable persistent storage only if you intentionally need local files.
6. Deploy once with `RUN_MIGRATIONS=true` on the app service, then turn it back off.
7. Verify `GET /api/health`.
8. Open `/settings/diagnostics` and confirm queue and scheduler heartbeats.

## Notes

- `.dockerignore` excludes local secrets and the untracked `personal/` directory from the build context.
- The application still targets PHP `8.4`, so the runtime stays on a PHP 8.4 FPM base instead of 8.3.
- Queue and cache remain database-backed by default; Redis stays optional for later scale-up.
