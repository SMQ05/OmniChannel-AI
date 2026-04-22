<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IntegrationSecretStorageTest extends TestCase
{
    use RefreshDatabase;

    public function test_integration_settings_store_google_client_secret_in_encrypted_storage_only(): void
    {
        [$business, $owner] = $this->makeManagedBusinessUser();

        $this->actingAs($owner)
            ->withSession(['impersonating_as' => 'super_admin'])
            ->post(route('settings.integrations.update'), [
                'google_credentials' => [
                    'client_id' => 'client-id.apps.googleusercontent.com',
                    'client_secret' => 'super-secret',
                ],
                'google_calendar' => [
                    'enabled' => '1',
                    'calendar_id' => 'primary',
                ],
                'google_sheets' => [
                    'enabled' => '1',
                    'spreadsheet_id' => 'sheet-123',
                    'sheet_name' => 'Appointments',
                ],
            ])
            ->assertRedirect(route('settings.integrations'));

        $business->refresh();

        $this->assertSame('client-id.apps.googleusercontent.com', $business->integration_config['google_credentials']['client_id']);
        $this->assertArrayNotHasKey('client_secret', $business->integration_config['google_credentials']);
        $this->assertArrayNotHasKey('token', $business->integration_config['google_calendar']);
        $this->assertArrayNotHasKey('token', $business->integration_config['google_sheets']);
        $this->assertSame('super-secret', $business->googleOauthCredentials()['client_secret']);

        $rawSecrets = (string) DB::table('businesses')->where('id', $business->id)->value('integration_secrets');
        $this->assertNotSame('', $rawSecrets);
        $this->assertStringNotContainsString('super-secret', $rawSecrets);
    }

    public function test_oauth_callback_persists_google_tokens_to_encrypted_storage_only(): void
    {
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'access-token-123',
                'refresh_token' => 'refresh-token-456',
                'expires_in' => 3600,
            ], 200),
        ]);

        [$business, $owner] = $this->makeManagedBusinessUser([
            'integration_config' => [
                'google_credentials' => ['client_id' => 'client-id.apps.googleusercontent.com'],
                'google_calendar' => ['enabled' => false, 'calendar_id' => 'primary'],
            ],
            'integration_secrets' => [
                'google_credentials' => ['client_secret' => 'super-secret'],
            ],
        ]);

        $this->actingAs($owner)
            ->withSession([
                'impersonating_as' => 'super_admin',
                'oauth_service' => 'google_calendar',
                'oauth_business_id' => $business->id,
            ])
            ->get(route('settings.integrations.oauth.callback', ['code' => 'oauth-code']))
            ->assertRedirect(route('settings.integrations'));

        $business->refresh();

        $this->assertTrue((bool) $business->integration_config['google_calendar']['enabled']);
        $this->assertArrayNotHasKey('token', $business->integration_config['google_calendar']);
        $this->assertSame('access-token-123', $business->googleServiceToken('google_calendar')['access_token']);
        $this->assertSame('refresh-token-456', $business->googleServiceToken('google_calendar')['refresh_token']);

        $rawSecrets = (string) DB::table('businesses')->where('id', $business->id)->value('integration_secrets');
        $this->assertStringNotContainsString('access-token-123', $rawSecrets);
        $this->assertStringNotContainsString('refresh-token-456', $rawSecrets);
    }

    public function test_integration_settings_page_does_not_render_stored_google_secrets(): void
    {
        [$business, $owner] = $this->makeManagedBusinessUser([
            'integration_config' => [
                'google_credentials' => ['client_id' => 'client-id.apps.googleusercontent.com'],
                'google_calendar' => ['enabled' => true, 'calendar_id' => 'primary'],
                'google_sheets' => ['enabled' => true, 'spreadsheet_id' => 'sheet-123', 'sheet_name' => 'Appointments'],
            ],
            'integration_secrets' => [
                'google_credentials' => ['client_secret' => 'super-secret'],
                'google_calendar' => [
                    'token' => [
                        'access_token' => 'access-token-123',
                        'refresh_token' => 'refresh-token-456',
                        'expires_at' => time() + 3600,
                    ],
                ],
            ],
        ]);

        $this->actingAs($owner)
            ->withSession(['impersonating_as' => 'super_admin'])
            ->get(route('settings.integrations'))
            ->assertOk()
            ->assertSee('client-id.apps.googleusercontent.com')
            ->assertSee('Stored securely. Leave blank to keep the current secret.')
            ->assertDontSee('super-secret')
            ->assertDontSee('access-token-123')
            ->assertDontSee('refresh-token-456');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array{0: Business, 1: User}
     */
    private function makeManagedBusinessUser(array $overrides = []): array
    {
        $business = Business::query()->create(array_merge([
            'name' => 'Managed Clinic',
            'business_type' => 'clinic',
            'slug' => 'managed-clinic-' . str()->lower(str()->random(6)),
            'timezone' => 'UTC',
            'locale' => 'en',
            'channel_config' => [],
            'integration_config' => [],
            'integration_secrets' => [],
            'reminder_settings' => [],
            'ai_config' => [],
            'operations_config' => [],
            'is_active' => true,
            'plan' => 'pro',
        ], $overrides));

        $user = User::query()->create([
            'business_id' => $business->id,
            'name' => 'Owner',
            'email' => 'owner+' . str()->lower(str()->random(6)) . '@example.com',
            'password' => bcrypt('password'),
            'role' => 'business_owner',
        ]);

        return [$business, $user];
    }
}
