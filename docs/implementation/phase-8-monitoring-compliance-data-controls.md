# Phase 8: Monitoring, Compliance, and Data Controls

## Scope

Phase 8 adds:

- admin monitoring for cross-tenant derived health snapshots
- tenant-local data controls and diagnostics context
- approval-driven governance workflows for export and deletion
- retention policy guardrails with bounded overrides and idempotent run logging

## Architectural Guardrail

**Current architectural weakness:** the shared brain is not visually or structurally centralized enough.

Phase 8 improves monitoring, compliance, and data controls, but it does not unify messaging, voice, webhook, billing, and runtime truth into one core.

Runtime and billing truth remain distributed across existing domain services and tables.
Monitoring snapshots are explicitly derived aggregates only.

## Ownership Split

- Tenant data controls page (`settings.data-controls`) is business-local and request-oriented.
- Admin monitoring/compliance pages (`admin.monitoring.*`, `admin.compliance.*`) are platform-wide control-plane surfaces.

## Governance Workflow Rules

- Export and deletion requests are approval-driven and async.
- All critical transitions are audit logged.
- Export artifacts are private, expiring, and access-scoped by policy.
- Deletion remains conservative:
  - billing, audit, and legal-retention domains are preserved
  - anonymization is default
  - hard-delete is policy-dependent and still restricted to transient data domains

## Retention Rules

- Global defaults are defined in `config/data_controls.php`.
- Business overrides are bounded by min/max guardrails.
- Enforcement is idempotent via `run_key` and logged in `data_retention_runs`.
