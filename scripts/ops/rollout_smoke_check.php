<?php

declare(strict_types=1);

use App\Models\Business;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;

require __DIR__ . '/../../vendor/autoload.php';

$dbFile = '/tmp/rollout_smoke_' . date('Ymd_His') . '.sqlite';

touch($dbFile);

putenv("DB_CONNECTION=sqlite");
putenv("DB_DATABASE={$dbFile}");
$_ENV['DB_CONNECTION'] = 'sqlite';
$_ENV['DB_DATABASE'] = $dbFile;
$_SERVER['DB_CONNECTION'] = 'sqlite';
$_SERVER['DB_DATABASE'] = $dbFile;

$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$migrationsDir = realpath(__DIR__ . '/../../database/migrations');
$targetMigration = '2026_04_22_000001_encrypt_legacy_business_integration_secrets.php';

if ($migrationsDir === false) {
    fwrite(STDERR, "Unable to resolve migrations directory.\n");
    exit(1);
}

$migrationFiles = array_values(array_filter(scandir($migrationsDir) ?: [], static function (string $file) use ($targetMigration): bool {
    return str_ends_with($file, '.php') && $file !== $targetMigration;
}));
sort($migrationFiles);

foreach ($migrationFiles as $file) {
    $status = Artisan::call('migrate', [
        '--force' => true,
        '--path' => 'database/migrations/' . $file,
    ]);

    if ($status !== 0) {
        fwrite(STDERR, Artisan::output());
        exit($status);
    }
}

Business::query()->create([
    'name' => 'Rollout Smoke Clinic',
    'business_type' => 'clinic',
    'slug' => 'rollout-smoke-clinic',
    'timezone' => 'UTC',
    'locale' => 'en',
    'channel_config' => [
        'whatsapp' => [
            'enabled' => true,
            'provider' => 'meta_cloud',
            'phone_number_id' => 'phone-123',
            'access_token' => 'legacy-meta-token',
            'verify_token' => 'legacy-verify',
            'app_secret' => 'legacy-app-secret',
        ],
        'messenger' => [
            'enabled' => true,
            'page_id' => 'page-123',
            'access_token' => 'legacy-page-token',
            'verify_token' => 'legacy-page-verify',
            'app_secret' => 'legacy-page-secret',
        ],
        'voice' => [
            'enabled' => true,
            'llm_provider' => 'openai',
        ],
    ],
    'integration_config' => [
        'google_credentials' => [
            'client_id' => 'client-id.apps.googleusercontent.com',
            'client_secret' => 'legacy-google-secret',
        ],
        'google_calendar' => [
            'enabled' => true,
            'calendar_id' => 'primary',
            'token' => [
                'access_token' => 'legacy-calendar-access',
                'refresh_token' => 'legacy-calendar-refresh',
                'expires_at' => 1893456000,
            ],
        ],
        'google_sheets' => [
            'enabled' => true,
            'spreadsheet_id' => 'sheet-123',
            'sheet_name' => 'Appointments',
            'token' => [
                'access_token' => 'legacy-sheets-access',
                'refresh_token' => 'legacy-sheets-refresh',
                'expires_at' => 1893456000,
            ],
        ],
    ],
    'reminder_settings' => [],
    'ai_config' => [],
    'operations_config' => [],
    'is_active' => true,
    'plan' => 'trial',
]);

$targetPath = $migrationsDir . DIRECTORY_SEPARATOR . $targetMigration;
$migration = require $targetPath;
$migration->up();

$business = Business::query()->with(['messagingConnections'])->firstOrFail();

echo json_encode([
    'db_file' => $dbFile,
    'business' => $business->name,
    'google_client_id' => $business->integration_config['google_credentials']['client_id'] ?? null,
    'google_client_secret_scrubbed' => !array_key_exists('client_secret', $business->integration_config['google_credentials'] ?? []),
    'google_calendar_token_scrubbed' => !array_key_exists('token', $business->integration_config['google_calendar'] ?? []),
    'google_sheets_token_scrubbed' => !array_key_exists('token', $business->integration_config['google_sheets'] ?? []),
    'integration_secrets_present' => ($business->integration_secrets ?? []) !== [],
    'whatsapp_legacy_keys' => array_keys($business->channel_config['whatsapp'] ?? []),
    'messenger_legacy_keys' => array_keys($business->channel_config['messenger'] ?? []),
    'voice_legacy_keys' => array_keys($business->channel_config['voice'] ?? []),
    'connections' => $business->messagingConnections->map(static fn ($connection): array => [
        'channel' => $connection->channel,
        'provider' => $connection->provider,
        'status' => $connection->status,
        'credential_keys' => array_keys($connection->credentials ?? []),
        'runtime_keys' => array_keys($connection->runtime_config ?? []),
    ])->all(),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
