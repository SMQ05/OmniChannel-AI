# Phase 0 Ownership Matrix

## Purpose

Phase 0 freezes ownership boundaries before UI and RBAC changes begin.

This phase is intentionally inert at runtime:

- no menu changes
- no route authorization changes
- no new Gate or policy behavior
- no tenant-facing capability changes

It exists to make later implementation phases consistent with the current repo instead of drifting into greenfield abstractions.

## Locked Product Decisions

- Business side owns day-to-day operations, content, staff, providers, services, hours, reminders, conversations, billing visibility, and channel approval/status.
- SaaS admin side owns tenant lifecycle, plans, quotas, provider secrets, model routing, global guardrails, monitoring, impersonation, support, audit policy, and billing truth.
- Billing provider choice remains open. The product needs monthly subscription support plus one-time setup fee support, so billing terminology in this repo should remain provider-agnostic.
- Raw provider secrets must never appear in business UI.
- Platform model routing and global guardrails must never move to the business side.
- Support admins may inspect masked metadata, health, rotation timestamps, and diagnostics for credentials or LLM keys, but never raw secret values.
- SMS is not committed as a first-class supported channel in the current implementation scope.
- Full branch/location matrix is deferred.

## Current Architectural Weakness

**Current architectural weakness:** the shared brain is not visually or structurally centralized enough.

The implementation is strong, but the architecture still feels spread across multiple Laravel services instead of clearly presenting one unified shared conversation/business core across voice, WhatsApp, Messenger, and future SMS.

Today, the shared business logic is distributed across:

- `app/Jobs/ProcessIncomingMessage.php`
- `app/Ai/Agents/AppointmentAgent.php`
- `app/Services/AppointmentOrchestrator.php`
- `app/Services/Messaging/InboundMessageNormalizer.php`
- `app/Services/Messaging/OutboundMessageService.php`
- `app/Services/Voice/VoiceToolService.php`
- `app/Services/Voice/VoiceSessionService.php`
- `app/Services/Voice/VoicePromptBuilder.php`

Phase 0 does not refactor this. Later phases should gradually make the shared brain easier to see and reason about without pretending it is already centralized today.

## Current Repo Ownership Map

### Admin-only surfaces

- `routes/admin.php`
- `resources/views/admin/dashboard.blade.php`
- `resources/views/admin/businesses/index.blade.php`
- `resources/views/admin/plans/index.blade.php`
- `resources/views/admin/voice/index.blade.php`
- `resources/views/admin/llm-keys/index.blade.php`
- Horizon entry from admin when Redis/Horizon is enabled

These remain platform-owned because they control tenant lifecycle, plans, platform credentials, platform voice routing governance, support, and impersonation.

### Business-owned surfaces

- `resources/views/dashboard/index.blade.php`
- `resources/views/appointments/*`
- `resources/views/conversations/*`
- `resources/views/providers/*`
- `resources/views/patients/*`
- `resources/views/settings/integrations.blade.php`
- `resources/views/settings/diagnostics.blade.php`
- `resources/views/profile/edit.blade.php`

These are day-to-day operational surfaces and should remain on the tenant side.

### Surfaces that stay business-side but must shift from admin-managed behavior

- `resources/views/settings/ai.blade.php`
  - business-owned content only
  - remove business access to platform model/provider routing
- `resources/views/settings/reminders.blade.php`
  - business-owned wording and timing
- `resources/views/settings/channels.blade.php`
  - business-owned approval/status flow only
  - no raw secret editing
- `resources/views/settings/voice.blade.php`
  - business-owned voice preferences/status only
  - no raw provider tests or platform credentials
- `resources/views/settings/subscription.blade.php`
  - rename toward Billing / Usage visibility

### Shared read-only support inspection surfaces

These can be safely inspected by platform support via impersonation or future support-specific policy, but business ownership remains with the tenant:

- billing visibility page
- diagnostics page
- conversation detail page
- channel connection status
- voice status/history

## Data Ownership Map

### Platform truth

The following remain platform-owned:

- `plans`
- `business_subscriptions`
- `platform_llm_keys`
- provider credential material currently embedded in `businesses.channel_config`
- platform-level model routing and provider defaults

### Business truth

The following remain tenant-owned:

- `providers`
- `provider_blocked_dates`
- `patients`
- `appointments`
- `conversation_logs`
- business-facing content in `businesses.ai_config`
- reminder wording/timing in `businesses.reminder_settings`

### Transitional note

The current repo still stores mixed concerns in a few JSON columns:

- `businesses.channel_config`
- `businesses.ai_config`
- `businesses.reminder_settings`

That is acceptable for the current codebase, but later phases should gradually separate:

- business-owned content/preferences
- platform-owned integration state
- connection metadata

## Page Ownership Table

### Stays admin-only

- `admin.dashboard`
- `admin.businesses.*`
- `admin.plans.*`
- `admin.voice.*`
- `admin.llm-keys.*`
- admin Horizon access

### Stays business-owned

- `dashboard`
- `appointments.*`
- `conversations.*`
- `providers.*`
- `patients.*`
- `settings.integrations`
- `settings.diagnostics`
- `profile.edit`

### Moves to business-owned behavior later

- `settings.ai`
- `settings.reminders`
- `settings.channels`
- `settings.voice`
- `settings.subscription`

## Permission Key Vocabulary

### Platform permissions

- `platform.dashboard.view`
- `platform.businesses.view`
- `platform.businesses.manage`
- `platform.businesses.impersonate`
- `platform.onboarding.manage`
- `platform.plans.view`
- `platform.plans.manage`
- `platform.billing_truth.view`
- `platform.billing_truth.manage`
- `platform.usage_controls.view`
- `platform.usage_controls.manage`
- `platform.llm_keys.view_metadata`
- `platform.llm_keys.manage`
- `platform.voice_admin.view`
- `platform.voice_admin.manage`
- `platform.monitoring.view`
- `platform.compliance.view`
- `platform.compliance.approve`
- `platform.compliance.execute`
- `platform.retention.manage`
- `platform.support.actions`
- `platform.audit.view`
- `platform.credentials.view_metadata`
- `platform.credentials.manage`
- `platform.ai_guardrails.manage`
- `platform.horizon.view`

### Business permissions

- `business.dashboard.view`
- `business.conversations.view`
- `business.conversations.manage`
- `business.conversations.takeover`
- `business.appointments.view`
- `business.appointments.manage`
- `business.providers.view`
- `business.providers.manage`
- `business.patients.view`
- `business.patients.manage`
- `business.services.view`
- `business.services.manage`
- `business.ai_content.view`
- `business.ai_content.manage`
- `business.reminders.view`
- `business.reminders.manage`
- `business.integrations.view`
- `business.integrations.manage`
- `business.channels.view`
- `business.channels.approve`
- `business.voice_preferences.view`
- `business.voice_preferences.manage`
- `business.diagnostics.view`
- `business.diagnostics.run`
- `business.usage.view`
- `business.billing.view`
- `business.billing.portal`
- `business.team.view`
- `business.team.manage`
- `business.roles.manage`
- `business.security.view`
- `business.security.manage`
- `business.data_export.request`
- `business.data_controls.view`
- `business.data_delete.request`

## Role Intent Summary

### `super_admin`

- full platform-wide authority
- can manage platform truth
- can impersonate with audit trail

### `support_admin`

- can inspect platform and tenant state for support
- can impersonate with audit trail
- can see masked credential/LLM-key metadata only
- cannot manage plans, raw secrets, raw LLM key values, or platform guardrails

### `business_owner`

- full tenant administration
- manages team, business content, approvals, and billing visibility
- cannot access platform truth

### `manager`

- runs day-to-day operations and business content
- cannot approve channels
- cannot manage billing portal access
- cannot mutate credential-linked integrations

### `receptionist`

- appointments and conversation operations
- no billing, team, or channel approval authority

### `staff`

- read-heavy operational access only
- cannot mutate business configuration

## Non-goals For Phase 0

- no runtime permission enforcement
- no policy layer
- no Gate layer
- no middleware changes
- no navigation changes
- no billing provider implementation
- no channel onboarding flow redesign
- no shared-brain refactor

## Phase Dependency Note

- Phase 1 uses this document to reshape layouts, menus, and page framing.
- Phase 2 uses this document to enforce authorization and team-management behavior.
