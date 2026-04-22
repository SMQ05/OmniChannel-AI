<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Business;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingRulesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_business_owner_can_update_booking_rules(): void
    {
        [$business, $owner] = $this->makeBusinessUser('business_owner');

        $this->actingAs($owner)
            ->post(route('settings.booking-rules.update'), [
                'lead_time_minutes' => 120,
                'max_advance_days' => 45,
                'cancellation_notice_hours' => 24,
                'reschedule_notice_hours' => 12,
                'allow_same_day_booking' => '1',
                'require_provider_selection' => '1',
                'default_booking_status' => 'pending',
            ])
            ->assertRedirect(route('settings.booking-rules'));

        $business->refresh();

        $this->assertSame([
            'lead_time_minutes' => 120,
            'max_advance_days' => 45,
            'cancellation_notice_hours' => 24,
            'reschedule_notice_hours' => 12,
            'allow_same_day_booking' => true,
            'require_provider_selection' => true,
            'default_booking_status' => 'pending',
        ], $business->bookingRules());
    }

    public function test_receptionist_cannot_manage_booking_rules(): void
    {
        [, $receptionist] = $this->makeBusinessUser('receptionist');

        $this->actingAs($receptionist)
            ->get(route('settings.booking-rules'))
            ->assertForbidden();

        $this->actingAs($receptionist)
            ->post(route('settings.booking-rules.update'), [
                'lead_time_minutes' => 30,
                'max_advance_days' => 30,
                'cancellation_notice_hours' => 4,
                'reschedule_notice_hours' => 4,
                'default_booking_status' => 'confirmed',
            ])
            ->assertForbidden();
    }

    /**
     * @return array{0: Business, 1: User}
     */
    private function makeBusinessUser(string $role): array
    {
        $business = Business::query()->create([
            'name' => ucfirst($role) . ' Rules Clinic',
            'business_type' => 'clinic',
            'slug' => $role . '-rules-clinic',
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
            'email' => $role . '-rules@example.com',
            'password' => bcrypt('password'),
            'role' => $role,
        ]);

        return [$business, $user];
    }
}
