# Legacy Secret Cleanup Note

This hardening pass removes live secrets from legacy plaintext JSON config paths.

## Encrypted stores now used for live secrets

- `businesses.integration_secrets`
  - `google_credentials.client_secret`
  - `google_calendar.token.access_token`
  - `google_calendar.token.refresh_token`
  - `google_calendar.token.expires_at`
  - `google_sheets.token.access_token`
  - `google_sheets.token.refresh_token`
  - `google_sheets.token.expires_at`
- `messaging_channel_connections.credentials`
  - WhatsApp Meta: `access_token`, `verify_token`, `app_secret`
  - WhatsApp Twilio: `twilio_account_sid`, `twilio_auth_token`
  - Messenger: `access_token`, `verify_token`, `app_secret`

## Legacy fields that are now safe / non-secret

- `businesses.channel_config.whatsapp`
  - `enabled`
  - `provider`
  - `phone_number_id`
  - `twilio_from_number`
- `businesses.channel_config.messenger`
  - `enabled`
  - `provider`
  - `page_id`
- `businesses.channel_config.voice`
  - preference and routing fields only
  - current safe keys include `enabled`, `greeting_message`, `handoff_message`, `notes`, `transport_provider`, `stt_provider`, `llm_provider`, `tts_provider`
- `businesses.integration_config.google_credentials`
  - `client_id`
- `businesses.integration_config.google_calendar`
  - `enabled`
  - `calendar_id`
- `businesses.integration_config.google_sheets`
  - `enabled`
  - `spreadsheet_id`
  - `sheet_name`

## Migration compatibility

- A narrow read fallback still exists for older rows during rollout.
- After the migration runs, the fallback should be unused because plaintext secret keys are scrubbed from the legacy JSON blobs.
- New writes must go only to encrypted stores for secrets and to legacy JSON only for non-secret preferences.
