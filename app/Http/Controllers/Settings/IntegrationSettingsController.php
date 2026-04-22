<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Services\Google\GoogleCalendarService;
use App\Services\Google\GoogleSheetsService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * Manages Google Calendar and Google Sheets integration settings (/settings/integrations).
 *
 * Handles OAuth authorization code flow:
 *  1. oauthRedirect() — builds the Google OAuth consent URL and redirects the user.
 *  2. oauthCallback() — exchanges the auth code for tokens and persists them to encrypted storage.
 *
 * Test endpoints create then immediately delete/undo a test record to verify connectivity.
 */
class IntegrationSettingsController extends Controller
{
    private const GOOGLE_AUTH_URL  = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const GOOGLE_TOKEN_URL = 'https://oauth2.googleapis.com/token';

    /** Google OAuth scopes required for Calendar + Sheets. */
    private const SCOPES = [
        'https://www.googleapis.com/auth/calendar',
        'https://www.googleapis.com/auth/spreadsheets',
    ];

    /**
     * Render the integration settings page.
     *
     * @param  Request  $request
     * @return View
     */
    public function edit(Request $request): View
    {
        $business = $request->user()->business;
        $integrationConfig = $business->integration_config ?? [];

        return view('settings.integrations', [
            'business' => $business,
            'integrationConfig' => $integrationConfig,
            'googleCredentials' => [
                'client_id' => $business->googleOauthClientId(),
                'client_secret_configured' => trim((string) ($business->googleOauthCredentials()['client_secret'] ?? '')) !== '',
            ],
            'integrationStatus' => [
                'google_calendar' => ['connected' => $business->googleServiceConnected('google_calendar')],
                'google_sheets' => ['connected' => $business->googleServiceConnected('google_sheets')],
            ],
            'managedByAdmin' => $this->managedByAdmin($request),
        ]);
    }

    /**
     * Save integration toggles, manual config fields, and OAuth credentials.
     *
     * @param  Request  $request
     * @return RedirectResponse
     */
    public function update(Request $request): RedirectResponse
    {
        $this->ensureManagedByAdmin($request);

        $validated = $request->validate([
            'google_credentials.client_id'     => ['nullable', 'string', 'max:500'],
            'google_credentials.client_secret' => ['nullable', 'string', 'max:500'],
            'google_calendar.enabled'          => ['boolean'],
            'google_calendar.calendar_id'      => ['nullable', 'string', 'max:255'],
            'google_sheets.enabled'            => ['boolean'],
            'google_sheets.spreadsheet_id'     => ['nullable', 'string', 'max:255'],
            'google_sheets.sheet_name'         => ['nullable', 'string', 'max:255'],
        ]);

        $business = $request->user()->business;
        $existing = $business->integration_config ?? [];
        $secrets = $business->integration_secrets ?? [];

        if (isset($validated['google_credentials'])) {
            $clientId = trim((string) ($validated['google_credentials']['client_id'] ?? ''));
            $clientSecret = trim((string) ($validated['google_credentials']['client_secret'] ?? ''));

            if ($clientId !== '') {
                $existing['google_credentials'] = array_filter([
                    'client_id' => $clientId,
                ], static fn (mixed $value): bool => is_string($value) ? trim($value) !== '' : filled($value));
            }

            if ($clientSecret !== '') {
                $googleCredentials = $secrets['google_credentials'] ?? [];
                $googleCredentials['client_secret'] = $clientSecret;
                $secrets['google_credentials'] = $googleCredentials;
            }
        }

        foreach (['google_calendar', 'google_sheets'] as $service) {
            if (isset($validated[$service])) {
                unset($existing[$service]['token']);
                $existing[$service] = array_merge($existing[$service] ?? [], $validated[$service]);
            }
        }

        unset($existing['google_credentials']['client_secret']);

        $business->integration_config = $existing;
        $business->integration_secrets = $secrets;
        $business->save();

        return redirect()->route('settings.integrations')->with('success', 'Integration settings saved.');
    }

    /**
     * Redirect the user to Google's OAuth consent screen.
     *
     * Stores the service ('google_calendar' or 'google_sheets') in the session
     * so the callback knows which service to update.
     *
     * @param  Request  $request
     * @param  string   $service  'google_calendar' or 'google_sheets'
     * @return RedirectResponse
     */
    public function oauthRedirect(Request $request, string $service): RedirectResponse
    {
        $this->ensureManagedByAdmin($request);

        abort_if(!in_array($service, ['google_calendar', 'google_sheets'], true), 400);

        $business = $request->user()->business;
        $clientId = $business->googleOauthClientId();

        if ($clientId === '') {
            return redirect()->route('settings.integrations')
                ->withErrors(['oauth' => 'No OAuth Client ID configured. Enter your Google Client ID in the credentials section below and save first.']);
        }

        $request->session()->put('oauth_service', $service);
        $request->session()->put('oauth_business_id', $business->id);

        $params = http_build_query([
            'client_id'     => $clientId,
            'redirect_uri'  => route('settings.integrations.oauth.callback'),
            'response_type' => 'code',
            'scope'         => implode(' ', self::SCOPES),
            'access_type'   => 'offline',
            'prompt'        => 'consent',
        ]);

        return redirect(self::GOOGLE_AUTH_URL . '?' . $params);
    }

    /**
     * Handle the OAuth callback from Google.
     *
     * Exchanges the authorization code for access + refresh tokens and persists
     * them to businesses.integration_secrets[$service]['token'].
     *
     * @param  Request  $request
     * @return RedirectResponse
     */
    public function oauthCallback(Request $request): RedirectResponse
    {
        $this->ensureManagedByAdmin($request);

        $service    = $request->session()->pull('oauth_service');
        $businessId = $request->session()->pull('oauth_business_id');
        $code       = $request->input('code', '');

        if (!$service || !$businessId || $code === '') {
            return redirect()->route('settings.integrations')
                ->withErrors(['oauth' => 'OAuth callback failed: missing state or code.']);
        }

        /** @var Business $business */
        $business = Business::findOrFail($businessId);
        $credentials = $business->googleOauthCredentials();

        try {
            $response = Http::asForm()->post(self::GOOGLE_TOKEN_URL, [
                'code'          => $code,
                'client_id'     => $credentials['client_id']     ?? '',
                'client_secret' => $credentials['client_secret'] ?? '',
                'redirect_uri'  => route('settings.integrations.oauth.callback'),
                'grant_type'    => 'authorization_code',
            ]);

            if ($response->failed()) {
                throw new \RuntimeException($response->body());
            }

            $tokenData = $response->json();

            $config = $business->integration_config ?? [];
            $secrets = $business->integration_secrets ?? [];
            $serviceSecrets = $secrets[$service] ?? [];
            $serviceSecrets['token'] = [
                'access_token'  => $tokenData['access_token']  ?? '',
                'refresh_token' => $tokenData['refresh_token'] ?? '',
                'expires_at'    => time() + (int) ($tokenData['expires_in'] ?? 3600),
            ];
            $secrets[$service] = $serviceSecrets;
            // Auto-enable the service now that it's connected
            $config[$service]['enabled'] = true;
            unset($config[$service]['token']);

            $business->integration_config = $config;
            $business->integration_secrets = $secrets;
            $business->save();
        } catch (\Throwable $e) {
            Log::error('IntegrationSettingsController: OAuth callback failed.', [
                'business_id' => $businessId,
                'service'     => $service,
                'error'       => $e->getMessage(),
            ]);

            return redirect()->route('settings.integrations')
                ->withErrors(['oauth' => 'Google authorization failed: ' . $e->getMessage()]);
        }

        $serviceLabel = match($service) {
            'google_calendar' => 'Google Calendar',
            'google_sheets'   => 'Google Sheets',
            default           => ucwords(str_replace('_', ' ', $service)),
        };

        $nextStep = match($service) {
            'google_calendar' => ' Now enter your Calendar ID below to complete setup.',
            'google_sheets'   => ' Now enter your Spreadsheet ID and Sheet Tab Name below to complete setup.',
            default           => '',
        };

        return redirect()->route('settings.integrations')
            ->with('success', $serviceLabel . ' connected successfully.' . $nextStep);
    }

    /**
     * Test a specific integration by creating and immediately deleting a test record.
     *
     * @param  Request                  $request
     * @param  string                   $service  'google_calendar' or 'google_sheets'
     * @param  GoogleCalendarService    $calendar
     * @param  GoogleSheetsService      $sheets
     * @return JsonResponse
     */
    public function test(
        Request $request,
        string $service,
        GoogleCalendarService $calendar,
        GoogleSheetsService $sheets,
    ): JsonResponse {
        $this->ensureManagedByAdmin($request);

        $business = $request->user()->business;

        try {
            if ($service === 'google_calendar') {
                $this->testCalendar($calendar, $business);
            } elseif ($service === 'google_sheets') {
                $this->testSheets($sheets, $business);
            } else {
                return response()->json(['success' => false, 'message' => 'Unknown service.']);
            }
        } catch (\Throwable $e) {
            Log::error("IntegrationSettingsController: test failed for {$service}.", [
                'business_id' => $business->id,
                'error'       => $e->getMessage(),
            ]);

            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }

        return response()->json(['success' => true, 'message' => 'Connection successful.']);
    }

    // -------------------------------------------------------------------------
    // Internal test helpers
    // -------------------------------------------------------------------------

    /**
     * Create a test calendar event, then immediately delete it.
     *
     * @param  GoogleCalendarService  $calendar
     * @param  \App\Models\Business   $business
     */
    private function testCalendar(GoogleCalendarService $calendar, \App\Models\Business $business): void
    {
        // Build a minimal fake appointment for the test event
        $fakeAppointment = new \App\Models\Appointment([
            'business_id'  => $business->id,
            'service_type' => 'Test Connection',
            'start_time'   => now()->addDays(30)->utc(),
            'end_time'     => now()->addDays(30)->addMinutes(30)->utc(),
            'status'       => 'confirmed',
            'booked_via'   => 'manual',
        ]);

        // Attach minimal relations without DB queries
        $fakeAppointment->setRelation('patient',  new \App\Models\Patient(['name' => 'Test Patient']));
        $fakeAppointment->setRelation('provider', new \App\Models\Provider(['name' => 'Test Provider']));

        $eventId = $calendar->createEvent($business, $fakeAppointment);
        $calendar->deleteEvent($business, $eventId);
    }

    /**
     * Append a test row to the sheet, then log the successful append.
     * (Google Sheets API does not support atomic delete of appended rows,
     * so we append and notify the user to manually clean up if needed.)
     *
     * @param  GoogleSheetsService   $sheets
     * @param  \App\Models\Business  $business
     */
    private function testSheets(GoogleSheetsService $sheets, \App\Models\Business $business): void
    {
        $fakeAppointment = new \App\Models\Appointment([
            'business_id'  => $business->id,
            'id'           => 0,
            'service_type' => 'TEST - PLEASE DELETE',
            'start_time'   => now()->addDays(30)->utc(),
            'end_time'     => now()->addDays(30)->addMinutes(30)->utc(),
            'status'       => 'confirmed',
            'booked_via'   => 'manual',
        ]);

        $fakeAppointment->setRelation('patient',  new \App\Models\Patient(['name' => 'TEST', 'phone' => '']));
        $fakeAppointment->setRelation('provider', new \App\Models\Provider(['name' => 'TEST']));

        $sheets->appendRow($business, $fakeAppointment);
    }

    private function managedByAdmin(Request $request): bool
    {
        return $request->session()->has('impersonating_as') || $request->user()?->role === 'super_admin';
    }

    private function ensureManagedByAdmin(Request $request): void
    {
        if (!$this->managedByAdmin($request)) {
            throw new AuthorizationException('Integrations are managed by Kynex Solutions.');
        }
    }
}
