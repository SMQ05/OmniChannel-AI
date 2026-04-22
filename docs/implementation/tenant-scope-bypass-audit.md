# Tenant Scope Bypass Audit

This audit covers every runtime use of `withoutGlobalScope(TenantScope::class)` as of April 22, 2026.

## Runtime paths

### `app/Jobs/ProcessIncomingMessage.php`

- `Patient::withoutGlobalScope(...)->firstOrCreate(...)`
  - Reapplied guard: `business_id`, `platform_user_id`, `platform`
  - Risk note: safe when `business_id` comes from the already-resolved webhook business.
- `ConversationLog::withoutGlobalScope(...)->where(...)`
  - Reapplied guard: `business_id`, `patient_id`, `session_ended_at IS NULL`
  - Risk note: safe because `patient_id` is derived from the business-scoped patient lookup above.
- `Provider::withoutGlobalScope(...)->where(...)`
  - Reapplied guard: `business_id`, `is_active`
  - Risk note: safe for read access; nested appointment eager loads are bounded by the already business-scoped provider set.
- Nested `appointments` eager load on providers
  - Reapplied guard: provider relation plus future-window and status filters
  - Manual review note: still worth re-checking if provider relation definitions change later.

### `app/Services/AppointmentOrchestrator.php`

- Booking conflict checks on `Appointment`
  - Reapplied guard: provider comes from `resolveProvider()` which enforces `business_id` and `is_active`
  - Risk note: safe because a foreign provider never resolves.
- Cancel and reschedule queries on `Appointment`
  - Reapplied guard: `business_id`, `patient_id`, status and time window filters
  - Risk note: safe because the patient object is already business-owned.
- `resolveProvider()`
  - Reapplied guard: `id`, `business_id`, `is_active`
  - Risk note: safe; this is the main cross-tenant gate for orchestrated booking.

### `app/Services/Voice/VoiceToolService.php`

- Provider availability query
  - Reapplied guard: `business_id`, `is_active`
  - Nested appointment eager load is bounded by the provider relation plus time/status filters.
- Booking conflict checks on `Appointment`
  - Reapplied guard: `business_id`, `provider_id`
- `lookupPatient()`
  - Reapplied guard: `business_id`, plus either `id` or normalized phone/platform identifier
- Upcoming appointment lookup
  - Reapplied guard: `business_id`, `patient_id`
- WhatsApp follow-up patient match
  - Reapplied guard: `business_id`, `platform = whatsapp`, normalized phone/platform identifier
- `resolveProvider()`
  - Reapplied guard: `business_id`, `id`
- `resolvePatient()`
  - Reapplied guard: `business_id`, plus either `id` or normalized phone/platform identifier
- `findAppointment()`
  - Reapplied guard: `business_id`, status filters, optional `appointment_id`, `patient_id`, `provider_id`
  - Risk note: safe for current inputs; keep explicit tests because this method is reused by cancel and reschedule tools.

### `app/Services/Voice/VoiceSessionService.php`

- Caller phone to patient resolution
  - Reapplied guard: `business_id`, normalized phone/platform identifier
  - Risk note: safe; when no match exists, the created patient is explicitly written with the current business id.

## Non-runtime bypasses

### `tests/Feature/HumanHandoffBroadcastTest.php`

- Uses bypassed model creation only for test fixture setup.
- No production path is affected.

## Negative-test coverage added in this pass

- `tests/Feature/ProcessIncomingMessageTest.php`
  - same sender identifier in another tenant does not get reused
- `tests/Feature/AppointmentOrchestratorTenantIsolationTest.php`
  - cross-tenant provider id is rejected during booking orchestration
- `tests/Feature/VoiceInternalApiTest.php`
  - cross-tenant patient lookup returns not found
  - cross-tenant appointment cancel attempt is denied
  - cross-tenant provider id cannot be booked
  - voice session caller-phone matching does not reuse another tenant's patient

## Residual manual-review items

- Any future `withoutGlobalScope(TenantScope::class)` addition should require an audit note update and a negative test.
- The nested provider appointment eager loads in messaging and voice are safe today because the parent provider query is business-scoped first, but that coupling should stay explicit in future refactors.
