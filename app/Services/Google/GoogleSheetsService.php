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
 * Manages Google Sheets row sync for appointment records.
 *
 * Column order (1-indexed, matches spec):
 *  A: ID | B: Patient | C: Phone | D: Provider | E: Service |
 *  F: Date | G: Time | H: Status | I: Channel | J: Booked At | K: Last Updated
 *
 * Each appointment is stored as one row. On rescheduling or cancellation,
 * updateRow() locates the existing row by Appointment ID in column A and
 * overwrites it via batchUpdate.
 *
 * Token refresh on 401:
 *  All API calls are wrapped in callWithTokenRefresh(), identical in
 *  behaviour to GoogleCalendarService — one forced refresh + retry on
 *  401, then \RuntimeException to allow job retry via #[Backoff(120)].
 *
 * Error contract:
 *  Non-2xx responses (including 429 Too Many Requests) throw
 *  \RuntimeException after logging. Never silently swallowed.
 */
class GoogleSheetsService
{
    private const API_BASE = 'https://sheets.googleapis.com/v4/spreadsheets';

    public function __construct(
        private readonly GoogleOAuthService $oauth,
    ) {}

    /**
     * Append a new row to the configured Google Sheet for a new appointment.
     *
     * Uses the Sheets API `values:append` endpoint with
     * `valueInputOption=USER_ENTERED` so dates and times render correctly
     * without requiring the caller to pre-format cell values.
     *
     * @param  Business     $business     The tenant (provides spreadsheetId + sheetName)
     * @param  Appointment  $appointment  The appointment to append
     *
     * @throws \RuntimeException On API failure, connection error, or auth failure
     */
    public function appendRow(Business $business, Appointment $appointment): void
    {
        $spreadsheetId = $this->spreadsheetId($business);
        $sheetName     = $this->sheetName($business);
        $row           = $this->buildRow($business, $appointment);

        $url = self::API_BASE . '/' . urlencode($spreadsheetId)
            . '/values/' . urlencode($sheetName)
            . '!A1:append?valueInputOption=USER_ENTERED&insertDataOption=INSERT_ROWS';

        $this->callWithTokenRefresh(
            business: $business,
            service: 'google_sheets',
            method: 'POST',
            url: $url,
            body: ['values' => [$row]],
        );

        Log::info('GoogleSheetsService: row appended.', [
            'business_id'    => $business->id,
            'appointment_id' => $appointment->id,
        ]);
    }

    /**
     * Update the existing row for the given appointment.
     *
     * Searches column A of the sheet for a cell matching the appointment
     * ID, then overwrites that entire row with fresh data.
     *
     * If no matching row is found (e.g. the initial append failed), this
     * method falls back to appending a new row so the record is never lost.
     *
     * @param  Business     $business     The tenant
     * @param  Appointment  $appointment  The appointment to update (must already exist in sheet)
     *
     * @throws \RuntimeException On API failure, connection error, or auth failure
     */
    public function updateRow(Business $business, Appointment $appointment): void
    {
        $rowIndex = $this->findRowIndex($business, $appointment->id);

        if ($rowIndex === null) {
            // Row not found — fall back to append so the record isn't lost
            Log::warning('GoogleSheetsService: row not found for update, appending instead.', [
                'business_id'    => $business->id,
                'appointment_id' => $appointment->id,
            ]);

            $this->appendRow($business, $appointment);

            return;
        }

        $spreadsheetId = $this->spreadsheetId($business);
        $sheetName     = $this->sheetName($business);
        $row           = $this->buildRow($business, $appointment);

        // Sheets API rows are 1-indexed; $rowIndex is already 1-based
        $range = $sheetName . '!A' . $rowIndex . ':K' . $rowIndex;

        $url = self::API_BASE . '/' . urlencode($spreadsheetId)
            . '/values/' . urlencode($range)
            . '?valueInputOption=USER_ENTERED';

        $this->callWithTokenRefresh(
            business: $business,
            service: 'google_sheets',
            method: 'PUT',
            url: $url,
            body: [
                'range'  => $range,
                'values' => [$row],
            ],
        );

        Log::info('GoogleSheetsService: row updated.', [
            'business_id'    => $business->id,
            'appointment_id' => $appointment->id,
            'row_index'      => $rowIndex,
        ]);
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    /**
     * Build the 11-column row array for the given appointment.
     *
     * Column order:
     *  [0] ID | [1] Patient | [2] Phone | [3] Provider | [4] Service |
     *  [5] Date | [6] Time | [7] Status | [8] Channel | [9] Booked At | [10] Last Updated
     *
     * Date/Time values use the business timezone for human readability in the sheet.
     *
     * @param  Business     $business
     * @param  Appointment  $appointment
     * @return list<string>
     */
    private function buildRow(Business $business, Appointment $appointment): array
    {
        $appointment->loadMissing(['patient', 'provider']);

        $timezone = $business->timezone;
        $start    = Carbon::parse($appointment->start_time)->setTimezone($timezone);

        return [
            (string) $appointment->id,
            $appointment->patient->name    ?? '',
            $appointment->patient->phone   ?? '',
            $appointment->provider->displayName() ?? '',
            $appointment->service_type,
            $start->format('Y-m-d'),
            $start->format('H:i'),
            $appointment->status,
            $appointment->booked_via,
            Carbon::parse($appointment->created_at)->setTimezone($timezone)->toDateTimeString(),
            Carbon::now()->setTimezone($timezone)->toDateTimeString(),
        ];
    }

    /**
     * Search column A of the sheet for a row whose first cell matches
     * the given appointment ID.
     *
     * Returns the 1-based row number, or null if not found.
     *
     * @param  Business  $business
     * @param  int       $appointmentId
     * @return int|null
     *
     * @throws \RuntimeException On API failure
     */
    private function findRowIndex(Business $business, int $appointmentId): ?int
    {
        $spreadsheetId = $this->spreadsheetId($business);
        $sheetName     = $this->sheetName($business);

        $url = self::API_BASE . '/' . urlencode($spreadsheetId)
            . '/values/' . urlencode($sheetName . '!A:A');

        $response = $this->callWithTokenRefresh(
            business: $business,
            service: 'google_sheets',
            method: 'GET',
            url: $url,
            body: [],
        );

        $values = $response['values'] ?? [];

        foreach ($values as $index => $row) {
            if (isset($row[0]) && (string) $row[0] === (string) $appointmentId) {
                return $index + 1; // Convert 0-based PHP index to 1-based sheet row
            }
        }

        return null;
    }

    /**
     * Execute an API call, retrying once after a forced token refresh on 401.
     *
     * Identical retry logic to GoogleCalendarService::callWithTokenRefresh().
     * 429 Too Many Requests and all other non-2xx responses throw
     * \RuntimeException after logging the raw body.
     *
     * @param  Business              $business
     * @param  string                $service  'google_sheets'
     * @param  string                $method   HTTP verb
     * @param  string                $url      Full API URL
     * @param  array<string, mixed>  $body     Request body
     * @return array<string, mixed>            Decoded JSON response
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
        $token    = $this->oauth->getValidToken($business, $service);
        $response = $this->makeRequest($method, $url, $token, $body);

        // On 401: force refresh and retry exactly once
        if ($response->status() === 401) {
            Log::warning('GoogleSheetsService: 401 received, forcing token refresh.', [
                'business_id' => $business->id,
                'url'         => $url,
            ]);

            $token    = $this->oauth->refreshToken($business, $service);
            $response = $this->makeRequest($method, $url, $token, $body);
        }

        if ($response->failed()) {
            Log::error('GoogleSheetsService: API returned non-2xx response.', [
                'business_id' => $business->id,
                'method'      => $method,
                'url'         => $url,
                'status'      => $response->status(),
                'body'        => $response->body(),
            ]);

            throw new \RuntimeException(
                "GoogleSheetsService: {$method} {$url} failed "
                . "(HTTP {$response->status()}): {$response->body()}",
            );
        }

        return (array) $response->json();
    }

    /**
     * Make a single HTTP request to the Google Sheets API.
     *
     * @param  string                $method
     * @param  string                $url
     * @param  string                $token
     * @param  array<string, mixed>  $body
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
                'POST' => $request->post($url, $body),
                'PUT'  => $request->put($url, $body),
                default => $request->get($url),
            };
        } catch (ConnectionException $e) {
            Log::error('GoogleSheetsService: HTTP connection error.', [
                'url'   => $url,
                'error' => $e->getMessage(),
            ]);

            throw new \RuntimeException(
                "GoogleSheetsService: connection error for {$url}: {$e->getMessage()}",
                previous: $e,
            );
        }
    }

    /**
     * Extract and validate the Spreadsheet ID from the business integration config.
     *
     * @throws \RuntimeException When spreadsheet_id is not configured
     */
    private function spreadsheetId(Business $business): string
    {
        $id = (string) (
            $business->integration_config['google_sheets']['spreadsheet_id'] ?? ''
        );

        if ($id === '') {
            throw new \RuntimeException(
                "GoogleSheetsService: no spreadsheet_id configured for business #{$business->id}.",
            );
        }

        return $id;
    }

    /**
     * Return the sheet tab name from integration config, defaulting to 'Appointments'.
     */
    private function sheetName(Business $business): string
    {
        return (string) (
            $business->integration_config['google_sheets']['sheet_name'] ?? 'Appointments'
        );
    }
}
