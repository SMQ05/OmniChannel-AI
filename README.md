# kynex OmniChannel AI

Laravel application for multi-channel customer communication, booking flows, webhook ingestion, async processing, diagnostics, and deployment-ready operations.

## Highlights

- WhatsApp and Messenger webhook ingress
- Durable inbound and outbound message tracking
- Database or Redis queue support
- Diagnostics dashboard and `/api/health`
- Render and Hetzner deployment docs
- GitHub Actions CI and live smoke workflow

## Local Setup

```bash
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate
npm install
npm run build
php artisan serve
```

`php artisan serve` is for local development only. Production Docker/Coolify deploys now run PHP-FPM behind Nginx with separate worker and scheduler processes.

## Tests

```bash
php artisan test
```

## Deployment

- Render: [docs/deployment/render.md](docs/deployment/render.md)
- Hetzner: [docs/deployment/hetzner.md](docs/deployment/hetzner.md)
- Coolify: [docs/deployment/coolify.md](docs/deployment/coolify.md)
