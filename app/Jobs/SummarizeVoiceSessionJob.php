<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\VoiceSession;
use App\Models\VoiceSummary;
use App\Services\Queue\QueueRouteResolver;
use App\Services\Voice\VoiceSummaryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SummarizeVoiceSessionJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        private readonly int $voiceSessionId,
    ) {
        app(QueueRouteResolver::class)->apply($this, 'integrations');
    }

    public function handle(VoiceSummaryService $voiceSummaryService): void
    {
        $voiceSession = VoiceSession::query()
            ->with(['turns', 'events'])
            ->find($this->voiceSessionId);

        if ($voiceSession === null) {
            return;
        }

        $summary = $voiceSummaryService->build($voiceSession);

        VoiceSummary::query()->updateOrCreate(
            ['voice_session_id' => $voiceSession->id],
            $summary,
        );
    }
}
