<?php

declare(strict_types=1);

namespace App\Services\Google;

use App\Models\Appointment;
use App\Models\Business;
use Carbon\Carbon;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Manages Google Calendar events for appointment sync.
 *
 * All methods use the Google Calendar REST API v3. Authentication
 * is handled via GoogleOAuthService, which automatically refreshes
 * expired tokens before returning a valid access_token.
 *
 * Token refresh on 401:
 *  Every API call is wrapped in callWithTokenRefresh(), which:
 *   1. Calls the API with the current token.
 *   2. On 401, forces a hard refresh via GoogleOAuthService::refreshToken()
 *      and retries the call exactly once.
 *   3. On a second 401, throws \RuntimeException — this propagates to
 *      SyncAppointmentJob which retries via #[Backoff(120)].
 *
 * All timestamps sent to Google are in RFC 3339 format in the business
 * timezone. The Google API respects the timezone offset, so patient-facing
 * calendar events show the correct local time on any device.
 *
 * Error contract:
 *  - Non-2xx responses (including 429 Too Many Requests) throw \RuntimeException
 *    after logging the raw response body. Never silently swallowed.
 */
class GoogleCalendarService
{
    private const API_BASE = 'https://www.googleapis.com/calendar/v3';

    public function __construct(
        private readonly GoogleOAuthService $oauth,
    ) {}

    /**
     * Create a new Google Calendar event for the given appointment.
     *
     * @param  Business     $business     The tenant (provides calendarId + timezone)
     * @param  Appointment  $appointment  The confirmed appointment to sync
     * @return string                     The Google event ID (stored for future updates/deletes)
     *
     * @throws \RuntimeException On API failure, connection error, or auth failure
     */
    public function createEvent(Business $business, Appointment $appointment): string
    {
        $calendarId = $this->calendarId($business);
        $body       = $this->buildEventBody($business, $appointment);

        $response = $this->callWithTokenRefresh(
            business: $business,
            service: 'google_calendar',
            method: 'POST',
            url: self::API_BASE . '/calendars/' . urlencode($calendarId) . '/events',
            body: $body,
        );

        $eventId = (string) ($response['id'] ?? '');

        if ($eventId === '') {
            throw new \RuntimeException(
                'GoogleCalendarService: createEvent returned no event ID. '
                . 'Business #' . $business->id . ', Appointment #' . $appointment->id,
            );
        }

        Log::info('GoogleCalendarService: event created.', [
            'business_id'    => $business->id,
            'appointment_id' => $appointment->id,
            'event_id'       => $eventId,
        ]);

        return $eventId;
    }

    /**
     * Update an existing Google Calendar event to reflect a rescheduled
     * or modified appointment.
     *
     * @param  Business     $business        The tenant
     * @param  Appointment  $appointment     The updated appointment (must have google_event_id)
     * @param  string       $googleEventId   The existing Google event ID to update
     *
     * @throws \RuntimeException On API failure, connection error, or auth failure
     */
    public function updateEvent(
        Business $business,
        Appointment $appointment,
        string $googleEventId,
    ): void {
        $calendarId = $this->calendarId($business);
        $body       = $this->buildEventBody($business, $appointment);

        $this->callWithTokenRefresh(
            business: $business,
            service: 'google_calendar',
            method: 'PATCH',
            url: self::API_BASE . '/calendars/' . urlencode($calendarId)
                . '/events/' . urlencode($googleEventId),
            body: $body,
        );

        Log::info('GoogleCalendarService: event updated.', [
            'business_id'    => $business->id,
            'appointment_id' => $appointment->id,
            'event_id'       => $googleEventId,
        ]);
    }

    /**
     * Delete a Google Calendar event for a cancelled appointment.
     *
     * Uses DELETE with `sendUpdates=none` to suppress notification emails
     * — reminder notifications are handled by the reminder pipeline.
     *
     * @param  Business  $business       The tenant
     * @param  string    $googleEventId  The Google event ID to delete
     *
     * @throws \RuntimeException On API failure, connection error, or auth failure
     */
    public function deleteEvent(Business $business, string $googleEventId): void
    {
        $calendarId = $this->calendarId($business);

        $this->callWithTokenRefresh(
            business: $business,
            service: 'google_calendar',
            method: 'DELETE',
            url: self::API_BASE . '/calendars/' . urlencode($calendarId)
                . '/events/' . urlencode($googleEventId)
                . '?sendUpdates=none',
            body: [],
        );

        Log::info('GoogleCalendarService: event deleted.', [
            'business_id' => $business->id,
            'event_id'    => $googleEventId,
        ]);
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    /**
     * Build the Google Calendar event resource body from an Appointment.
     *
     * Times are formatted in RFC 3339 using the business timezone so the
     * event appears at the correct local time on any user's calendar —
     * regardless of whether they are in the same timezone.
     *
     * @param  Business     $business
     * @param  Appointment  $appointment
     * @return array<string, mixed>
     */
    private function buildEventBody(Business $business, Appointment $appointment): array
    {
        $timezone = $business->timezone;

        // Convert UTC DB timestamps to business local time for Calendar display
        $startLocal = Carbon::parse($appointment->start_time)->setTimezone($timezone);
        $endLocal   = Carbon::parse($appointment->end_time)->setTimezone($timezone);

        $appointment->loadMissing(['patient', 'provider']);
        $patientName  = $appointment->patient->name   ?? 'Patient';
        $providerName = $appointment->provider->displayName() ?? 'Provider';
        $serviceType  = $appointment->service_type;
        $businessName = $business->name;

        return [
            'summary'     => "{$serviceType} — {$patientName}",
            'description' => implode("\n", [
                "Patient: {$patientName}",
                "Provider: {$providerName}",
                "Service: {$serviceType}",
                "Business: {$businessName}",
                "Booked via: {$appointment->booked_via}",
            ]),
            'start' => [
                'dateTime' => $startLocal->toRfc3339String(),
                'timeZone' => $timezone,
            ],
            'end' => [
                'dateTime' => $endLocal->toRfc3339String(),
                'timeZone' => $timezone,
            ],
            'status' => $appointment->status === 'cancelled' ? 'cancelled' : 'confirmed',
        ];
    }

    /**
     * Execute an API call, retrying once after a forced token refresh on 401.
     *
     * Flow:
     *  1. Obtain a valid token via getValidToken() (may already refresh proactively).
     *  2. Make the HTTP call.
     *  3. On 401: force hard refresh, retry exactly once.
     *  4. On second 401 or any other non-2xx: log raw body, throw \RuntimeException.
     *  5. On DELETE 204 No Content: treat as success (Google returns 204 on delete).
     *
     * @param  Business              $business
     * @param  string                $service   'google_calendar'
     * @param  string                $method    HTTP verb: 'GET' | 'POST' | 'PATCH' | 'DELETE'
     * @param  string                $url       Full API URL
     * @param  array<string, mixed>  $body      Request body (empty array for DELETE)
     * @return array<string, mixed>             Decoded JSON response (empty array for 204)
     *
     * @throws \RuntimeException On connection failure, 401 after retry, or other non-2xx
     */
    private function callWithTokenRefresh(
        Business $business,
        string $service,
        string $method,
        string $url,
        array $body,
    ): array {
        $token = $this->oauth->getValidToken($business, $service);

        $response = $this->makeRequest($method, $url, $token, $body);

        // 204 No Content is a success (DELETE)
        if ($response->status() === 204) {
            return [];
        }

        // On 401: force refresh and retry exactly once
        if ($response->status() === 401) {
            Log::warning('GoogleCalendarService: 401 received, forcing token refresh.', [
                'business_id' => $business->id,
                'url'         => $url,
            ]);

            $token    = $this->oauth->refreshToken($business, $service);
            $response = $this->makeRequest($method, $url, $token, $body);

            if ($response->status() === 204) {
                return [];
            }
        }

        if ($response->failed()) {
            Log::error('GoogleCalendarService: API returned non-2xx response.', [
                'business_id' => $business->id,
                'method'      => $method,
                'url'         => $url,
                'status'      => $response->status(),
                'body'        => $response->body(),
            ]);

            throw new \RuntimeException(
                "GoogleCalendarService: {$method} {$url} failed "
                . "(HTTP {$response->status()}): {$response->body()}",
            );
        }

        return (array) $response->json();
    }

    /**
     * Make a single HTTP request to the Google Calendar API.
     *
     * @param  string                $method  HTTP verb
     * @param  string                $url     Full URL
     * @param  string                $token   Bearer access token
     * @param  array<string, mixed>  $body    Request body
     * @return \Illuminate\Http\Client\Response
     *
     * @throws \RuntimeException On connection error
     */
    private function makeRequest(
        string $method,
        string $url,
        string $token,
        array $body,
    ): \Illuminate\Http\Client\Response {
        try {
            $request = Http::withToken($token)
                ->acceptJson()
                ->timeout(20);

            return match (strtoupper($method)) {
                'POST'   => $request->post($url, $body),
                'PATCH'  => $request->patch($url, $body),
                'DELETE' => $request->delete($url),
                default  => $request->get($url),
            };
        } catch (ConnectionException $e) {
            Log::error('GoogleCalendarService: HTTP connection error.', [
                'url'   => $url,
                'error' => $e->getMessage(),
            ]);

            throw new \RuntimeException(
                "GoogleCalendarService: connection error for {$url}: {$e->getMessage()}",
                previous: $e,
            );
        }
    }

    /**
     * Extract and validate the Calendar ID from the business integration config.
     *
     * @param  Business  $business
     * @throws \RuntimeException When calendar_id is not configured
     */
    private function calendarId(Business $business): string
    {
        $calendarId = (string) (
            $business->integration_config['google_calendar']['calendar_id'] ?? ''
        );

        if ($calendarId === '') {
            throw new \RuntimeException(
                "GoogleCalendarService: no calendar_id configured for business #{$business->id}.",
            );
        }

        return $calendarId;
    }
}
