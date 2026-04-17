<?php

declare(strict_types=1);

namespace App\Services\Google;

use App\Models\Business;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Manages Google OAuth 2.0 token lifecycle for business integrations.
 *
 * Each business stores its own OAuth credentials inside
 * `businesses.integration_config` under the relevant service key:
 *
 *   integration_config.google_calendar.token = {
 *     access_token:  string,
 *     refresh_token: string,
 *     expires_at:    int  (Unix timestamp)
 *   }
 *
 *   integration_config.google_sheets.token  = { … same shape … }
 *
 * Public API:
 *  - getValidToken(Business, service) — returns a fresh access_token,
 *    refreshing + persisting if within the expiry buffer window.
 *  - refreshToken(Business, service) — forces a hard refresh regardless
 *    of current expiry state and always persists the new token.
 *
 * Token refresh uses the standard Google OAuth 2.0 token endpoint with
 * the `refresh_token` grant type. Client credentials are loaded from the
 * `credentials` sub-object stored in integration_config.
 *
 * Error handling:
 *  - HTTP connection failures → throw \RuntimeException (job retries)
 *  - Non-200 refresh response  → throw \RuntimeException (job retries)
 *  - Missing refresh_token     → throw \RuntimeException (operator action needed)
 */
class GoogleOAuthService
{
    /** Google OAuth 2.0 token endpoint. */
    private const TOKEN_ENDPOINT = 'https://oauth2.googleapis.com/token';

    /**
     * Seconds before actual expiry at which we proactively refresh the token.
     * Prevents the token from expiring mid-flight during a slow API call.
     */
    private const EXPIRY_BUFFER_SECONDS = 60;

    /**
     * Return a valid access token for the given service, refreshing if needed.
     *
     * Checks `token.expires_at` against the current UTC time plus the
     * 60-second buffer. If the token is still valid, returns it immediately.
     * If expired (or missing), calls refreshToken() which fetches, persists,
     * and returns the new access_token.
     *
     * @param  Business  $business  The tenant whose credentials to use
     * @param  string    $service   'google_calendar' or 'google_sheets'
     * @return string               A valid access_token string
     *
     * @throws \RuntimeException When the token cannot be obtained or refreshed
     */
    public function getValidToken(Business $business, string $service): string
    {
        $tokenData = $business->integration_config[$service]['token'] ?? [];
        $expiresAt = (int) ($tokenData['expires_at'] ?? 0);

        $isExpired = $expiresAt === 0
            || $expiresAt <= (time() + self::EXPIRY_BUFFER_SECONDS);

        if ($isExpired) {
            return $this->refreshToken($business, $service);
        }

        $accessToken = (string) ($tokenData['access_token'] ?? '');

        if ($accessToken === '') {
            return $this->refreshToken($business, $service);
        }

        return $accessToken;
    }

    /**
     * Force-refresh the OAuth token for the given service and persist
     * the new credentials back to businesses.integration_config.
     *
     * Always hits the Google token endpoint regardless of current expiry.
     * Use this method after receiving a 401 Unauthorized response to
     * force a hard refresh when the stored token has been revoked.
     *
     * @param  Business  $business  The tenant whose credentials to refresh
     * @param  string    $service   'google_calendar' or 'google_sheets'
     * @return string               The new access_token string
     *
     * @throws \RuntimeException On missing credentials, HTTP error, or non-200 response
     */
    public function refreshToken(Business $business, string $service): string
    {
        $config      = $business->integration_config[$service] ?? [];
        $credentials = $business->integration_config['google_credentials'] ?? [];
        $tokenData   = $config['token']       ?? [];

        $clientId     = (string) ($credentials['client_id']     ?? '');
        $clientSecret = (string) ($credentials['client_secret'] ?? '');
        $refreshToken = (string) ($tokenData['refresh_token']   ?? '');

        if ($clientId === '' || $clientSecret === '' || $refreshToken === '') {
            throw new \RuntimeException(
                "GoogleOAuthService: missing OAuth credentials for service '{$service}' "
                . "on business #{$business->id}. Re-authorisation required.",
            );
        }

        try {
            $response = Http::asForm()
                ->timeout(15)
                ->post(self::TOKEN_ENDPOINT, [
                    'client_id'     => $clientId,
                    'client_secret' => $clientSecret,
                    'refresh_token' => $refreshToken,
                    'grant_type'    => 'refresh_token',
                ]);
        } catch (ConnectionException $e) {
            Log::error('GoogleOAuthService: connection error during token refresh.', [
                'business_id' => $business->id,
                'service'     => $service,
                'error'       => $e->getMessage(),
            ]);

            throw new \RuntimeException(
                "GoogleOAuthService: connection error refreshing token for '{$service}': "
                . $e->getMessage(),
                previous: $e,
            );
        }

        if ($response->failed()) {
            Log::error('GoogleOAuthService: token refresh returned non-2xx response.', [
                'business_id' => $business->id,
                'service'     => $service,
                'status'      => $response->status(),
                'body'        => $response->body(),
            ]);

            throw new \RuntimeException(
                "GoogleOAuthService: token refresh failed for '{$service}' "
                . "(HTTP {$response->status()}): {$response->body()}",
            );
        }

        $newTokenData = $response->json();
        $accessToken  = (string) ($newTokenData['access_token'] ?? '');
        $expiresIn    = (int)    ($newTokenData['expires_in']   ?? 3600);

        // Persist the refreshed token back to integration_config.
        // We keep the existing refresh_token in place — Google only returns
        // a new one when the user re-authorises the application.
        $this->persistToken($business, $service, [
            'access_token'  => $accessToken,
            'refresh_token' => $refreshToken, // preserve the existing refresh_token
            'expires_at'    => time() + $expiresIn,
        ]);

        Log::info('GoogleOAuthService: token refreshed and persisted.', [
            'business_id' => $business->id,
            'service'     => $service,
            'expires_in'  => $expiresIn,
        ]);

        return $accessToken;
    }

    /**
     * Persist new token data into businesses.integration_config for the
     * given service, merging into the existing structure.
     *
     * @param  Business              $business   The tenant to update
     * @param  string                $service    'google_calendar' or 'google_sheets'
     * @param  array<string, mixed>  $tokenData  New token fields to store
     */
    private function persistToken(Business $business, string $service, array $tokenData): void
    {
        $integrationConfig = $business->integration_config ?? [];

        // Merge new token data into the existing service block
        $integrationConfig[$service]['token'] = array_merge(
            $integrationConfig[$service]['token'] ?? [],
            $tokenData,
        );

        $business->integration_config = $integrationConfig;
        $business->saveQuietly(); // skip model events — this is a credentials update
    }
}
