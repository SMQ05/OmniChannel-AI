<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Services\Usage\UsageSummaryService;
use App\Services\Voice\VoiceConfigurationService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminVoiceController extends Controller
{
    public function index(
        Request $request,
        VoiceConfigurationService $voiceConfigurationService,
        UsageSummaryService $usageSummaryService,
    ): View {
        $businesses = Business::query()
            ->with(['subscription.plan', 'voiceChannels'])
            ->orderBy('name')
            ->get()
            ->map(function (Business $business) use ($voiceConfigurationService, $usageSummaryService): array {
                $voiceState = $voiceConfigurationService->forBusiness($business);
                $featureFlags = $usageSummaryService->mergedFeatureFlags($business);

                return [
                    'business' => $business,
                    'plan_supports_voice' => (bool) ($featureFlags['voice_agent'] ?? false),
                    'voice_ready' => $voiceState['ready'],
                    'active_channels' => $voiceState['summary']['active_channels'],
                    'recent_calls' => $voiceState['summary']['recent_calls'],
                    'issues' => $voiceState['issues'],
                ];
            });

        return view('admin.voice.index', [
            'platform' => $voiceConfigurationService->platformSummary(),
            'businessVoiceStates' => $businesses,
        ]);
    }
}
