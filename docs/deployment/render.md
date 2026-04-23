# Render Deployment For kynex OmniChannel AI

## Services

Create three Render services:

1. Web service for HTTP traffic.
2. Worker service for queue processing.
3. Cron service for the Laravel scheduler.

`render.yaml` in the project root defines the baseline configuration.

## Required Environment Variables

Set at minimum:

- `APP_KEY`
- `APP_URL`
- `DB_CONNECTION`
- `DB_HOST`
- `DB_PORT`
- `DB_DATABASE`
- `DB_USERNAME`
- `DB_PASSWORD`
- `QUEUE_CONNECTION`
- `CACHE_STORE`
- `SESSION_DRIVER`
- Meta and Google credentials used by your tenants

For Render testing, prefer:

- `QUEUE_CONNECTION=database`
- `CACHE_STORE=database`
- `SESSION_DRIVER=database`

If you enable Redis on Render, switch queue/cache env vars accordingly and provide `REDIS_URL`.

## Deploy Flow

1. Push the codebase.
2. Let GitHub Actions run `.github/workflows/ci.yml` on the branch or PR first.
3. Create a PostgreSQL or MySQL instance on Render.
4. Provision the web, worker, and cron services from `render.yaml`.
5. Set `APP_KEY` and all provider credentials in the Render dashboard.
6. Deploy once so the build runs:
   `composer install --no-dev --optimize-autoloader`
7. Let the web build run:
   `php artisan migrate --force`
8. Confirm the worker is running:
   `php artisan queue:work ${QUEUE_CONNECTION} --queue=webhooks,integrations,reminders,billing --sleep=1 --tries=3 --timeout=60 --max-time=3600`
9. Confirm the cron service runs:
   `php artisan schedule:work`
10. Open `/settings/diagnostics` and verify:
   queue connectivity, worker heartbeat, scheduler heartbeat, and webhook URLs.
11. Hit the public health endpoint:
   `GET https://your-app.onrender.com/api/health`
12. Run the smoke command in the Render shell:
   `php artisan ops:smoke --url=https://your-app.onrender.com/api/health --strict-runtime --max-failed-jobs=0`
13. Inspect queue failures if needed:
   `php artisan queue:failed`
14. Retry failures if needed:
   `php artisan queue:retry all`
15. Inspect webhook routes if needed:
   `php artisan route:list --path=api/webhook`

## Ephemeral Filesystem Notes

- Treat Render local storage as ephemeral.
- Do not rely on `storage/` for durable business data.
- Queue payloads, webhook replay data, failed jobs, reminders, diagnostics heartbeats, and usage events are database-backed.
- If public uploads are added later, move them to object storage.

## Repeatable Post-Deploy Checklist

Run these after every Render deploy until the system is stable:

1. `php artisan migrate --force`
2. `php artisan ops:smoke --url=https://your-app.onrender.com/api/health --strict-runtime --max-failed-jobs=0`
3. `php artisan queue:failed`
4. `php artisan schedule:list`
5. Open `/settings/diagnostics`
6. Send a live WhatsApp message through Meta and confirm:
   inbound webhook row created, async job processed, outbound attempt recorded, reply delivered

See `docs/deployment/pilot-operations-runbook.md` for rollback, backup, restore, and alerting requirements.
