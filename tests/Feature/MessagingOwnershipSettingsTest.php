<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Business;
use App\Models\BusinessMessagingChannel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MessagingOwnershipSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_business_owner_can_update_reminder_rules(): void
    {
        [$business, $owner] = $this->makeBusinessUser([
            'reminder_settings' => ['reminders' => []],
        ]);

        $this->actingAs($owner)
            ->post(route('settings.reminders.update'), [
                'reminders' => [
                    [
                        'offset_hours' => 24,
                        'label' => 'Day before',
                        'message_template' => 'Reminder for {patient_name} and {service_type}',
                    ],
                ],
            ])
            ->assertRedirect(route('settings.reminders'));

        $business->refresh();

        $this->assertCount(1, $business->reminder_settings['reminders']);
        $this->assertSame('Day before', $business->reminder_settings['reminders'][0]['label']);
    }

    public function test_business_owner_can_toggle_connected_channels_but_not_edit_platform_credentials(): void
    {
        [$business, $owner] = $this->makeBusinessUser([
            'channel_config' => [
                'whatsapp' => [
                    'provider' => 'meta_cloud',
                    'phone_number_id' => 'pnid-1',
                    'access_token' => 'token-1',
                    'verify_token' => 'verify-1',
                    'app_secret' => 'secret-1',
                    'enabled' => false,
                ],
                'messenger' => [
                    'page_id' => 'page-1',
                    'access_token' => 'token-2',
                    'verify_token' => 'verify-2',
                    'app_secret' => 'secret-2',
                    'enabled' => false,
                ],
            ],
        ]);

        $this->actingAs($owner)
            ->post(route('settings.channels.update'), [
                'whatsapp' => ['enabled' => '1'],
                'messenger' => ['enabled' => '1'],
            ])
            ->assertRedirect(route('settings.channels'));

        $business->refresh();

        $this->assertDatabaseHas('business_messaging_channels', [
            'business_id' => $business->id,
            'channel' => 'whatsapp',
            'is_enabled' => true,
        ]);
        $this->assertDatabaseHas('business_messaging_channels', [
            'business_id' => $business->id,
            'channel' => 'messenger',
            'is_enabled' => true,
        ]);
        $this->assertFalse((bool) $business->fresh()->channel_config['whatsapp']['enabled']);
        $this->assertFalse((bool) $business->fresh()->channel_config['messenger']['enabled']);

        $this->actingAs($owner)
            ->patch(route('admin.businesses.messaging.update', [$business, 'whatsapp']), ['provider' => 'meta_cloud'])
            ->assertForbidden();
    }

    public function test_channel_cannot_be_enabled_without_platform_connection(): void
    {
        [, $owner] = $this->makeBusinessUser([
            'channel_config' => [
                'whatsapp' => ['provider' => 'meta_cloud', 'enabled' => false],
                'messenger' => ['enabled' => false],
            ],
        ]);

        $this->actingAs($owner)
            ->from(route('settings.channels'))
            ->post(route('settings.channels.update'), [
                'whatsapp' => ['enabled' => '1'],
                'messenger' => ['enabled' => '0'],
            ])
            ->assertRedirect(route('settings.channels'))
            ->assertSessionHasErrors('whatsapp.enabled');
    }

    public function test_super_admin_can_update_platform_channel_credentials_without_writing_legacy_blob(): void
    {
        [$business] = $this->makeBusinessUser([
            'channel_config' => [
                'whatsapp' => ['provider' => 'meta_cloud', 'enabled' => false],
                'messenger' => ['enabled' => false],
            ],
        ]);

        $admin = User::query()->create([
            'business_id' => null,
            'name' => 'Admin',
            'email' => 'admin+' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
            'role' => 'super_admin',
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.businesses.messaging.update', [$business, 'whatsapp']), [
                'provider' => 'meta_cloud',
                'phone_number_id' => 'pnid-1',
                'access_token' => 'token-1',
                'verify_token' => 'verify-1',
                'app_secret' => 'secret-1',
            ])
            ->assertRedirect(route('admin.businesses.index'));

        $this->actingAs($admin)
            ->patch(route('admin.businesses.messaging.update', [$business, 'messenger']), [
                'page_id' => 'page-1',
                'access_token' => 'token-2',
                'verify_token' => 'verify-2',
                'app_secret' => 'secret-2',
            ])
            ->assertRedirect(route('admin.businesses.index'));

        $business->refresh();

        $this->assertDatabaseHas('messaging_channel_connections', [
            'business_id' => $business->id,
            'channel' => 'whatsapp',
            'provider' => 'meta_cloud',
            'status' => 'connected',
        ]);
        $this->assertDatabaseHas('messaging_channel_connections', [
            'business_id' => $business->id,
            'channel' => 'messenger',
            'provider' => 'meta',
            'status' => 'connected',
        ]);
        $this->assertArrayNotHasKey('phone_number_id', $business->fresh()->channel_config['whatsapp']);
        $this->assertArrayNotHasKey('page_id', $business->fresh()->channel_config['messenger']);
    }

    public function test_live_channel_disable_requires_confirmation_and_is_audit_logged(): void
    {
        [$business, $owner] = $this->makeBusinessUser([
            'channel_config' => [
                'whatsapp' => [
                    'provider' => 'meta_cloud',
                    'phone_number_id' => 'pnid-1',
                    'access_token' => 'token-1',
                    'verify_token' => 'verify-1',
                    'app_secret' => 'secret-1',
                    'enabled' => true,
                ],
            ],
        ]);

        BusinessMessagingChannel::query()->create([
            'business_id' => $business->id,
            'channel' => 'whatsapp',
            'is_enabled' => true,
            'approved_at' => now(),
            'enabled_at' => now(),
        ]);

        $this->actingAs($owner)
            ->from(route('settings.channels'))
            ->post(route('settings.channels.update'), [
                'whatsapp' => ['enabled' => '0'],
            ])
            ->assertRedirect(route('settings.channels'))
            ->assertSessionHasErrors('confirm_disable.whatsapp');

        $this->actingAs($owner)
            ->post(route('settings.channels.update'), [
                'confirm_disable' => ['whatsapp' => '1'],
                'disable_reason' => ['whatsapp' => 'Clinic requested pause'],
            ])
            ->assertRedirect(route('settings.channels'));

        $this->assertDatabaseHas('business_messaging_channels', [
            'business_id' => $business->id,
            'channel' => 'whatsapp',
            'is_enabled' => false,
            'disable_reason' => 'Clinic requested pause',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'business_id' => $business->id,
            'action' => 'messaging.channel_disabled',
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array{0: Business, 1: User}
     */
    private function makeBusinessUser(array $overrides = []): array
    {
        $business = Business::query()->create(array_merge([
            'name' => 'Clinic',
            'business_type' => 'clinic',
            'slug' => 'clinic',
            'timezone' => 'UTC',
            'locale' => 'en',
            'channel_config' => [],
            'integration_config' => [],
            'reminder_settings' => [],
            'ai_config' => [],
            'operations_config' => [],
            'is_active' => true,
            'plan' => 'pro',
        ], $overrides));

        $user = User::query()->create([
            'business_id' => $business->id,
            'name' => 'Owner',
            'email' => uniqid('owner', true) . '@example.com',
            'password' => bcrypt('password'),
            'role' => 'business_owner',
        ]);

        return [$business, $user];
    }
}
