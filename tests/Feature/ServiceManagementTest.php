<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Business;
use App\Models\BusinessService;
use App\Models\Provider;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_business_owner_can_create_service_and_map_providers(): void
    {
        [$business, $owner] = $this->makeBusinessUser('business_owner');
        $provider = $this->makeProvider($business, 'Dr Mapping');

        $response = $this->actingAs($owner)->post(route('services.store'), [
            'name' => 'General Consultation',
            'description' => 'Structured consult service',
            'duration_minutes' => 45,
            'price' => 125.50,
            'sort_order' => 2,
            'provider_ids' => [$provider->id],
            'booking_rules' => [
                'buffer_before_minutes' => 10,
                'buffer_after_minutes' => 5,
                'allow_online_booking' => '1',
                'requires_manual_confirmation' => '0',
            ],
            'is_active' => '1',
        ]);

        $service = BusinessService::query()->where('business_id', $business->id)->firstOrFail();

        $response->assertRedirect(route('services.show', $service));

        $this->assertDatabaseHas('business_services', [
            'business_id' => $business->id,
            'name' => 'General Consultation',
            'duration_minutes' => 45,
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('business_service_provider', [
            'business_service_id' => $service->id,
            'provider_id' => $provider->id,
        ]);
    }

    public function test_business_service_routes_are_tenant_scoped(): void
    {
        [$businessA, $ownerA] = $this->makeBusinessUser('business_owner', 'alpha');
        [$businessB, $ownerB] = $this->makeBusinessUser('business_owner', 'beta');

        $service = BusinessService::query()->create([
            'business_id' => $businessA->id,
            'name' => 'Alpha Service',
            'slug' => 'alpha-service',
            'duration_minutes' => 30,
            'is_active' => true,
        ]);

        $this->actingAs($ownerA)
            ->get(route('services.show', $service))
            ->assertOk();

        $this->actingAs($ownerB)
            ->get(route('services.show', $service))
            ->assertNotFound();
    }

    public function test_receptionist_cannot_create_services(): void
    {
        [$business, $receptionist] = $this->makeBusinessUser('receptionist');
        $provider = $this->makeProvider($business, 'Front Desk Doc');

        $this->actingAs($receptionist)
            ->post(route('services.store'), [
                'name' => 'Blocked Service',
                'duration_minutes' => 30,
                'provider_ids' => [$provider->id],
            ])
            ->assertForbidden();
    }

    /**
     * @return array{0: Business, 1: User}
     */
    private function makeBusinessUser(string $role, string $slugPrefix = 'service'): array
    {
        $business = Business::query()->create([
            'name' => ucfirst($slugPrefix) . ' Clinic',
            'business_type' => 'clinic',
            'slug' => $slugPrefix . '-clinic',
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
            'email' => $slugPrefix . '-' . $role . '@example.com',
            'password' => bcrypt('password'),
            'role' => $role,
        ]);

        return [$business, $user];
    }

    private function makeProvider(Business $business, string $name): Provider
    {
        return Provider::query()->create([
            'business_id' => $business->id,
            'name' => $name,
            'working_hours' => [
                'monday' => ['active' => true, 'start' => '09:00', 'end' => '17:00'],
            ],
            'slot_duration_minutes' => 30,
            'is_active' => true,
        ]);
    }
}
