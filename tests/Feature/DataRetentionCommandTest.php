<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Business;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class DataRetentionCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_retention_command_applies_policies_and_is_idempotent_by_run_key(): void
    {
        $business = Business::query()->create([
            'name' => 'Retention Clinic',
            'business_type' => 'clinic',
            'slug' => 'retention-clinic',
            'timezone' => 'UTC',
            'locale' => 'en',
            'channel_config' => [],
            'integration_config' => [],
            'reminder_settings' => [],
            'ai_config' => [],
            'is_active' => true,
            'plan' => 'trial',
        ]);

        $admin = User::query()->create([
            'business_id' => null,
            'name' => 'Admin',
            'email' => 'admin-retention@example.com',
            'password' => bcrypt('password'),
            'role' => 'super_admin',
        ]);

        $patientId = DB::table('patients')->insertGetId([
            'business_id' => $business->id,
            'name' => 'Retention Patient',
            'phone' => '555-2000',
            'email' => 'retention@example.com',
            'platform_user_id' => 'retention-user',
            'platform' => 'whatsapp',
            'created_at' => now()->subYears(2),
            'updated_at' => now()->subYears(2),
        ]);

        DB::table('conversation_logs')->insert([
            'business_id' => $business->id,
            'patient_id' => $patientId,
            'appointment_id' => null,
            'channel' => 'whatsapp',
            'messages' => json_encode([['role' => 'user', 'content' => 'old']]),
            'ai_model_used' => null,
            'human_mode' => false,
            'session_started_at' => now()->subDays(500),
            'session_ended_at' => null,
            'created_at' => now()->subDays(500),
            'updated_at' => now()->subDays(500),
        ]);

        DB::table('inbound_webhooks')->insert([
            'business_id' => $business->id,
            'channel' => 'whatsapp',
            'business_slug' => $business->slug,
            'correlation_id' => (string) Str::uuid(),
            'idempotency_key' => 'retention-inbound-' . Str::uuid()->toString(),
            'external_message_id' => null,
            'sender_platform_id' => null,
            'sender_name' => null,
            'message_text' => 'old webhook',
            'message_type' => null,
            'payload' => json_encode(['sample' => true]),
            'normalized_payload' => null,
            'signature_valid' => false,
            'status' => 'received',
            'queue_connection' => null,
            'queue_name' => null,
            'attempt_count' => 0,
            'last_error' => null,
            'received_at' => now()->subDays(200),
            'dispatched_at' => null,
            'processed_at' => null,
            'last_replayed_at' => null,
            'created_at' => now()->subDays(200),
            'updated_at' => now()->subDays(200),
        ]);

        DB::table('outbound_message_attempts')->insert([
            'business_id' => $business->id,
            'conversation_log_id' => null,
            'inbound_webhook_id' => null,
            'channel' => 'whatsapp',
            'recipient_platform_id' => 'retention-user',
            'idempotency_key' => 'retention-outbound-' . Str::uuid()->toString(),
            'correlation_id' => (string) Str::uuid(),
            'status' => 'failed',
            'message_text' => 'old outbound',
            'provider_message_id' => null,
            'http_status' => null,
            'failure_class' => null,
            'response_body' => null,
            'last_error' => null,
            'attempts' => 1,
            'meta' => null,
            'last_attempted_at' => now()->subDays(200),
            'sent_at' => null,
            'created_at' => now()->subDays(200),
            'updated_at' => now()->subDays(200),
        ]);

        $voiceSessionId = DB::table('voice_sessions')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'business_id' => $business->id,
            'voice_channel_id' => null,
            'patient_id' => null,
            'legacy_call_log_id' => null,
            'provider' => 'telnyx',
            'provider_call_id' => null,
            'transport_stream_id' => null,
            'openai_session_id' => null,
            'deepgram_session_id' => null,
            'direction' => 'inbound',
            'status' => 'ended',
            'from_number' => null,
            'to_number' => null,
            'initiated_at' => now()->subDays(300),
            'connected_at' => null,
            'ended_at' => now()->subDays(300),
            'last_activity_at' => now()->subDays(300),
            'transfer_requested_at' => null,
            'callback_requested_at' => null,
            'fallback_mode' => null,
            'handoff_reason' => null,
            'metrics' => null,
            'context' => null,
            'meta' => null,
            'created_at' => now()->subDays(300),
            'updated_at' => now()->subDays(300),
        ]);

        DB::table('voice_events')->insert([
            'business_id' => $business->id,
            'voice_session_id' => $voiceSessionId,
            'event_type' => 'call.ended',
            'source' => 'voice',
            'idempotency_key' => 'retention-voice-' . Str::uuid()->toString(),
            'correlation_id' => (string) Str::uuid(),
            'severity' => 'info',
            'payload' => json_encode(['sample' => true]),
            'occurred_at' => now()->subDays(250),
            'created_at' => now()->subDays(250),
            'updated_at' => now()->subDays(250),
        ]);

        $runKey = (string) Str::uuid();

        $this->artisan('data-controls:enforce-retention', [
            '--run-key' => $runKey,
            '--actor-id' => $admin->id,
        ])->assertExitCode(0);

        $this->assertDatabaseCount('conversation_logs', 0);
        $this->assertDatabaseCount('inbound_webhooks', 0);
        $this->assertDatabaseCount('outbound_message_attempts', 0);
        $this->assertDatabaseCount('voice_events', 0);
        $this->assertDatabaseHas('data_retention_runs', [
            'run_key' => $runKey,
            'status' => 'completed',
            'mode' => 'apply',
        ]);

        $this->artisan('data-controls:enforce-retention', [
            '--run-key' => $runKey,
            '--actor-id' => $admin->id,
        ])->assertExitCode(0);

        $this->assertDatabaseCount('data_retention_runs', 1);
    }
}
