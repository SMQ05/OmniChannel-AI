# Hetzner Deployment For kynex OmniChannel AI

## Stack

- Ubuntu 24.04 LTS
- Nginx
- PHP 8.4 + PHP-FPM
- MySQL 8 or PostgreSQL 16
- Redis or Valkey
- Supervisor or systemd for queue workers
- Cron for Laravel scheduler
- Horizon only when `QUEUE_CONNECTION=redis`

## Provisioning

1. Create the server and point DNS to it.
2. Install packages:
   `sudo apt update`
   `sudo apt install -y nginx php8.4 php8.4-fpm php8.4-cli php8.4-mysql php8.4-pgsql php8.4-mbstring php8.4-xml php8.4-curl php8.4-zip unzip git redis-server supervisor`
3. Install Composer.
4. Create the application directory, clone the code, and copy `.env.example` to `.env`.
5. Set production env vars, then run:
   `composer install --no-dev --optimize-autoloader`
   `php artisan key:generate`
   `php artisan migrate --force`
   `php artisan config:cache`
   `php artisan view:cache`

## Nginx Example

Use a server block that points `root` to `public/` and forwards PHP requests to PHP-FPM.

Key directives:

- `index index.php;`
- `try_files $uri $uri/ /index.php?$query_string;`
- `fastcgi_pass unix:/run/php/php8.4-fpm.sock;`

## Queue Workers

Use Redis or Valkey in Hetzner production.

Worker command:

`php artisan queue:work redis --queue=webhooks,integrations,reminders --sleep=1 --tries=3 --timeout=120 --max-time=3600`

If you enable Horizon:

1. Set `QUEUE_CONNECTION=redis`
2. Start Horizon with `php artisan horizon`
3. Expose `/horizon` behind authentication only

## Supervisor Example

`/etc/supervisor/conf.d/kynex-omnichannel-ai-worker.conf`

```ini
[program:kynex-omnichannel-ai-worker]
command=/usr/bin/php /var/www/kynex-omnichannel-ai/artisan queue:work redis --queue=webhooks,integrations,reminders --sleep=1 --tries=3 --timeout=120 --max-time=3600
directory=/var/www/kynex-omnichannel-ai
autostart=true
autorestart=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/kynex-omnichannel-ai/storage/logs/worker.log
stopwaitsecs=3600
```

## systemd Alternative

`/etc/systemd/system/kynex-omnichannel-ai-worker.service`

```ini
[Unit]
Description=kynex OmniChannel AI Queue Worker
After=network.target

[Service]
User=www-data
Group=www-data
Restart=always
WorkingDirectory=/var/www/kynex-omnichannel-ai
ExecStart=/usr/bin/php artisan queue:work redis --queue=webhooks,integrations,reminders --sleep=1 --tries=3 --timeout=120 --max-time=3600

[Install]
WantedBy=multi-user.target
```

## Scheduler

Cron entry:

`* * * * * cd /var/www/kynex-omnichannel-ai && php artisan schedule:run >> /dev/null 2>&1`

## Zero-Downtime-ish Deploy Flow

1. Pull code to a release directory.
2. Run `composer install --no-dev --optimize-autoloader`.
3. Run `php artisan migrate --force`.
4. Run `php artisan config:cache && php artisan view:cache`.
5. Swap the symlink to the new release.
6. Reload PHP-FPM and Nginx.
7. Restart Supervisor/systemd workers.
8. Verify `/settings/diagnostics`.

## Smoke Commands

After each deploy, run:

1. `php artisan ops:smoke --url=https://your-domain.com/api/health`
2. `php artisan queue:failed`
3. `php artisan schedule:list`
4. `php artisan route:list --path=api/webhook`
