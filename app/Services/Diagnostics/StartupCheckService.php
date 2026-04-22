<?php

declare(strict_types=1);

namespace App\Services\Diagnostics;

use App\Models\Business;
use App\Services\ChannelReadinessService;
use Illuminate\Support\Facades\Schema;

class StartupCheckService
{
    public function __construct(
        private readonly ChannelReadinessService $channelReadinessService,
    ) {}

    /**
     * @return list<array{severity: string, title: string, detail: string}>
     */
    public function globalIssues(): array
    {
        $issues = [];

        if ((string) config('app.key') === '') {
            $issues[] = [
                'severity' => 'critical',
                'title' => 'APP_KEY missing',
                'detail' => 'Set APP_KEY before serving production traffic.',
            ];
        }

        if (config('queue.default') === 'database' && !Schema::hasTable('jobs')) {
            $issues[] = [
                'severity' => 'critical',
                'title' => 'Queue tables missing',
                'detail' => 'The database queue is configured but the jobs table does not exist.',
            ];
        }

        if (config('queue.failed.driver') !== 'null' && !Schema::hasTable('failed_jobs')) {
            $issues[] = [
                'severity' => 'critical',
                'title' => 'Failed job storage missing',
                'detail' => 'Create the failed_jobs table so queue failures are visible and retryable.',
            ];
        }

        if (config('cache.default') === 'database' && !Schema::hasTable('cache')) {
            $issues[] = [
                'severity' => 'warning',
                'title' => 'Database cache tables missing',
                'detail' => 'The database cache driver is configured but cache tables are not present.',
            ];
        }

        if (!str_starts_with((string) config('app.url'), 'http')) {
            $issues[] = [
                'severity' => 'warning',
                'title' => 'APP_URL may be invalid',
                'detail' => 'APP_URL should include https:// for webhook signatures, redirects, and assets.',
            ];
        }

        return $issues;
    }

    /**
     * @return list<array{severity: string, title: string, detail: string}>
     */
    public function businessIssues(Business $business): array
    {
        $issues = [];
        $channels = $this->channelReadinessService->forBusiness($business);
        $integrations = $business->integration_config ?? [];
        $voice = $business->channel_config['voice'] ?? [];

        if ($channels['whatsapp']['enabled'] && !$channels['whatsapp']['connected']) {
            $issues[] = [
                'severity' => 'critical',
                'title' => 'WhatsApp connection incomplete',
                'detail' => 'Business enablement is live, but the admin-managed WhatsApp connection is not ready.',
            ];
        }

        if ($channels['messenger']['enabled'] && !$channels['messenger']['connected']) {
            $issues[] = [
                'severity' => 'critical',
                'title' => 'Messenger connection incomplete',
                'detail' => 'Business enablement is live, but the admin-managed Messenger connection is not ready.',
            ];
        }

        if (($integrations['google_calendar']['enabled'] ?? false) && (
            empty($integrations['google_credentials']['client_id'])
            || empty($integrations['google_credentials']['client_secret'])
            || empty($integrations['google_calendar']['calendar_id'])
        )) {
            $issues[] = [
                'severity' => 'warning',
                'title' => 'Google Calendar config incomplete',
                'detail' => 'OAuth credentials and calendar_id are required for sync and diagnostics.',
            ];
        }

        if (($integrations['google_sheets']['enabled'] ?? false) && (
            empty($integrations['google_credentials']['client_id'])
            || empty($integrations['google_credentials']['client_secret'])
            || empty($integrations['google_sheets']['spreadsheet_id'])
            || empty($integrations['google_sheets']['sheet_name'])
        )) {
            $issues[] = [
                'severity' => 'warning',
                'title' => 'Google Sheets config incomplete',
                'detail' => 'OAuth credentials, spreadsheet_id, and sheet_name are required for sync and diagnostics.',
            ];
        }

        if (($voice['enabled'] ?? false) && !config('kynex.features.voice_agent')) {
            $issues[] = [
                'severity' => 'warning',
                'title' => 'Voice enabled while global feature flag is off',
                'detail' => 'The business has voice enabled in settings, but FEATURE_VOICE_AGENT is disabled at platform level.',
            ];
        }

        if (($voice['enabled'] ?? false) && (
            empty($voice['transport_provider'])
            || empty($voice['stt_provider'])
            || empty($voice['llm_provider'])
            || empty($voice['tts_provider'])
        )) {
            $issues[] = [
                'severity' => 'warning',
                'title' => 'Voice provider routing incomplete',
                'detail' => 'Select transport, STT, LLM, and TTS providers before enabling live voice rollout.',
            ];
        }

        return $issues;
    }
}
