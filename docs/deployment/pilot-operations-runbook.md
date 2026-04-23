# Pilot Operations Runbook

This runbook defines the minimum operating standard for a controlled pilot release. It is not a GA runbook yet.

## Release Steps

1. Confirm CI passed on the release branch.
2. Confirm `php artisan test` passed against the release candidate.
3. Confirm `npm run build` passed for the web app and `services/voice-gateway`.
4. Deploy app, worker, scheduler, and web services together.
5. Run database migrations once:

```bash
php artisan migrate --force
```

6. Run strict smoke checks after the new containers are healthy:

```bash
php artisan ops:smoke --strict-runtime --max-failed-jobs=0
```

7. Verify:
   - `GET /api/health`
   - `/settings/diagnostics`
   - worker heartbeat is fresh
   - scheduler reminders and billing heartbeats are fresh
   - no failed jobs
   - billing cycle command is registered
8. Send one live inbound message through an enabled tenant channel and confirm webhook ingest, async processing, and outbound response.

## Rollback

Use rollback if smoke checks fail, migrations are incompatible with runtime behavior, or new queue failures spike.

1. Stop further deploy automation.
2. Re-deploy the prior application image or previous Git SHA for:
   - web
   - app
   - worker
   - scheduler
3. If the new release included reversible migrations that caused the issue, restore the database from the last pre-release backup instead of attempting ad hoc manual edits.
4. Re-run:

```bash
php artisan ops:smoke --strict-runtime --max-failed-jobs=0
```

5. Confirm queue depth is stabilizing and failed jobs stop increasing.

Pilot note: rollback is still image-and-database restore based. There is not yet an in-repo blue/green or canary flow.

## Backup

Before each pilot release, capture:

- full database backup
- encrypted copy of production `.env` or secret-manager export
- deployment manifests and release SHA

Minimum backup cadence:

- nightly database backup retained for at least 7 days
- pre-release backup before every deploy

## Restore Verification

At least once before broad pilot expansion:

1. Restore the latest backup into a staging or isolated restore target.
2. Boot the application against the restored database.
3. Run:

```bash
php artisan ops:smoke
```

4. Verify one tenant can load diagnostics and one billing business still shows its latest invoice and subscription lifecycle state.

This repo now has restore guidance, but restore drills still need to be performed by operations.

## Monitoring And Alerts

Minimum alert coverage for pilot:

- `/api/health` non-200
- worker heartbeat older than 10 minutes
- scheduler reminders heartbeat older than 10 minutes
- scheduler billing heartbeat older than 10 minutes
- failed jobs count above 0 for more than 5 minutes
- database queue depth rising continuously on `webhooks`, `integrations`, `reminders`, or `billing`
- billing cycle drift where due subscriptions are not invoiced within one scheduler interval

Preferred destinations:

- on-call email for pilot
- PagerDuty or equivalent paging target before GA
- centralized logs for `stderr`

## Recovery Priorities

1. Restore inbound messaging and webhook ingestion.
2. Restore queue processing.
3. Restore billing cycle generation and payment marking.
4. Restore diagnostics and admin visibility.
5. Keep voice in rollout/beta mode until live recovery and failover behavior are proven.
