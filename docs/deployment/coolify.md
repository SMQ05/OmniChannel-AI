# Coolify Deployment For kynex OmniChannel AI

This repository now supports Coolify's Dockerfile deployment mode.

## Build Mode

- Source: GitHub repository
- Build pack: Dockerfile
- Dockerfile location: `./Dockerfile`
- Port: `8080`
- PHP runtime: `8.4`

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
- `RUN_MIGRATIONS=true`

## Runtime Behavior

The container starts three processes under Supervisor:

- Laravel web server on port `8080`
- `php artisan queue:work`
- `php artisan schedule:run` once per minute

## First Deploy Checklist

1. Add the environment variables in Coolify before the first deploy.
2. Set the service port to `8080`.
3. Enable persistent storage only if you intentionally need local files.
4. Deploy once with `RUN_MIGRATIONS=true`.
5. Verify `GET /api/health`.
6. Open `/settings/diagnostics` and confirm queue and scheduler heartbeats.

## Notes

- `.dockerignore` excludes local secrets and the untracked `personal/` directory from the build context.
- The current dependency set targets PHP `8.4`, so Coolify must build this image with the included PHP 8.4 Docker base.
- If you later split web, worker, and scheduler into separate Coolify services, remove the extra Supervisor programs and run dedicated start commands per service instead.
