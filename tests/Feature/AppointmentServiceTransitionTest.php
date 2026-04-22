<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Business;
use App\Models\BusinessService;
use App\Models\Patient;
use App\Models\Provider;
use App\Models\User;
use App\Services\SlotCalculatorService;
use Carbon\CarbonImmutable;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppointmentServiceTransitionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_manual_appointment_writes_service_id_and_service_type_snapshot(): void
    {
        [$business, $owner] = $this->makeBusinessUser('business_owner');
        $provider = $this->makeProvider($business);
        $patient = $this->makePatient($business);
        $service = $this->makeService($business, $provider, [
            'name' => 'Structured Consult',
            'duration_minutes' => 45,
        ]);

        $response = $this->actingAs($owner)->post(route('appointments.store'), [
            'provider_id' => $provider->id,
            'patient_id' => $patient->id,
            'service_id' => $service->id,
            'start_time' => '2026-04-20T10:00',
            'notes' => 'Structured booking',
        ]);

        $appointment = Appointment::query()->firstOrFail();

        $response->assertRedirect(route('appointments.show', $appointment));

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'business_id' => $business->id,
            'service_id' => $service->id,
            'service_type' => 'Structured Consult',
        ]);
    }

    public function test_manual_appointment_rejects_service_when_provider_is_not_mapped(): void
    {
        [$business, $owner] = $this->makeBusinessUser('business_owner');
        $provider = $this->makeProvider($business);
        $patient = $this->makePatient($business);
        $service = BusinessService::query()->create([
            'business_id' => $business->id,
            'name' => 'Unmapped Service',
            'slug' => 'unmapped-service',
            'duration_minutes' => 30,
            'is_active' => true,
        ]);

        $this->actingAs($owner)
            ->from(route('appointments.create'))
            ->post(route('appointments.store'), [
                'provider_id' => $provider->id,
                'patient_id' => $patient->id,
                'service_id' => $service->id,
                'start_time' => '2026-04-20T10:00',
            ])
            ->assertRedirect(route('appointments.create'))
            ->assertSessionHasErrors('service_id');
    }

    public function test_slot_calculator_uses_service_duration_and_buffers_but_keeps_legacy_fallback(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-04-20 08:00:00', 'UTC'));

        $business = Business::query()->create([
            'name' => 'Slots Clinic',
            'business_type' => 'clinic',
            'slug' => 'slots-clinic',
            'timezone' => 'UTC',
            'locale' => 'en',
            'channel_config' => [],
            'integration_config' => [],
            'reminder_settings' => [],
            'ai_config' => [],
            'operations_config' => [],
            'is_active' => true,
            'plan' => 'pro',
        ]);

        $provider = $this->makeProvider($business);

        $service = $this->makeService($business, $provider, [
            'name' => 'Buffered Service',
            'duration_minutes' => 45,
            'booking_rules' => [
                'buffer_before_minutes' => 0,
                'buffer_after_minutes' => 15,
                'allow_online_booking' => true,
                'requires_manual_confirmation' => false,
            ],
        ]);

        Appointment::query()->create([
            'business_id' => $business->id,
            'provider_id' => $provider->id,
            'patient_id' => $this->makePatient($business)->id,
            'service_id' => $service->id,
            'service_type' => $service->name,
            'start_time' => '2026-04-20 09:00:00',
            'end_time' => '2026-04-20 09:45:00',
            'status' => 'confirmed',
            'booked_via' => 'manual',
        ]);

        $provider->load([
            'services',
            'blockedDates',
            'appointments' => fn ($query) => $query->whereDate('start_time', '2026-04-20'),
        ]);

        $calculator = app(SlotCalculatorService::class);

        $structuredSlots = $calculator->compute(collect([$provider]), 'UTC', 1, $service);
        $legacySlots = $calculator->compute(collect([$provider]), 'UTC', 1);

        $this->assertSame('2026-04-20T10:00:00.000000Z', $structuredSlots[0]['start_utc']);
        $this->assertSame('2026-04-20T10:45:00.000000Z', $structuredSlots[0]['end_utc']);
        $this->assertSame('2026-04-20T10:00:00.000000Z', $legacySlots[0]['start_utc']);
        $this->assertSame('2026-04-20T10:30:00.000000Z', $legacySlots[0]['end_utc']);

        CarbonImmutable::setTestNow();
    }

    /**
     * @return array{0: Business, 1: User}
     */
    private function makeBusinessUser(string $role): array
    {
        $business = Business::query()->create([
            'name' => ucfirst($role) . ' Appointment Clinic',
            'business_type' => 'clinic',
            'slug' => $role . '-appointment-clinic',
            'timezone' => 'UTC',
            'locale' => 'en',
            'channel_config' => [],
            'integration_config' => [],
            'reminder_settings' => [],
            'ai_config' => [],
            'operations_config' => [],
            'is_active' => true,
            'plan' => 'pro',
        ]);

        $user = User::query()->create([
            'business_id' => $business->id,
            'name' => ucfirst($role) . ' User',
            'email' => $role . '-appointments@example.com',
            'password' => bcrypt('password'),
            'role' => $role,
        ]);

        return [$business, $user];
    }

    private function makeProvider(Business $business): Provider
    {
        return Provider::query()->create([
            'business_id' => $business->id,
            'name' => 'Dr Structured',
            'working_hours' => [
                'monday' => ['active' => true, 'start' => '09:00', 'end' => '17:00'],
            ],
            'slot_duration_minutes' => 30,
            'is_active' => true,
        ]);
    }

    private function makePatient(Business $business): Patient
    {
        return Patient::query()->create([
            'business_id' => $business->id,
            'name' => 'Patient Structured',
            'phone' => '15550001234',
            'platform_user_id' => '15550001234',
            'platform' => 'whatsapp',
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeService(Business $business, Provider $provider, array $overrides = []): BusinessService
    {
        $service = BusinessService::query()->create(array_merge([
            'business_id' => $business->id,
            'name' => 'General Consultation',
            'slug' => 'general-consultation',
            'duration_minutes' => 30,
            'is_active' => true,
            'booking_rules' => [
                'buffer_before_minutes' => 0,
                'buffer_after_minutes' => 0,
                'allow_online_booking' => true,
                'requires_manual_confirmation' => false,
            ],
        ], $overrides));

        $service->providers()->sync([$provider->id]);

        return $service;
    }
}
