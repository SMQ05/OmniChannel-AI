<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Events\HumanHandoffRequested;
use App\Models\Business;
use App\Models\ConversationLog;
use App\Models\Patient;
use App\Models\User;
use App\Services\AppointmentOrchestrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class HumanHandoffBroadcastTest extends TestCase
{
    use RefreshDatabase;

    public function test_ai_handoff_dispatches_private_broadcast_event_and_sets_cache_flag(): void
    {
        Event::fake([HumanHandoffRequested::class]);

        [$business, $patient, $conversationLog] = $this->createConversationContext();

        $reply = app(AppointmentOrchestrator::class)->execute(
            business: $business,
            patient: $patient,
            conversationLog: $conversationLog,
            agentResponse: [
                'intent' => 'handoff',
                'provider_id' => null,
                'service_id' => null,
                'date' => null,
                'time' => null,
                'service_type' => null,
                'reply_text' => 'A human will join shortly.',
                'needs_human' => false,
            ],
            channel: 'whatsapp',
        );

        $this->assertSame('A human will join shortly.', $reply);
        $this->assertTrue((bool) $conversationLog->fresh()->human_mode);
        $this->assertTrue(Cache::get("human_mode:{$business->id}:whatsapp:{$patient->platform_user_id}", false));

        Event::assertDispatched(HumanHandoffRequested::class, function (HumanHandoffRequested $event) use ($business, $conversationLog, $patient): bool {
            return $event->businessId === $business->id
                && $event->conversationLogId === $conversationLog->id
                && $event->patientId === $patient->id
                && $event->channel === 'whatsapp'
                && $event->source === 'ai_agent';
        });
    }

    public function test_manual_takeover_dispatches_event_once_and_sets_cache_flag(): void
    {
        Event::fake([HumanHandoffRequested::class]);

        [$business, $patient, $conversationLog] = $this->createConversationContext();

        $user = User::factory()->create([
            'business_id' => $business->id,
            'role' => 'business_owner',
        ]);

        $this->actingAs($user)->post(route('conversations.takeover', $conversationLog))
            ->assertRedirect();

        $this->assertTrue((bool) $conversationLog->fresh()->human_mode);
        $this->assertTrue(Cache::get("human_mode:{$business->id}:whatsapp:{$patient->platform_user_id}", false));

        Event::assertDispatchedTimes(HumanHandoffRequested::class, 1);
        Event::assertDispatched(HumanHandoffRequested::class, function (HumanHandoffRequested $event) use ($business, $conversationLog, $patient): bool {
            return $event->businessId === $business->id
                && $event->conversationLogId === $conversationLog->id
                && $event->patientId === $patient->id
                && $event->source === 'manual_takeover';
        });

        $this->actingAs($user)->post(route('conversations.takeover', $conversationLog))
            ->assertRedirect();

        Event::assertDispatchedTimes(HumanHandoffRequested::class, 1);
    }

    /**
     * @return array{0: Business, 1: Patient, 2: ConversationLog}
     */
    private function createConversationContext(): array
    {
        $business = Business::query()->create([
            'name' => 'Clinic',
            'business_type' => 'clinic',
            'slug' => 'clinic',
            'timezone' => 'UTC',
            'locale' => 'en',
            'channel_config' => [],
            'integration_config' => [],
            'reminder_settings' => [],
            'ai_config' => ['llm_provider' => 'claude'],
            'is_active' => true,
            'plan' => 'trial',
        ]);

        $patient = Patient::withoutGlobalScope(\App\Models\Concerns\TenantScope::class)->create([
            'business_id' => $business->id,
            'name' => 'Patient',
            'platform_user_id' => '15551234567',
            'platform' => 'whatsapp',
        ]);

        $conversationLog = ConversationLog::withoutGlobalScope(\App\Models\Concerns\TenantScope::class)->create([
            'business_id' => $business->id,
            'patient_id' => $patient->id,
            'channel' => 'whatsapp',
            'messages' => [],
            'ai_model_used' => 'claude',
            'human_mode' => false,
            'session_started_at' => now()->utc(),
        ]);

        return [$business, $patient, $conversationLog];
    }
}
