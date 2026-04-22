<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Business;
use App\Models\ConversationLog;
use App\Models\DataGovernanceRequest;
use App\Models\InboundWebhook;
use App\Models\OutboundMessageAttempt;
use App\Models\Patient;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class DataGovernanceWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        Storage::fake('local');
    }

    public function test_export_request_is_approval_driven_and_completes_asynchronously(): void
    {
        [$business, $owner, $admin] = $this->makeActors();

        $this->actingAs($owner)
            ->post(route('settings.data-controls.export'), [
                'reason' => 'Compliance export required for vendor review.',
            ])
            ->assertRedirect(route('settings.data-controls'));

        $request = DataGovernanceRequest::query()->latest('id')->firstOrFail();
        $this->assertSame(DataGovernanceRequest::STATUS_PENDING_APPROVAL, $request->status);
        $this->assertSame(DataGovernanceRequest::TYPE_EXPORT, $request->request_type);

        $this->actingAs($admin)
            ->post(route('admin.compliance.approve', $request), [
                'approval_reason' => 'Approved for tenant-owned export.',
            ])
            ->assertRedirect(route('admin.compliance.show', $request));

        $request = $request->refresh();
        $this->assertSame(DataGovernanceRequest::STATUS_COMPLETED, $request->status);
        $this->assertNotNull($request->artifact_path);
        $this->assertNotNull($request->artifact_expires_at);
        Storage::disk($request->artifact_disk ?? 'local')->assertExists((string) $request->artifact_path);
    }

    public function test_deletion_request_defaults_to_conservative_anonymization_when_hard_delete_not_allowed(): void
    {
        [$business, $owner, $admin] = $this->makeActors();
        $patient = Patient::query()->create([
            'business_id' => $business->id,
            'name' => 'Sensitive Patient',
            'phone' => '555-1000',
            'email' => 'patient@example.com',
            'platform_user_id' => 'user-1000',
            'platform' => 'whatsapp',
            'notes' => 'contains pii',
        ]);

        ConversationLog::query()->create([
            'business_id' => $business->id,
            'patient_id' => $patient->id,
            'channel' => 'whatsapp',
            'messages' => [['role' => 'user', 'content' => 'my private details']],
            'human_mode' => false,
            'session_started_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        InboundWebhook::query()->create([
            'business_id' => $business->id,
            'channel' => 'whatsapp',
            'business_slug' => $business->slug,
            'correlation_id' => (string) Str::uuid(),
            'idempotency_key' => 'governance-delete-' . Str::uuid()->toString(),
            'message_text' => 'Private inbound message',
            'payload' => ['message' => 'private'],
            'status' => 'received',
        ]);

        OutboundMessageAttempt::query()->create([
            'business_id' => $business->id,
            'channel' => 'whatsapp',
            'recipient_platform_id' => 'patient-1',
            'idempotency_key' => 'governance-delete-outbound-' . Str::uuid()->toString(),
            'correlation_id' => (string) Str::uuid(),
            'status' => 'sent',
            'message_text' => 'Sensitive outbound',
            'attempts' => 1,
        ]);

        AuditLog::query()->create([
            'business_id' => $business->id,
            'actor_user_id' => $admin->id,
            'actor_role' => 'super_admin',
            'action' => 'seed.audit',
            'subject_type' => Business::class,
            'subject_id' => $business->id,
            'request_id' => (string) Str::uuid(),
            'payload' => ['seed' => true],
            'created_at' => now(),
        ]);

        $this->actingAs($owner)
            ->post(route('settings.data-controls.delete'), [
                'mode' => 'hard_delete',
                'reason' => 'Data minimization requirement for closed account.',
            ])
            ->assertRedirect(route('settings.data-controls'));

        $request = DataGovernanceRequest::query()->latest('id')->firstOrFail();
        $this->assertSame(DataGovernanceRequest::STATUS_PENDING_APPROVAL, $request->status);

        $this->actingAs($admin)
            ->post(route('admin.compliance.approve', $request))
            ->assertRedirect(route('admin.compliance.show', $request));

        $request = $request->refresh();
        $this->assertSame(DataGovernanceRequest::STATUS_COMPLETED, $request->status);
        $this->assertSame('anonymize', $request->result_summary['effective_mode'] ?? null);

        $this->assertDatabaseHas('patients', [
            'id' => $patient->id,
            'name' => 'Redacted',
            'phone' => null,
            'email' => null,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'business_id' => $business->id,
            'action' => 'seed.audit',
        ]);
    }

    /**
     * @return array{Business, User, User}
     */
    private function makeActors(): array
    {
        $business = Business::query()->create([
            'name' => 'Governance Clinic',
            'business_type' => 'clinic',
            'slug' => 'governance-clinic',
            'timezone' => 'UTC',
            'locale' => 'en',
            'channel_config' => [],
            'integration_config' => [],
            'reminder_settings' => [],
            'ai_config' => [],
            'is_active' => true,
            'plan' => 'trial',
        ]);

        $owner = User::query()->create([
            'business_id' => $business->id,
            'name' => 'Owner',
            'email' => 'owner-governance@example.com',
            'password' => bcrypt('password'),
            'role' => 'business_owner',
        ]);

        $admin = User::query()->create([
            'business_id' => null,
            'name' => 'Admin',
            'email' => 'admin-governance@example.com',
            'password' => bcrypt('password'),
            'role' => 'super_admin',
        ]);

        return [$business, $owner, $admin];
    }
}
