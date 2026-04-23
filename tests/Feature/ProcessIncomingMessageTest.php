<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Ai\Agents\AppointmentAgent;
use App\Jobs\ProcessIncomingMessage;
use App\Models\BusinessService;
use App\Models\Business;
use App\Models\BusinessMessagingChannel;
use App\Models\BusinessSubscription;
use App\Models\InboundWebhook;
use App\Models\MessagingChannelConnection;
use App\Models\Patient;
use App\Models\Plan;
use App\Models\Provider;
use App\Services\AppointmentOrchestrator;
use App\Services\SlotCalculatorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProcessIncomingMessageTest extends TestCase
{
    use RefreshDatabase;

    public function test_inbound_job_processes_and_sends_reply(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.reply.1']]], 200),
        ]);

        $business = Business::query()->create([
            'name' => 'Clinic',
            'business_type' => 'clinic',
            'slug' => 'clinic',
            'timezone' => 'UTC',
            'locale' => 'en',
            'channel_config' => [
                'whatsapp' => [
                    'enabled' => true,
                    'phone_number_id' => '123456',
                    'provider' => 'meta_cloud',
                ],
            ],
            'integration_config' => [],
            'reminder_settings' => [],
            'ai_config' => ['llm_provider' => 'claude', 'business_phone' => '15550001111'],
            'is_active' => true,
            'plan' => 'trial',
        ]);

        $this->seedWhatsappConnection($business, true);

        Provider::query()->create([
            'business_id' => $business->id,
            'name' => 'Dr Test',
            'working_hours' => ['monday' => ['active' => true, 'start' => '09:00', 'end' => '17:00']],
            'slot_duration_minutes' => 30,
            'is_active' => true,
        ]);

        $webhook = InboundWebhook::query()->create([
            'business_id' => $business->id,
            'channel' => 'whatsapp',
            'business_slug' => $business->slug,
            'correlation_id' => (string) \Illuminate\Support\Str::uuid(),
            'idempotency_key' => sha1('test'),
            'external_message_id' => 'wamid.test.1',
            'sender_platform_id' => '15551234567',
            'sender_name' => 'Patient',
            'message_text' => 'hello',
            'message_type' => 'text',
            'payload' => ['example' => true],
            'normalized_payload' => ['text' => 'hello'],
            'signature_valid' => true,
            'status' => 'received',
            'received_at' => now(),
        ]);

        $this->app->instance(SlotCalculatorService::class, new class extends SlotCalculatorService {
            public function compute(\Illuminate\Support\Collection $providers, string $timezone, int $days = 7, ?BusinessService $service = null): array
            {
                return [];
            }
        });

        $this->app->instance(AppointmentAgent::class, new class extends AppointmentAgent {
            public function handle(\App\Models\Business $business, \App\Models\ConversationLog $conversationLog, array $availableSlots, string $inboundText): array
            {
                return [
                    'intent' => 'faq',
                    'provider_id' => null,
                    'date' => null,
                    'time' => null,
                    'service_type' => null,
                    'reply_text' => 'Thanks for your message.',
                    'needs_human' => false,
                ];
            }
        });

        $this->app->instance(AppointmentOrchestrator::class, new class extends AppointmentOrchestrator {
            public function execute(\App\Models\Business $business, \App\Models\Patient $patient, \App\Models\ConversationLog $conversationLog, array $agentResponse, string $channel, array $context = []): string
            {
                return 'Thanks for your message.';
            }
        });

        $job = new ProcessIncomingMessage($webhook->id);
        $this->app->call([$job, 'handle']);

        $this->assertDatabaseHas('inbound_webhooks', [
            'id' => $webhook->id,
            'status' => 'processed',
        ]);
        $this->assertDatabaseHas('outbound_message_attempts', [
            'business_id' => $business->id,
            'channel' => 'whatsapp',
            'status' => 'sent',
        ]);
        $this->assertDatabaseHas('conversation_logs', [
            'business_id' => $business->id,
            'channel' => 'whatsapp',
        ]);
    }

    public function test_inbound_job_records_message_but_blocks_reply_when_billing_is_suspended(): void
    {
        $plan = Plan::query()->firstOrCreate(
            ['code' => 'trial'],
            [
                'name' => 'Trial',
                'included_quotas' => ['messages_sent' => 100],
                'feature_flags' => ['voice_agent' => false],
                'is_active' => true,
            ],
        );

        $business = Business::query()->create([
            'name' => 'Clinic',
            'business_type' => 'clinic',
            'slug' => 'clinic',
            'timezone' => 'UTC',
            'locale' => 'en',
            'channel_config' => [
                'whatsapp' => [
                    'enabled' => true,
                    'phone_number_id' => '123456',
                    'provider' => 'meta_cloud',
                ],
            ],
            'integration_config' => [],
            'reminder_settings' => [],
            'ai_config' => ['llm_provider' => 'claude', 'business_phone' => '15550001111'],
            'is_active' => true,
            'plan' => 'trial',
        ]);

        $this->seedWhatsappConnection($business, true);

        BusinessSubscription::query()->create([
            'business_id' => $business->id,
            'plan_id' => $plan->id,
            'status' => 'trial',
            'lifecycle_status' => 'suspended',
            'warn_at_ratio' => 0.8,
            'enforce_limits' => false,
            'admin_override' => false,
            'current_period_start' => now()->startOfMonth(),
            'current_period_end' => now()->endOfMonth(),
        ]);

        Provider::query()->create([
            'business_id' => $business->id,
            'name' => 'Dr Test',
            'working_hours' => ['monday' => ['active' => true, 'start' => '09:00', 'end' => '17:00']],
            'slot_duration_minutes' => 30,
            'is_active' => true,
        ]);

        $webhook = InboundWebhook::query()->create([
            'business_id' => $business->id,
            'channel' => 'whatsapp',
            'business_slug' => $business->slug,
            'correlation_id' => (string) \Illuminate\Support\Str::uuid(),
            'idempotency_key' => sha1('billing-suspended'),
            'external_message_id' => 'wamid.test.blocked',
            'sender_platform_id' => '15551234567',
            'sender_name' => 'Patient',
            'message_text' => 'hello',
            'message_type' => 'text',
            'payload' => ['example' => true],
            'normalized_payload' => ['text' => 'hello'],
            'signature_valid' => true,
            'status' => 'received',
            'received_at' => now(),
        ]);

        $job = new ProcessIncomingMessage($webhook->id);
        $this->app->call([$job, 'handle']);

        $this->assertDatabaseHas('inbound_webhooks', [
            'id' => $webhook->id,
            'status' => 'blocked',
        ]);
        $this->assertDatabaseHas('conversation_logs', [
            'business_id' => $business->id,
            'channel' => 'whatsapp',
        ]);
        $this->assertDatabaseMissing('outbound_message_attempts', [
            'business_id' => $business->id,
            'channel' => 'whatsapp',
        ]);
    }

    public function test_inbound_job_does_not_reuse_patient_from_another_business_with_same_sender(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.reply.2']]], 200),
        ]);

        $business = Business::query()->create([
            'name' => 'Clinic A',
            'business_type' => 'clinic',
            'slug' => 'clinic-a',
            'timezone' => 'UTC',
            'locale' => 'en',
            'channel_config' => [
                'whatsapp' => [
                    'enabled' => true,
                    'phone_number_id' => '123456',
                    'provider' => 'meta_cloud',
                ],
            ],
            'integration_config' => [],
            'reminder_settings' => [],
            'ai_config' => ['llm_provider' => 'claude', 'business_phone' => '15550001111'],
            'is_active' => true,
            'plan' => 'trial',
        ]);
        $this->seedWhatsappConnection($business, true);

        $otherBusiness = Business::query()->create([
            'name' => 'Clinic B',
            'business_type' => 'clinic',
            'slug' => 'clinic-b',
            'timezone' => 'UTC',
            'locale' => 'en',
            'channel_config' => [],
            'integration_config' => [],
            'reminder_settings' => [],
            'ai_config' => ['llm_provider' => 'claude'],
            'is_active' => true,
            'plan' => 'trial',
        ]);

        $otherTenantPatient = Patient::query()->create([
            'business_id' => $otherBusiness->id,
            'name' => 'Existing Other Tenant Patient',
            'platform_user_id' => '15551234567',
            'platform' => 'whatsapp',
        ]);

        Provider::query()->create([
            'business_id' => $business->id,
            'name' => 'Dr Test',
            'working_hours' => ['monday' => ['active' => true, 'start' => '09:00', 'end' => '17:00']],
            'slot_duration_minutes' => 30,
            'is_active' => true,
        ]);

        $webhook = InboundWebhook::query()->create([
            'business_id' => $business->id,
            'channel' => 'whatsapp',
            'business_slug' => $business->slug,
            'correlation_id' => (string) \Illuminate\Support\Str::uuid(),
            'idempotency_key' => sha1('tenant-isolation'),
            'external_message_id' => 'wamid.tenant-isolation.1',
            'sender_platform_id' => '15551234567',
            'sender_name' => 'Scoped Patient',
            'message_text' => 'hello',
            'message_type' => 'text',
            'payload' => ['example' => true],
            'normalized_payload' => ['text' => 'hello'],
            'signature_valid' => true,
            'status' => 'received',
            'received_at' => now(),
        ]);

        $this->app->instance(SlotCalculatorService::class, new class extends SlotCalculatorService {
            public function compute(\Illuminate\Support\Collection $providers, string $timezone, int $days = 7, ?BusinessService $service = null): array
            {
                return [];
            }
        });

        $this->app->instance(AppointmentAgent::class, new class extends AppointmentAgent {
            public function handle(\App\Models\Business $business, \App\Models\ConversationLog $conversationLog, array $availableSlots, string $inboundText): array
            {
                return [
                    'intent' => 'faq',
                    'provider_id' => null,
                    'date' => null,
                    'time' => null,
                    'service_type' => null,
                    'reply_text' => 'Scoped reply.',
                    'needs_human' => false,
                ];
            }
        });

        $this->app->instance(AppointmentOrchestrator::class, new class extends AppointmentOrchestrator {
            public function execute(\App\Models\Business $business, \App\Models\Patient $patient, \App\Models\ConversationLog $conversationLog, array $agentResponse, string $channel, array $context = []): string
            {
                return 'Scoped reply.';
            }
        });

        $job = new ProcessIncomingMessage($webhook->id);
        $this->app->call([$job, 'handle']);

        $tenantPatient = Patient::query()
            ->where('business_id', $business->id)
            ->where('platform_user_id', '15551234567')
            ->firstOrFail();

        $conversationLog = \App\Models\ConversationLog::query()
            ->where('business_id', $business->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertNotSame($otherTenantPatient->id, $tenantPatient->id);
        $this->assertSame($tenantPatient->id, $conversationLog->patient_id);
        $this->assertSame(2, Patient::query()->where('platform_user_id', '15551234567')->count());
    }

    private function seedWhatsappConnection(Business $business, bool $enabled): void
    {
        BusinessMessagingChannel::query()->create([
            'business_id' => $business->id,
            'channel' => 'whatsapp',
            'is_enabled' => $enabled,
            'approved_at' => now(),
            'enabled_at' => $enabled ? now() : null,
        ]);

        MessagingChannelConnection::query()->create([
            'business_id' => $business->id,
            'channel' => 'whatsapp',
            'provider' => 'meta_cloud',
            'status' => 'connected',
            'credentials' => [
                'access_token' => 'token',
                'verify_token' => 'verify-token',
                'app_secret' => 'meta-app-secret',
            ],
            'runtime_config' => [
                'phone_number_id' => '123456',
            ],
            'connected_at' => now(),
        ]);
    }
}
