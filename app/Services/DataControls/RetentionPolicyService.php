<?php

declare(strict_types=1);

namespace App\Services\DataControls;

use App\Models\Business;
use App\Models\BusinessDataRetentionSetting;
use App\Models\DataRetentionRun;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Throwable;

class RetentionPolicyService
{
    /**
     * @return array<string, int>
     */
    public function effectivePolicyForBusiness(Business $business): array
    {
        $defaults = config('data_controls.retention.defaults', []);
        $bounds = config('data_controls.retention.bounds', []);
        $min = (int) ($bounds['min_days'] ?? 30);
        $max = (int) ($bounds['max_days'] ?? 1460);

        $setting = BusinessDataRetentionSetting::query()->where('business_id', $business->id)->first();

        return [
            'conversation_logs_days' => $this->boundedDays($setting?->conversation_logs_days, (int) ($defaults['conversation_logs_days'] ?? 365), $min, $max),
            'inbound_webhooks_days' => $this->boundedDays($setting?->inbound_webhooks_days, (int) ($defaults['inbound_webhooks_days'] ?? 120), $min, $max),
            'outbound_attempts_days' => $this->boundedDays($setting?->outbound_attempts_days, (int) ($defaults['outbound_attempts_days'] ?? 120), $min, $max),
            'voice_events_days' => $this->boundedDays($setting?->voice_events_days, (int) ($defaults['voice_events_days'] ?? 180), $min, $max),
        ];
    }

    public function runRetention(
        ?Business $business,
        bool $dryRun,
        string $runKey,
        string $commandSignature,
        ?User $actor = null,
    ): DataRetentionRun {
        $existingRun = DataRetentionRun::query()->where('run_key', $runKey)->first();
        if ($existingRun !== null) {
            return $existingRun;
        }

        $run = DataRetentionRun::query()->create([
            'run_key' => $runKey,
            'mode' => $dryRun ? 'dry_run' : 'apply',
            'scope' => $business === null ? 'all' : 'business',
            'business_id' => $business?->id,
            'triggered_by_user_id' => $actor?->id,
            'status' => 'started',
            'command_signature' => $commandSignature,
            'started_at' => now(),
        ]);

        $summary = [
            'dry_run' => $dryRun,
            'businesses' => [],
            'totals' => [
                'conversation_logs' => 0,
                'inbound_webhooks' => 0,
                'outbound_message_attempts' => 0,
                'voice_events' => 0,
            ],
        ];

        try {
            $businesses = $business !== null
                ? collect([$business])
                : Business::query()->orderBy('id')->get();

            foreach ($businesses as $targetBusiness) {
                $setting = BusinessDataRetentionSetting::query()->where('business_id', $targetBusiness->id)->first();
                $legalHold = $setting?->legal_hold_until !== null && $setting->legal_hold_until->isFuture();
                $policy = $this->effectivePolicyForBusiness($targetBusiness);

                if ($legalHold) {
                    $summary['businesses'][(string) $targetBusiness->id] = [
                        'business_slug' => $targetBusiness->slug,
                        'skipped' => true,
                        'reason' => 'legal_hold_active',
                        'policy' => $policy,
                    ];
                    continue;
                }

                $results = $this->applyRetentionForBusiness($targetBusiness, $policy, $dryRun);
                $summary['businesses'][(string) $targetBusiness->id] = [
                    'business_slug' => $targetBusiness->slug,
                    'policy' => $policy,
                    'results' => $results,
                ];

                foreach (array_keys($summary['totals']) as $metric) {
                    $summary['totals'][$metric] += (int) ($results[$metric] ?? 0);
                }
            }

            $run->forceFill([
                'status' => 'completed',
                'summary' => $summary,
                'finished_at' => now(),
            ])->save();
        } catch (Throwable $exception) {
            $run->forceFill([
                'status' => 'failed',
                'summary' => $summary,
                'error' => $exception->getMessage(),
                'finished_at' => now(),
            ])->save();

            throw $exception;
        }

        return $run->refresh();
    }

    /**
     * @param  array<string, int>  $policy
     * @return array<string, int>
     */
    private function applyRetentionForBusiness(Business $business, array $policy, bool $dryRun): array
    {
        $conversationCutoff = now()->subDays($policy['conversation_logs_days']);
        $inboundCutoff = now()->subDays($policy['inbound_webhooks_days']);
        $outboundCutoff = now()->subDays($policy['outbound_attempts_days']);
        $voiceCutoff = now()->subDays($policy['voice_events_days']);

        $conversationQuery = DB::table('conversation_logs')
            ->where('business_id', $business->id)
            ->where('created_at', '<', $conversationCutoff);
        $inboundQuery = DB::table('inbound_webhooks')
            ->where('business_id', $business->id)
            ->where('created_at', '<', $inboundCutoff);
        $outboundQuery = DB::table('outbound_message_attempts')
            ->where('business_id', $business->id)
            ->where('created_at', '<', $outboundCutoff);
        $voiceQuery = DB::table('voice_events')
            ->where('business_id', $business->id)
            ->where('occurred_at', '<', $voiceCutoff);

        if ($dryRun) {
            return [
                'conversation_logs' => $conversationQuery->count(),
                'inbound_webhooks' => $inboundQuery->count(),
                'outbound_message_attempts' => $outboundQuery->count(),
                'voice_events' => $voiceQuery->count(),
            ];
        }

        return [
            'conversation_logs' => $conversationQuery->delete(),
            'inbound_webhooks' => $inboundQuery->delete(),
            'outbound_message_attempts' => $outboundQuery->delete(),
            'voice_events' => $voiceQuery->delete(),
        ];
    }

    private function boundedDays(?int $value, int $default, int $min, int $max): int
    {
        $candidate = $value ?? $default;

        return max($min, min($max, $candidate));
    }
}
