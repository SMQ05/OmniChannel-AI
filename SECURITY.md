# Security Guidance

## Immediate Actions

Rotate every secret that has ever been committed or packaged with this application:

- `APP_KEY`
- Meta WhatsApp and Messenger access tokens
- Meta app secrets and webhook verify tokens
- Database usernames/passwords
- Google OAuth client secrets and refresh tokens
- Redis credentials

## Repository Hygiene

- Keep `.env` out of version control.
- Commit only `.env.example` with placeholders.
- Do not store production exports, database dumps, or provider credential JSON files in the repo.
- Do not keep live logs with secrets or access tokens in deployable artifacts.

## Deployment Rules

- Set `APP_DEBUG=false` in every non-local environment.
- Use a distinct `APP_KEY` per environment.
- Use HTTPS-only `APP_URL`.
- Configure queue tables before enabling `QUEUE_CONNECTION=database`.
- Configure Redis only in environments where Redis is actually reachable.

## Webhooks

- Keep Meta app secrets and verify tokens in environment variables or encrypted tenant settings.
- Reject invalid `X-Hub-Signature-256` requests.
- Store inbound payloads for replay, but never expose them publicly.

## Google

- Store Google OAuth refresh tokens in the database only if the database is access-controlled and encrypted at rest.
- Restrict OAuth redirect URIs to the exact deployed domain.

## Incident Response

1. Rotate secrets.
2. Invalidate leaked OAuth refresh tokens.
3. Clear failed jobs that contain stale secrets only after secure review.
4. Audit inbound and outbound message logs for unauthorized activity.
