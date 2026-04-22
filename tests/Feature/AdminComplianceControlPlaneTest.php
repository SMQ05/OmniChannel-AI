<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Business;
use App\Models\DataGovernanceRequest;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminComplianceControlPlaneTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_super_admin_can_view_compliance_index_and_request_details(): void
    {
        $admin = User::query()->create([
            'business_id' => null,
            'name' => 'Admin',
            'email' => 'admin-compliance@example.com',
            'password' => bcrypt('password'),
            'role' => 'super_admin',
        ]);

        $business = Business::query()->create([
            'name' => 'Compliance Clinic',
            'business_type' => 'clinic',
            'slug' => 'compliance-clinic',
            'timezone' => 'UTC',
            'locale' => 'en',
            'channel_config' => [],
            'integration_config' => [],
            'reminder_settings' => [],
            'ai_config' => [],
            'is_active' => true,
            'plan' => 'trial',
        ]);

        $request = DataGovernanceRequest::query()->create([
            'business_id' => $business->id,
            'request_scope' => 'tenant',
            'request_type' => 'export',
            'status' => 'pending_approval',
            'requested_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.compliance.index'))
            ->assertOk()
            ->assertSee('Governance Requests');

        $this->actingAs($admin)
            ->get(route('admin.compliance.show', $request))
            ->assertOk()
            ->assertSee('Request #'.$request->id);
    }
}
