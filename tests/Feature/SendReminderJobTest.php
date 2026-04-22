<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\SendReminderJob;
use App\Models\Appointment;
use App\Models\Business;
use App\Models\BusinessMessagingChannel;
use App\Models\BusinessSubscription;
use App\Models\MessagingChannelConnection;
use App\Models\Patient;
use App\Models\Provider;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SendReminderJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_reminder_job_sends_and_marks_offset_sent(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.reminder.1']]], 200),
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
            'reminder_settings' => [
                'reminders' => [
                    ['offset_hours' => 24, 'message_template' => 'Reminder for {patient_name} at {time}'],
                ],
            ],
            'ai_config' => ['business_phone' => '15550001111'],
            'is_active' => true,
            'plan' => 'trial',
        ]);

        $this->seedWhatsappConnection($business);

        $patient = Patient::query()->create([
            'business_id' => $business->id,
            'name' => 'Patient',
            'platform_user_id' => '15551234567',
            'platform' => 'whatsapp',
        ]);

        $provider = Provider::query()->create([
            'business_id' => $business->id,
            'name' => 'Dr Test',
            'working_hours' => ['monday' => ['active' => true, 'start' => '09:00', 'end' => '17:00']],
            'slot_duration_minutes' => 30,
            'is_active' => true,
        ]);

        $appointment = Appointment::query()->create([
            'business_id' => $business->id,
            'provider_id' => $provider->id,
            'patient_id' => $patient->id,
            'service_type' => 'Consultation',
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addMinutes(30),
            'status' => 'confirmed',
            'booked_via' => 'whatsapp',
        ]);

        $job = new SendReminderJob($appointment, 24);
        $this->app->call([$job, 'handle']);

        $appointment->refresh();

        $this->assertNotNull(DB::table('appointments')->where('id', $appointment->id)->value('reminder_sent_at'));
        $this->assertDatabaseHas('outbound_message_attempts', [
            'business_id' => $business->id,
            'channel' => 'whatsapp',
            'status' => 'sent',
        ]);
    }

    public function test_reminder_job_skips_when_billing_lifecycle_is_suspended(): void
    {
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
            'reminder_settings' => [
                'reminders' => [
                    ['offset_hours' => 24, 'message_template' => 'Reminder for {patient_name} at {time}'],
                ],
            ],
            'ai_config' => ['business_phone' => '15550001111'],
            'is_active' => true,
            'plan' => 'trial',
        ]);

        $this->seedWhatsappConnection($business);

        BusinessSubscription::query()->create([
            'business_id' => $business->id,
            'status' => 'trial',
            'lifecycle_status' => 'suspended',
            'warn_at_ratio' => 0.8,
            'enforce_limits' => false,
            'admin_override' => false,
            'current_period_start' => now()->startOfMonth(),
            'current_period_end' => now()->endOfMonth(),
        ]);

        $patient = Patient::query()->create([
            'business_id' => $business->id,
            'name' => 'Patient',
            'platform_user_id' => '15551234567',
            'platform' => 'whatsapp',
        ]);

        $provider = Provider::query()->create([
            'business_id' => $business->id,
            'name' => 'Dr Test',
            'working_hours' => ['monday' => ['active' => true, 'start' => '09:00', 'end' => '17:00']],
            'slot_duration_minutes' => 30,
            'is_active' => true,
        ]);

        $appointment = Appointment::query()->create([
            'business_id' => $business->id,
            'provider_id' => $provider->id,
            'patient_id' => $patient->id,
            'service_type' => 'Consultation',
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addMinutes(30),
            'status' => 'confirmed',
            'booked_via' => 'whatsapp',
        ]);

        $job = new SendReminderJob($appointment, 24);
        $this->app->call([$job, 'handle']);

        $this->assertDatabaseMissing('outbound_message_attempts', [
            'business_id' => $business->id,
            'channel' => 'whatsapp',
        ]);
        $this->assertNull($appointment->fresh()->reminder_sent_at);
    }

    private function seedWhatsappConnection(Business $business): void
    {
        BusinessMessagingChannel::query()->create([
            'business_id' => $business->id,
            'channel' => 'whatsapp',
            'is_enabled' => true,
            'approved_at' => now(),
            'enabled_at' => now(),
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
