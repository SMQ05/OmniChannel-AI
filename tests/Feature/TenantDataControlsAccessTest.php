<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Business;
use App\Models\DataGovernanceRequest;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TenantDataControlsAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        Storage::fake('local');
    }

    public function test_manager_can_view_data_controls_but_cannot_request_deletion(): void
    {
        $business = $this->makeBusiness('manager-clinic');
        $manager = User::query()->create([
            'business_id' => $business->id,
            'name' => 'Manager',
            'email' => 'manager-data-controls@example.com',
            'password' => bcrypt('password'),
            'role' => 'manager',
        ]);

        $this->actingAs($manager)
            ->get(route('settings.data-controls'))
            ->assertOk()
            ->assertSee('Data Controls');

        $this->actingAs($manager)
            ->post(route('settings.data-controls.delete'), [
                'mode' => 'anonymize',
                'reason' => 'Manager delete attempt',
            ])
            ->assertForbidden();
    }

    public function test_artifact_download_is_scoped_to_requesting_business(): void
    {
        $businessA = $this->makeBusiness('artifact-a');
        $businessB = $this->makeBusiness('artifact-b');

        $ownerA = User::query()->create([
            'business_id' => $businessA->id,
            'name' => 'Owner A',
            'email' => 'owner-a-data-controls@example.com',
            'password' => bcrypt('password'),
            'role' => 'business_owner',
        ]);

        $ownerB = User::query()->create([
            'business_id' => $businessB->id,
            'name' => 'Owner B',
            'email' => 'owner-b-data-controls@example.com',
            'password' => bcrypt('password'),
            'role' => 'business_owner',
        ]);

        $artifactPath = 'private/governance/exports/business-' . $businessA->id . '/request-1.json';
        Storage::disk('local')->put($artifactPath, '{"ok":true}');

        $request = DataGovernanceRequest::query()->create([
            'business_id' => $businessA->id,
            'request_scope' => 'tenant',
            'request_type' => 'export',
            'status' => 'completed',
            'requested_by_user_id' => $ownerA->id,
            'artifact_disk' => 'local',
            'artifact_path' => $artifactPath,
            'artifact_expires_at' => now()->addHour(),
            'requested_at' => now(),
            'completed_at' => now(),
        ]);

        $this->actingAs($ownerB)
            ->get(route('settings.data-controls.artifact', $request))
            ->assertForbidden();
    }

    private function makeBusiness(string $slug): Business
    {
        return Business::query()->create([
            'name' => 'Business ' . strtoupper($slug),
            'business_type' => 'clinic',
            'slug' => $slug,
            'timezone' => 'UTC',
            'locale' => 'en',
            'channel_config' => [],
            'integration_config' => [],
            'reminder_settings' => [],
            'ai_config' => [],
            'is_active' => true,
            'plan' => 'trial',
        ]);
    }
}
