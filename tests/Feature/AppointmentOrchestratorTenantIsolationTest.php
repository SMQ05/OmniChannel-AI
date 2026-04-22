<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Business;
use App\Models\ConversationLog;
use App\Models\Patient;
use App\Models\Provider;
use App\Services\AppointmentOrchestrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppointmentOrchestratorTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_orchestrator_rejects_provider_from_another_business(): void
    {
        $business = $this->makeBusiness('tenant-a');
        $otherBusiness = $this->makeBusiness('tenant-b');

        $patient = Patient::query()->create([
            'business_id' => $business->id,
            'name' => 'Tenant A Patient',
            'platform_user_id' => '15550001111',
            'platform' => 'whatsapp',
        ]);

        $conversationLog = ConversationLog::query()->create([
            'business_id' => $business->id,
            'patient_id' => $patient->id,
            'channel' => 'whatsapp',
            'messages' => [],
            'ai_model_used' => 'claude',
            'session_started_at' => now()->utc(),
        ]);

        $foreignProvider = Provider::query()->create([
            'business_id' => $otherBusiness->id,
            'name' => 'Foreign Provider',
            'working_hours' => ['monday' => ['active' => true, 'start' => '09:00', 'end' => '17:00']],
            'slot_duration_minutes' => 30,
            'is_active' => true,
        ]);

        $reply = app(AppointmentOrchestrator::class)->execute(
            business: $business,
            patient: $patient,
            conversationLog: $conversationLog,
            agentResponse: [
                'intent' => 'book',
                'provider_id' => $foreignProvider->id,
                'service_id' => null,
                'date' => now()->addDay()->toDateString(),
                'time' => '10:00',
                'service_type' => 'Consultation',
                'reply_text' => 'Book it.',
                'needs_human' => false,
            ],
            channel: 'whatsapp',
        );

        $this->assertSame(
            "I couldn't find that provider. Could you let me know your preferred provider or would you like me to suggest one?",
            $reply,
        );
        $this->assertDatabaseCount('appointments', 0);
    }

    private function makeBusiness(string $slug): Business
    {
        return Business::query()->create([
            'name' => strtoupper($slug),
            'business_type' => 'clinic',
            'slug' => $slug,
            'timezone' => 'UTC',
            'locale' => 'en',
            'channel_config' => [],
            'integration_config' => [],
            'integration_secrets' => [],
            'reminder_settings' => [],
            'ai_config' => ['llm_provider' => 'claude'],
            'operations_config' => [],
            'is_active' => true,
            'plan' => 'trial',
        ]);
    }
}
