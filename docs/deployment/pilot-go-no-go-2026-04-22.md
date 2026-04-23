# Pilot Go/No-Go Report - 2026-04-22

## Verdict

Go for a controlled pilot after rollout validation.

Do not label this release GA-ready.

## Production-Ready Now

- multi-tenant Laravel business app and admin control plane
- messaging intake and async processing pipeline
- service-aware booking flow and reminder scheduling
- RBAC, team invites, admin operations ownership split
- billing document, ledger, and lifecycle core
- diagnostics, health endpoint, and monitoring snapshots
- encrypted live secret storage with legacy plaintext config scrubbed
- tenant scope bypass paths audited with explicit negative isolation tests

## Deferred Or Beta

- voice remains rollout/beta only
- centralized shared conversation and voice brain is still deferred
- first-class billing provider automation is still incomplete
- automated rollback orchestration and restore drills are not yet complete
- alert routing and paging are only partially codified
- unresolved `/images/map-placeholder.png` warning should be cleaned before GA

## Security And Tenant Risk Summary

- Secret storage hardening is now materially improved. Live credentials no longer belong in legacy plaintext config paths.
- Tenant isolation is still primarily enforced at the application layer. The audited bypasses are covered for current paths, but this is not database-enforced row-level security.
- The remaining legacy secret fallback is intentionally narrow and tracked for removal in GitHub issue `#1`.

## Billing Readiness Summary

- Lifecycle refresh now reconciles expired state correctly for subscriptions that pass `cancel_at_period_end`.
- Manual payment marking is idempotent for the same provider transaction reference.
- Billing cycle generation no longer issues an early duplicate invoice when rerun before the next due time.
- Provider account, document, and transaction references are now treated as provider-global uniqueness constraints.

## Ops Readiness Summary

- Deployment topology is documented for Coolify, Hetzner, and Render.
- Strict smoke checks now support runtime heartbeat and failed-job enforcement.
- Backup, restore, rollback, and minimum alerting guidance are documented.
- Remaining gap: these runbooks still need real operator execution and restore-drill evidence.

## Remaining Blockers Before GA

1. Complete rollout validation on a real migrated tenant in the target environment.
2. Remove the temporary legacy secret fallback after rollout verification.
3. Wire real alert delivery and paging.
4. Execute and record a restore drill.
5. Keep voice in beta until production incident recovery is proven.
