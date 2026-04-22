<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\ProcessIncomingMessage;
use App\Models\Business;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WebhookControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_whatsapp_webhook_is_stored_and_dispatched(): void
    {
        Queue::fake();

        $business = $this->makeBusiness();
        $payload = $this->whatsAppPayload();
        $rawBody = json_encode($payload, JSON_THROW_ON_ERROR);
        $signature = 'sha256=' . hash_hmac('sha256', $rawBody, 'meta-app-secret');

        $response = $this->withHeader('X-Hub-Signature-256', $signature)
            ->postJson(route('webhook.whatsapp.receive', ['slug' => $business->slug]), $payload);

        $response->assertOk()->assertJson(['status' => 'ok']);
        $this->assertDatabaseHas('inbound_webhooks', [
            'business_id' => $business->id,
            'channel' => 'whatsapp',
            'external_message_id' => 'wamid.test.1',
            'status' => 'dispatched',
        ]);
        Queue::assertPushed(ProcessIncomingMessage::class, 1);
    }

    public function test_valid_whatsapp_webhook_uses_first_class_connection_records(): void
    {
        Queue::fake();

        $business = Business::query()->create([
            'name' => 'Clinic',
            'business_type' => 'clinic',
            'slug' => 'clinic-records',
            'timezone' => 'UTC',
            'locale' => 'en',
            'channel_config' => ['whatsapp' => ['enabled' => false]],
            'integration_config' => [],
            'reminder_settings' => [],
            'ai_config' => ['llm_provider' => 'claude'],
            'is_active' => true,
            'plan' => 'trial',
        ]);

        \App\Models\BusinessMessagingChannel::query()->create([
            'business_id' => $business->id,
            'channel' => 'whatsapp',
            'is_enabled' => true,
            'approved_at' => now(),
            'enabled_at' => now(),
        ]);

        \App\Models\MessagingChannelConnection::query()->create([
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

        $payload = $this->whatsAppPayload();
        $rawBody = json_encode($payload, JSON_THROW_ON_ERROR);
        $signature = 'sha256=' . hash_hmac('sha256', $rawBody, 'meta-app-secret');

        $this->withHeader('X-Hub-Signature-256', $signature)
            ->postJson(route('webhook.whatsapp.receive', ['slug' => $business->slug]), $payload)
            ->assertOk()
            ->assertJson(['status' => 'ok']);

        $this->assertDatabaseHas('inbound_webhooks', [
            'business_id' => $business->id,
            'channel' => 'whatsapp',
            'external_message_id' => 'wamid.test.1',
        ]);
        Queue::assertPushed(ProcessIncomingMessage::class, 1);
    }

    public function test_invalid_signature_is_rejected(): void
    {
        $business = $this->makeBusiness();

        $response = $this->withHeader('X-Hub-Signature-256', 'sha256=bad-signature')
            ->postJson(route('webhook.whatsapp.receive', ['slug' => $business->slug]), $this->whatsAppPayload());

        $response->assertForbidden();
        $this->assertDatabaseCount('inbound_webhooks', 0);
    }

    public function test_valid_twilio_whatsapp_webhook_is_stored_and_dispatched(): void
    {
        Queue::fake();

        $business = $this->makeTwilioBusiness();
        $payload = [
            'MessageSid' => 'SM123',
            'From' => 'whatsapp:+15551234567',
            'To' => 'whatsapp:+14155238886',
            'Body' => 'hello from twilio',
            'ProfileName' => 'Twilio Patient',
            'NumMedia' => '0',
        ];

        $url = route('webhook.whatsapp.receive', ['slug' => $business->slug]);
        $payloadString = $url;
        ksort($payload);
        foreach ($payload as $key => $value) {
            $payloadString .= $key . $value;
        }
        $signature = base64_encode(hash_hmac('sha1', $payloadString, 'twilio-auth-token', true));

        $response = $this->withHeader('X-Twilio-Signature', $signature)
            ->post($url, $payload);

        $response->assertOk()->assertJson(['status' => 'ok']);
        $this->assertDatabaseHas('inbound_webhooks', [
            'business_id' => $business->id,
            'channel' => 'whatsapp',
            'external_message_id' => 'SM123',
            'status' => 'dispatched',
        ]);
        Queue::assertPushed(ProcessIncomingMessage::class, 1);
    }

    private function makeBusiness(): Business
    {
        return Business::query()->create([
            'name' => 'Clinic',
            'business_type' => 'clinic',
            'slug' => 'clinic',
            'timezone' => 'UTC',
            'locale' => 'en',
            'channel_config' => [
                'whatsapp' => [
                    'enabled' => true,
                    'phone_number_id' => '123456',
                    'access_token' => 'token',
                    'verify_token' => 'verify-token',
                    'app_secret' => 'meta-app-secret',
                ],
                'messenger' => [
                    'enabled' => false,
                ],
            ],
            'integration_config' => [],
            'reminder_settings' => [],
            'ai_config' => ['llm_provider' => 'claude'],
            'is_active' => true,
            'plan' => 'trial',
        ]);
    }

    private function makeTwilioBusiness(): Business
    {
        return Business::query()->create([
            'name' => 'Clinic',
            'business_type' => 'clinic',
            'slug' => 'clinic-twilio',
            'timezone' => 'UTC',
            'locale' => 'en',
            'channel_config' => [
                'whatsapp' => [
                    'enabled' => true,
                    'provider' => 'twilio',
                    'twilio_account_sid' => 'AC123',
                    'twilio_auth_token' => 'twilio-auth-token',
                    'twilio_from_number' => 'whatsapp:+14155238886',
                ],
                'messenger' => [
                    'enabled' => false,
                ],
            ],
            'integration_config' => [],
            'reminder_settings' => [],
            'ai_config' => ['llm_provider' => 'claude'],
            'is_active' => true,
            'plan' => 'trial',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function whatsAppPayload(): array
    {
        return [
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'id' => 'business-entry',
                'changes' => [[
                    'field' => 'messages',
                    'value' => [
                        'messaging_product' => 'whatsapp',
                        'metadata' => [
                            'display_phone_number' => '15555550000',
                            'phone_number_id' => '123456',
                        ],
                        'contacts' => [[
                            'profile' => ['name' => 'Test Patient'],
                            'wa_id' => '15551234567',
                        ]],
                        'messages' => [[
                            'from' => '15551234567',
                            'id' => 'wamid.test.1',
                            'timestamp' => (string) now()->timestamp,
                            'text' => ['body' => 'hello'],
                            'type' => 'text',
                        ]],
                    ],
                ]],
            ]],
        ];
    }
}
