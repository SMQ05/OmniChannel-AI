<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Business;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_business_owner_can_view_team_page_and_issue_invite(): void
    {
        [$business, $owner] = $this->makeBusinessUser('business_owner');

        $response = $this->actingAs($owner)->post(route('settings.team.invites.store'), [
            'invite_email' => 'new.staff@example.com',
            'invite_role' => 'staff',
        ]);

        $response->assertRedirect(route('settings.team'));
        $response->assertSessionHas('team_invite_link');

        $this->assertDatabaseHas('team_invites', [
            'business_id' => $business->id,
            'email' => 'new.staff@example.com',
            'role' => 'staff',
            'status' => 'pending',
        ]);
    }

    public function test_manager_can_view_team_page_but_cannot_issue_invite(): void
    {
        [, $manager] = $this->makeBusinessUser('manager');

        $this->actingAs($manager)
            ->get(route('settings.team'))
            ->assertOk();

        $this->actingAs($manager)
            ->post(route('settings.team.invites.store'), [
                'invite_email' => 'blocked@example.com',
                'invite_role' => 'staff',
            ])
            ->assertForbidden();
    }

    public function test_staff_cannot_open_team_page_or_manage_appointments(): void
    {
        [$business, $staff] = $this->makeBusinessUser('staff');

        $this->actingAs($staff)
            ->get(route('settings.team'))
            ->assertForbidden();

        $this->actingAs($staff)
            ->get(route('appointments.create'))
            ->assertForbidden();
    }

    /**
     * @return array{0: Business, 1: User}
     */
    private function makeBusinessUser(string $role): array
    {
        $business = Business::query()->create([
            'name' => ucfirst($role) . ' Clinic',
            'business_type' => 'clinic',
            'slug' => $role . '-clinic',
            'timezone' => 'UTC',
            'locale' => 'en',
            'channel_config' => [],
            'integration_config' => [],
            'reminder_settings' => [],
            'ai_config' => [],
            'is_active' => true,
            'plan' => 'pro',
        ]);

        $user = User::query()->create([
            'business_id' => $business->id,
            'name' => ucfirst($role) . ' User',
            'email' => $role . '@example.com',
            'password' => bcrypt('password'),
            'role' => $role,
        ]);

        return [$business, $user];
    }
}
