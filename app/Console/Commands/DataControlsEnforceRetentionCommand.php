<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Business;
use App\Models\User;
use App\Services\DataControls\RetentionPolicyService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class DataControlsEnforceRetentionCommand extends Command
{
    protected $signature = 'data-controls:enforce-retention
        {--business= : Restrict enforcement to one business id}
        {--dry-run : Calculate would-delete counts without deleting data}
        {--run-key= : Idempotency key (UUID). Auto-generated when omitted}
        {--actor-id= : Optional user id for audit context}';

    protected $description = 'Apply retention policies with bounded tenant overrides and idempotent run logging.';

    public function handle(RetentionPolicyService $retentionPolicyService): int
    {
        $business = null;
        if ($this->option('business') !== null) {
            $business = Business::query()->find((int) $this->option('business'));
            if ($business === null) {
                $this->error('Business not found.');

                return self::FAILURE;
            }
        }

        $actor = null;
        if ($this->option('actor-id') !== null) {
            $actor = User::query()->find((int) $this->option('actor-id'));
            if ($actor === null || !$actor->isSuperAdmin()) {
                $this->error('actor-id must resolve to a super_admin user.');

                return self::FAILURE;
            }
        }

        $runKey = (string) ($this->option('run-key') ?: Str::uuid()->toString());
        $dryRun = (bool) $this->option('dry-run');

        $run = $retentionPolicyService->runRetention(
            business: $business,
            dryRun: $dryRun,
            runKey: $runKey,
            commandSignature: $this->signature,
            actor: $actor,
        );

        $summary = $run->summary ?? [];
        $totals = $summary['totals'] ?? [];

        $this->info(sprintf(
            'Retention run %s finished with status %s (mode=%s).',
            $run->run_key,
            $run->status,
            $run->mode,
        ));
        $this->line(sprintf(
            'Totals: conversations=%d inbound=%d outbound=%d voice_events=%d',
            (int) ($totals['conversation_logs'] ?? 0),
            (int) ($totals['inbound_webhooks'] ?? 0),
            (int) ($totals['outbound_message_attempts'] ?? 0),
            (int) ($totals['voice_events'] ?? 0),
        ));

        return $run->status === 'failed' ? self::FAILURE : self::SUCCESS;
    }
}
