# Kynex Realtime Voice Agent

## 1. Architecture Overview

Kynex keeps Laravel 13 as the multi-tenant control plane and source of truth.

- `Laravel app`
  - tenant/business resolution
  - booking and calendar logic
  - internal voice tool APIs
  - session/event persistence
  - usage, plan enforcement, admin controls
- `Node.js voice-gateway`
  - Telnyx bidirectional media websocket endpoint
  - OpenAI Realtime websocket client per call
  - Deepgram Flux shadow transcription
  - tool bridge to Laravel
  - low-latency session manager, metrics, health endpoints
- `Redis`
  - gateway coordination, future fan-out, fast ephemeral state
- `PostgreSQL`
  - durable tenant data, appointments, voice session records, billing events

Primary live stack:

- transport: Telnyx
- live conversation engine: OpenAI Realtime API
- premium model: `gpt-realtime`
- fallback model: `gpt-realtime-mini`
- shadow STT / QA transcript: Deepgram Flux
- backup / branded speech: ElevenLabs

## 2. Folder Structure

```text
app/
  Http/Controllers/Api/Internal/
    VoiceSessionController.php
    VoiceToolController.php
  Http/Middleware/
    VerifyVoiceGatewayRequest.php
  Jobs/
    SummarizeVoiceSessionJob.php
  Models/
    VoiceSession.php
    VoiceTurn.php
    VoiceEvent.php
    VoiceSummary.php
    VoiceUsageEvent.php
  Services/Voice/
    VoicePromptBuilder.php
    VoiceSessionService.php
    VoiceToolCatalog.php
    VoiceToolService.php
    VoiceIdempotencyService.php
    VoiceSummaryService.php

config/
  voice_gateway.php

database/migrations/
  2026_04_18_220000_create_realtime_voice_tables.php

services/voice-gateway/
  Dockerfile
  package.json
  tsconfig.json
  src/
    app.ts
    config.ts
    index.ts
    logger.ts
    metrics.ts
    types.ts
    clients/
      laravel-control-plane-client.ts
      openai-realtime-client.ts
      deepgram-shadow-client.ts
    routes/
      health.ts
      telnyx-media.ts
    session/
      call-session.ts
      session-manager.ts
```

## 3. Database Schema Changes

### `voice_sessions`

- per-call root record
- links `business_id`, `voice_channel_id`, `patient_id`
- stores Telnyx/OpenAI/Deepgram session ids
- stores status, fallback mode, handoff timestamps, metrics, and context

### `voice_turns`

- transcript and assistant text by turn
- interruption flags
- tool name / tool status when a turn triggered deterministic tool work

### `voice_events`

- structured event stream for each call
- idempotency key support for deterministic write tools
- severity, source, payload, correlation id

### `voice_summaries`

- post-call summary
- disposition, action items, booking outcome
- structured summary payload for QA / analytics

### `voice_usage_events`

- durable usage and cost events for voice sessions
- supports billing, QA, and provider cost analysis

## 4. Environment Variables

Laravel / shared:

```env
FEATURE_VOICE_AGENT=true
VOICE_GATEWAY_SHARED_SECRET=...
OPENAI_API_KEY=...
OPENAI_REALTIME_BASE_URL=wss://api.openai.com/v1/realtime
VOICE_GATEWAY_PRIMARY_MODEL=gpt-realtime
VOICE_GATEWAY_FALLBACK_MODEL=gpt-realtime-mini
VOICE_GATEWAY_OPENAI_VOICE=alloy
VOICE_GATEWAY_SILENCE_TIMEOUT_MS=6000
VOICE_GATEWAY_REPROMPT_LIMIT=2
VOICE_GATEWAY_DEEPGRAM_SHADOW=true
VOICE_GATEWAY_FOLLOWUP_WHATSAPP=true
VOICE_GATEWAY_CALLBACK_ENABLED=true
VOICE_GATEWAY_TRANSFER_ENABLED=true
DEEPGRAM_API_KEY=...
TELNYX_API_KEY=...
TELNYX_CONNECTION_ID=...
ELEVENLABS_API_KEY=...
ELEVENLABS_VOICE_ID=...
SENTRY_DSN=...
```

Voice gateway container:

```env
PORT=3001
HOST=0.0.0.0
LARAVEL_INTERNAL_BASE_URL=http://nginx
LARAVEL_INTERNAL_SECRET=${VOICE_GATEWAY_SHARED_SECRET}
REDIS_URL=redis://redis:6379/0
OPENAI_API_KEY=...
OPENAI_REALTIME_BASE_URL=wss://api.openai.com/v1/realtime
VOICE_GATEWAY_PRIMARY_MODEL=gpt-realtime
VOICE_GATEWAY_FALLBACK_MODEL=gpt-realtime-mini
VOICE_GATEWAY_OPENAI_VOICE=alloy
DEEPGRAM_API_KEY=...
SENTRY_DSN=...
```

## 5. API Contracts

Internal authenticated Laravel endpoints:

```text
GET    /api/internal/voice/tools
POST   /api/internal/voice/tools/{tool}
POST   /api/internal/voice/sessions/start
POST   /api/internal/voice/sessions/{voiceSession}/events
POST   /api/internal/voice/sessions/{voiceSession}/turns
POST   /api/internal/voice/sessions/{voiceSession}/usage
POST   /api/internal/voice/sessions/{voiceSession}/summary
POST   /api/internal/voice/sessions/{voiceSession}/complete
```

Required auth header:

```text
X-Voice-Gateway-Secret: <shared secret>
```

Write tools require:

```text
X-Idempotency-Key: <stable unique key>
```

Tools exposed to the realtime agent:

- `check_availability`
- `book_appointment`
- `reschedule_appointment`
- `cancel_appointment`
- `lookup_patient`
- `get_business_faq`
- `create_callback_request`
- `notify_staff`
- `send_followup_whatsapp`

## 6. Call-Flow Sequence Diagrams

### Inbound call happy path

```text
Caller
  -> Telnyx PSTN/SIP
  -> voice-gateway websocket
  -> Laravel startSession
  -> OpenAI Realtime session.update + tools
  -> audio turns streamed in both directions
  -> tool bridge to Laravel when booking data is confirmed
  -> Laravel returns deterministic tool result
  -> OpenAI responds using confirmed tool output
  -> voice-gateway stores events/turns/usage
  -> Laravel completes session and queues post-call summary
```

### Tool call

```text
OpenAI Realtime function call
  -> voice-gateway executeTool
  -> Laravel internal tool endpoint
  -> booking / patient / FAQ logic
  -> Laravel JSON result
  -> voice-gateway function_call_output
  -> OpenAI final spoken response
```

### Mid-call failure

```text
OpenAI realtime failure
  -> voice-gateway fast fallback path
  -> record fallback event in Laravel
  -> create callback request and/or notify staff
  -> optionally send WhatsApp follow-up
  -> finalize session with fallback mode
```

## 7. Implementation Steps In Order

1. Deploy Redis and the Node voice-gateway container beside Laravel.
2. Run Laravel migrations for the realtime voice tables.
3. Configure Telnyx bidirectional streaming to the gateway websocket route.
4. Set `VOICE_GATEWAY_SHARED_SECRET` on both Laravel and voice-gateway.
5. Configure OpenAI Realtime API keys and premium/fallback model env.
6. Enable Deepgram shadow transcription.
7. Point voice-gateway at `http://nginx` for Laravel internal API access.
8. Enable voice for selected businesses/plans.
9. Start canary rollout on one clinic / one channel.
10. Monitor metrics, fallback rate, and booking success before expanding.

## 8. Exact Code Skeletons / Files Created

Core Laravel control-plane files:

- `config/voice_gateway.php`
- `app/Http/Middleware/VerifyVoiceGatewayRequest.php`
- `app/Http/Controllers/Api/Internal/VoiceSessionController.php`
- `app/Http/Controllers/Api/Internal/VoiceToolController.php`
- `app/Services/Voice/VoiceSessionService.php`
- `app/Services/Voice/VoiceToolService.php`
- `app/Services/Voice/VoicePromptBuilder.php`
- `app/Services/Voice/VoiceToolCatalog.php`
- `app/Services/Voice/VoiceIdempotencyService.php`
- `app/Services/Voice/VoiceSummaryService.php`
- `app/Jobs/SummarizeVoiceSessionJob.php`
- `app/Models/VoiceSession.php`
- `app/Models/VoiceTurn.php`
- `app/Models/VoiceEvent.php`
- `app/Models/VoiceSummary.php`
- `app/Models/VoiceUsageEvent.php`

Voice gateway files:

- `services/voice-gateway/src/clients/openai-realtime-client.ts`
- `services/voice-gateway/src/clients/laravel-control-plane-client.ts`
- `services/voice-gateway/src/clients/deepgram-shadow-client.ts`
- `services/voice-gateway/src/session/call-session.ts`
- `services/voice-gateway/src/session/session-manager.ts`
- `services/voice-gateway/src/routes/telnyx-media.ts`
- `services/voice-gateway/src/routes/health.ts`
- `services/voice-gateway/src/metrics.ts`

## 9. Testing Strategy

Laravel:

- feature tests for internal voice auth and session lifecycle
- feature tests for write-tool idempotency
- feature tests for booking / cancel / reschedule via voice tool endpoints
- feature tests for callback request and WhatsApp follow-up behavior

Voice gateway:

- unit tests for Telnyx message parsing
- unit tests for OpenAI Realtime event handling
- integration tests against mocked Laravel tool endpoints
- soak tests with concurrent sessions and forced reconnects

Operational:

- canary clinic rollout
- test interruption/barge-in
- test OpenAI fallback path
- test Telnyx disconnect cleanup
- verify post-call summary generation

## 10. Rollout Plan

1. keep `FEATURE_VOICE_AGENT=false` globally
2. deploy Redis + voice-gateway alongside Laravel
3. smoke test health and internal API auth
4. enable one internal test business with `admin_override=true`
5. validate booking, cancel, callback, and transfer flows
6. monitor first-response latency, fallback rate, and error rate
7. enable one paid clinic on the `Pro` plan
8. expand to `Growth` and `Launch` once metrics are stable

## 11. Known Risks And Mitigations

- Realtime model or event contract drift
  - mitigate with env-driven model selection and isolated gateway client
- Telnyx codec / media framing mismatches
  - mitigate with `g711_ulaw` alignment and a dedicated media adapter layer
- Tool hallucination risk
  - mitigate by routing all writes through Laravel and returning explicit tool result JSON
- Cross-tenant leakage
  - mitigate by deriving tenant context from the started `voice_session`
- Provider outages
  - mitigate with callback creation, staff notification, WhatsApp follow-up, and clean partial-session persistence
