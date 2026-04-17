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

    public function test_invalid_signature_is_rejected(): void
    {
        $business = $this->makeBusiness();

        $response = $this->withHeader('X-Hub-Signature-256', 'sha256=bad-signature')
            ->postJson(route('webhook.whatsapp.receive', ['slug' => $business->slug]), $this->whatsAppPayload());

        $response->assertForbidden();
        $this->assertDatabaseCount('inbound_webhooks', 0);
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
