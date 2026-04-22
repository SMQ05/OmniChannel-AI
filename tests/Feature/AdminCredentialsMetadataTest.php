<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Business;
use App\Models\CredentialMetadata;
use App\Models\MessagingChannelConnection;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCredentialsMetadataTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_super_admin_can_view_credentials_index(): void
    {
        $admin = User::query()->create([
            'business_id' => null,
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'role' => 'super_admin',
        ]);

        $business = Business::query()->create([
            'name' => 'Clinic',
            'business_type' => 'clinic',
            'slug' => 'clinic',
            'timezone' => 'UTC',
            'locale' => 'en',
            'channel_config' => [],
            'integration_config' => [],
            'reminder_settings' => [],
            'ai_config' => [],
            'is_active' => true,
            'plan' => 'trial',
        ]);

        $messagingConnection = MessagingChannelConnection::query()->create([
            'business_id' => $business->id,
            'channel' => 'whatsapp',
            'provider' => 'meta_cloud',
            'status' => 'connected',
            'credentials' => ['api_key' => 'test-key'],
            'runtime_config' => ['phone_number_id' => '123'],
        ]);

        CredentialMetadata::query()->create([
            'source_type' => 'messaging_connection',
            'source_id' => $messagingConnection->id,
            'provider' => 'meta_cloud',
            'key_name' => 'WhatsApp Production',
            'description' => 'Production WhatsApp credentials',
            'is_active' => true,
            'rotation_interval_days' => 90,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.credentials.index'));
        $response->assertStatus(200);
        $response->assertSee('WhatsApp Production');
        $response->assertSee('meta_cloud');
    }

    public function test_super_admin_can_view_credential_details(): void
    {
        $admin = User::query()->create([
            'business_id' => null,
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'role' => 'super_admin',
        ]);

        $business = Business::query()->create([
            'name' => 'Clinic',
            'business_type' => 'clinic',
            'slug' => 'clinic',
            'timezone' => 'UTC',
            'locale' => 'en',
            'channel_config' => [],
            'integration_config' => [],
            'reminder_settings' => [],
            'ai_config' => [],
            'is_active' => true,
            'plan' => 'trial',
        ]);

        $messagingConnection = MessagingChannelConnection::query()->create([
            'business_id' => $business->id,
            'channel' => 'whatsapp',
            'provider' => 'meta_cloud',
            'status' => 'connected',
            'credentials' => ['api_key' => 'test-key'],
            'runtime_config' => ['phone_number_id' => '123'],
        ]);

        CredentialMetadata::query()->create([
            'source_type' => 'messaging_connection',
            'source_id' => $messagingConnection->id,
            'provider' => 'meta_cloud',
            'key_name' => 'WhatsApp Production',
            'description' => 'Production WhatsApp credentials',
            'is_active' => true,
            'rotation_interval_days' => 90,
            'last_verified_at' => now()->subDays(30),
            'last_verification_status' => 'pass',
            'last_verification_message' => 'Credentials verified successfully',
        ]);

        $credential = CredentialMetadata::first();
        $response = $this->actingAs($admin)->get(route('admin.credentials.show', ['messaging_connection', $messagingConnection->id]));
        $response->assertStatus(200);
        $response->assertSee('WhatsApp Production');
        $response->assertSee('meta_cloud');
        $response->assertSee('Verification');
        $response->assertSee('Pass');
    }

    public function test_super_admin_can_record_credential_rotation(): void
    {
        $admin = User::query()->create([
            'business_id' => null,
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'role' => 'super_admin',
        ]);

        $business = Business::query()->create([
            'name' => 'Clinic',
            'business_type' => 'clinic',
            'slug' => 'clinic',
            'timezone' => 'UTC',
            'locale' => 'en',
            'channel_config' => [],
            'integration_config' => [],
            'reminder_settings' => [],
            'ai_config' => [],
            'is_active' => true,
            'plan' => 'trial',
        ]);

        $messagingConnection = MessagingChannelConnection::query()->create([
            'business_id' => $business->id,
            'channel' => 'whatsapp',
            'provider' => 'meta_cloud',
            'status' => 'connected',
            'credentials' => ['api_key' => 'test-key'],
            'runtime_config' => ['phone_number_id' => '123'],
        ]);

        CredentialMetadata::query()->create([
            'source_type' => 'messaging_connection',
            'source_id' => $messagingConnection->id,
            'provider' => 'meta_cloud',
            'key_name' => 'WhatsApp Production',
            'description' => 'Production WhatsApp credentials',
            'is_active' => true,
            'rotation_interval_days' => 90,
        ]);

        $credential = CredentialMetadata::first();

        $this->actingAs($admin)
            ->post(route('admin.credentials.record-rotation', ['messaging_connection', $messagingConnection->id]), [
                'credential_id' => $credential->id,
            ])
            ->assertRedirect(route('admin.credentials.show', ['messaging_connection', $messagingConnection->id]));

        $this->assertNotNull($credential->fresh()->last_rotated_at);
    }

    public function test_super_admin_can_update_credential_settings(): void
    {
        $admin = User::query()->create([
            'business_id' => null,
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'role' => 'super_admin',
        ]);

        $business = Business::query()->create([
            'name' => 'Clinic',
            'business_type' => 'clinic',
            'slug' => 'clinic',
            'timezone' => 'UTC',
            'locale' => 'en',
            'channel_config' => [],
            'integration_config' => [],
            'reminder_settings' => [],
            'ai_config' => [],
            'is_active' => true,
            'plan' => 'trial',
        ]);

        $messagingConnection = MessagingChannelConnection::query()->create([
            'business_id' => $business->id,
            'channel' => 'whatsapp',
            'provider' => 'meta_cloud',
            'status' => 'connected',
            'credentials' => ['api_key' => 'test-key'],
            'runtime_config' => ['phone_number_id' => '123'],
        ]);

        CredentialMetadata::query()->create([
            'source_type' => 'messaging_connection',
            'source_id' => $messagingConnection->id,
            'provider' => 'meta_cloud',
            'key_name' => 'WhatsApp Production',
            'description' => 'Production WhatsApp credentials',
            'is_active' => true,
            'rotation_interval_days' => 90,
        ]);

        $credential = CredentialMetadata::first();

        $this->actingAs($admin)
            ->patch(route('admin.credentials.update-settings', $credential), [
                'key_name' => 'WhatsApp Production Updated',
                'description' => 'Updated description',
                'rotation_interval_days' => '180',
            ])
            ->assertRedirect(route('admin.credentials.show', ['messaging_connection', $messagingConnection->id]));

        $this->assertDatabaseHas('credential_metadata', [
            'id' => $credential->id,
            'key_name' => 'WhatsApp Production Updated',
            'description' => 'Updated description',
            'rotation_interval_days' => 180,
        ]);
    }

    public function test_super_admin_can_record_credential_verification(): void
    {
        $admin = User::query()->create([
            'business_id' => null,
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'role' => 'super_admin',
        ]);

        $business = Business::query()->create([
            'name' => 'Clinic',
            'business_type' => 'clinic',
            'slug' => 'clinic',
            'timezone' => 'UTC',
            'locale' => 'en',
            'channel_config' => [],
            'integration_config' => [],
            'reminder_settings' => [],
            'ai_config' => [],
            'is_active' => true,
            'plan' => 'trial',
        ]);

        $messagingConnection = MessagingChannelConnection::query()->create([
            'business_id' => $business->id,
            'channel' => 'whatsapp',
            'provider' => 'meta_cloud',
            'status' => 'connected',
            'credentials' => ['api_key' => 'test-key'],
            'runtime_config' => ['phone_number_id' => '123'],
        ]);

        CredentialMetadata::query()->create([
            'source_type' => 'messaging_connection',
            'source_id' => $messagingConnection->id,
            'provider' => 'meta_cloud',
            'key_name' => 'WhatsApp Production',
            'description' => 'Production WhatsApp credentials',
            'is_active' => true,
            'rotation_interval_days' => 90,
        ]);

        $credential = CredentialMetadata::first();

        $this->actingAs($admin)
            ->post(route('admin.credentials.record-verification', $credential), [
                'status' => 'pass',
                'message' => 'Credentials verified successfully',
            ])
            ->assertRedirect(route('admin.credentials.show', ['messaging_connection', $messagingConnection->id]));

        $credential->refresh();
        $this->assertNotNull($credential->last_verified_at);
        $this->assertSame('pass', $credential->last_verification_status);
        $this->assertSame('Credentials verified successfully', $credential->last_verification_message);
    }

    public function test_super_admin_can_deactivate_credential(): void
    {
        $admin = User::query()->create([
            'business_id' => null,
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'role' => 'super_admin',
        ]);

        $business = Business::query()->create([
            'name' => 'Clinic',
            'business_type' => 'clinic',
            'slug' => 'clinic',
            'timezone' => 'UTC',
            'locale' => 'en',
            'channel_config' => [],
            'integration_config' => [],
            'reminder_settings' => [],
            'ai_config' => [],
            'is_active' => true,
            'plan' => 'trial',
        ]);

        $messagingConnection = MessagingChannelConnection::query()->create([
            'business_id' => $business->id,
            'channel' => 'whatsapp',
            'provider' => 'meta_cloud',
            'status' => 'connected',
            'credentials' => ['api_key' => 'test-key'],
            'runtime_config' => ['phone_number_id' => '123'],
        ]);

        CredentialMetadata::query()->create([
            'source_type' => 'messaging_connection',
            'source_id' => $messagingConnection->id,
            'provider' => 'meta_cloud',
            'key_name' => 'WhatsApp Production',
            'description' => 'Production WhatsApp credentials',
            'is_active' => true,
            'rotation_interval_days' => 90,
        ]);

        $credential = CredentialMetadata::first();

        $this->actingAs($admin)
            ->post(route('admin.credentials.deactivate', $credential), [
                'reason' => 'Credential rotated and no longer active.',
            ])
            ->assertRedirect(route('admin.credentials.index'));

        $this->assertDatabaseHas('credential_metadata', [
            'id' => $credential->id,
            'is_active' => false,
        ]);
    }

    public function test_super_admin_can_create_credential_metadata_for_messaging_connection(): void
    {
        $admin = User::query()->create([
            'business_id' => null,
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'role' => 'super_admin',
        ]);

        $business = Business::query()->create([
            'name' => 'Clinic',
            'business_type' => 'clinic',
            'slug' => 'clinic',
            'timezone' => 'UTC',
            'locale' => 'en',
            'channel_config' => [],
            'integration_config' => [],
            'reminder_settings' => [],
            'ai_config' => [],
            'is_active' => true,
            'plan' => 'trial',
        ]);

        $messagingConnection = MessagingChannelConnection::query()->create([
            'business_id' => $business->id,
            'channel' => 'whatsapp',
            'provider' => 'meta_cloud',
            'status' => 'connected',
            'credentials' => ['api_key' => 'test-key'],
            'runtime_config' => ['phone_number_id' => '123'],
        ]);

        $response = $this->actingAs($admin)->get(route('admin.credentials.index'));
        $response->assertStatus(200);
    }

    public function test_non_super_admin_cannot_access_credentials_metadata(): void
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
            'ai_config' => [],
            'is_active' => true,
            'plan' => 'trial',
        ]);

        $messagingConnection = MessagingChannelConnection::query()->create([
            'business_id' => $business->id,
            'channel' => 'whatsapp',
            'provider' => 'meta_cloud',
            'status' => 'connected',
            'credentials' => ['api_key' => 'test-key'],
            'runtime_config' => ['phone_number_id' => '123'],
        ]);

        CredentialMetadata::query()->create([
            'source_type' => 'messaging_connection',
            'source_id' => $messagingConnection->id,
            'provider' => 'meta_cloud',
            'key_name' => 'WhatsApp Production',
            'description' => 'Production WhatsApp credentials',
            'is_active' => true,
            'rotation_interval_days' => 90,
        ]);

        $owner = User::query()->create([
            'business_id' => $business->id,
            'name' => 'Owner',
            'email' => 'owner@example.com',
            'password' => bcrypt('password'),
            'role' => 'business_owner',
        ]);

        $this->actingAs($owner)->get(route('admin.credentials.index'))->assertForbidden();
        $this->actingAs($owner)->get(route('admin.credentials.show', ['messaging_connection', $messagingConnection->id]))->assertForbidden();
        $this->actingAs($owner)->patch(route('admin.credentials.update-settings', CredentialMetadata::first()))->assertForbidden();
    }
}
