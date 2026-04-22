<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\BusinessLaunchState;
use App\Services\Audit\AuditLogger;
use App\Services\LaunchReadinessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * AdminOnboardingController - Admin control plane for onboarding and launch readiness.
 *
 * This controller provides admin-facing views and actions for:
 *  - Viewing onboarding progress for all businesses
 *  - Approving launch for businesses
 *  - Resetting launch states
 *  - Viewing launch readiness snapshots
 *
 * Important: This controller deals with ADMIN WORKFLOW STATE only.
 * It does not affect runtime behavior directly.
 */
class AdminOnboardingController extends Controller
{
    public function __construct(
        private readonly LaunchReadinessService $launchReadiness,
        private readonly AuditLogger $auditLogger,
    ) {
    }

    /**
     * Render the onboarding overview page.
     *
     * Shows all businesses with their onboarding status and launch readiness.
     *
     * @param  Request  $request
     * @return View
     */
    public function index(Request $request): View
    {
        $launchStates = BusinessLaunchState::query()
            ->with('business')
            ->orderByDesc('created_at')
            ->paginate(50)
            ->withQueryString();

        $stages = BusinessLaunchState::query()
            ->select('launch_stage', \Illuminate\Support\Facades\DB::raw('count(*) as count'))
            ->groupBy('launch_stage')
            ->pluck('count', 'launch_stage')
            ->toArray();

        return view('admin.onboarding.index', [
            'launchStates' => $launchStates,
            'stages' => $stages,
            'totalBusinesses' => Business::query()->count(),
            'launchedBusinesses' => BusinessLaunchState::query()
                ->where('launch_stage', '>=', 'ready')
                ->count(),
        ]);
    }

    /**
     * View onboarding details for a specific business.
     *
     * @param  Business  $business
     * @return View
     */
    public function show(Business $business): View
    {
        $launchState = $business->launchState ?? $this->initializeLaunchState($business);
        $readiness = $this->launchReadiness->forBusiness($business);
        $onboardingProgress = $this->launchReadiness->onboardingProgress($business);

        return view('admin.onboarding.show', [
            'business' => $business,
            'launchState' => $launchState,
            'readiness' => $readiness,
            'onboardingProgress' => $onboardingProgress,
        ]);
    }

    /**
     * Mark onboarding as complete for a business.
     *
     * @param  Request  $request
     * @param  Business  $business
     * @return RedirectResponse
     */
    public function completeOnboarding(Request $request, Business $business): RedirectResponse
    {
        $request->validate([
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $launchState = $this->launchReadiness->completeOnboarding($business);

        $this->auditLogger->log(
            actor: $request->user(),
            action: 'onboarding.complete',
            subjectType: Business::class,
            subjectId: $business->id,
            payload: [
                'note' => $request->input('note'),
                'launch_stage' => $launchState->launch_stage,
                'onboarding_completed_at' => $launchState->onboarding_completed_at?->toIso8601String(),
            ],
            request: $request,
            businessId: $business->id,
        );

        return redirect()->route('admin.onboarding.show', $business)
            ->with('success', "Onboarding marked complete for {$business->name}.");
    }

    /**
     * Approve a business for launch.
     *
     * @param  Request  $request
     * @param  Business  $business
     * @return RedirectResponse
     */
    public function approveLaunch(Request $request, Business $business): RedirectResponse
    {
        $request->validate([
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $launchState = $business->launchState ?? $this->initializeLaunchState($business);

        $launchState->update([
            'launch_stage' => 'ready',
            'launch_approved_at' => now(),
            'can_skip_readiness' => false,
        ]);

        // Update subscription if it exists
        if ($business->subscription) {
            $business->subscription->update([
                'has_onboarding_complete' => true,
                'onboarding_completed_at' => now(),
            ]);
        }

        $this->auditLogger->log(
            actor: $request->user(),
            action: 'launch.approve',
            subjectType: Business::class,
            subjectId: $business->id,
            payload: [
                'note' => $request->input('note'),
                'approved_by_user_id' => $request->user()->id,
                'approved_at' => now()->toIso8601String(),
            ],
            request: $request,
            businessId: $business->id,
        );

        return redirect()->route('admin.onboarding.show', $business)
            ->with('success', "Launch approved for {$business->name}.");
    }

    /**
     * Reset a business's launch state.
     *
     * @param  Request  $request
     * @param  Business  $business
     * @return RedirectResponse
     */
    public function reset(Request $request, Business $business): RedirectResponse
    {
        $request->validate([
            'confirm' => ['accepted'],
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ]);

        $launchState = $business->launchState ?? $this->initializeLaunchState($business);

        $launchState->update([
            'launch_stage' => 'onboarding',
            'onboarding_started_at' => now(),
            'onboarding_completed_at' => null,
            'launch_approved_at' => null,
            'live_at' => null,
            'is_messaging_ready' => false,
            'is_billing_ready' => false,
            'is_voice_ready' => false,
            'is_diagnostics_ready' => false,
            'is_operations_ready' => false,
            'readiness_snapshot_at' => now(),
        ]);

        // Update subscription
        if ($business->subscription) {
            $business->subscription->update([
                'has_onboarding_complete' => false,
                'onboarding_completed_at' => null,
                'has_all_channels_configured' => false,
                'all_channels_configured_at' => null,
                'has_billing_configured' => false,
                'billing_configured_at' => null,
            ]);
        }

        $this->auditLogger->log(
            actor: $request->user(),
            action: 'launch.reset',
            subjectType: Business::class,
            subjectId: $business->id,
            payload: [
                'reason' => $request->input('reason'),
                'previous_stage' => $launchState->launch_stage,
                'reset_to_stage' => 'onboarding',
            ],
            request: $request,
            businessId: $business->id,
        );

        return redirect()->route('admin.onboarding.show', $business)
            ->with('success', "Launch state reset for {$business->name}.");
    }

    /**
     * Trigger a readiness snapshot update.
     *
     * @param  Request  $request
     * @param  Business  $business
     * @return RedirectResponse
     */
    public function refreshReadiness(Request $request, Business $business): RedirectResponse
    {
        $readiness = $this->launchReadiness->forBusiness($business);
        $launchState = $business->launchState ?? $this->initializeLaunchState($business);

        $launchState->update([
            'is_messaging_ready' => $readiness['components']['messaging']['status'] === 'ready',
            'is_billing_ready' => $readiness['components']['billing']['score'] >= 100,
            'is_voice_ready' => $readiness['components']['voice']['status'] === 'ready',
            'is_diagnostics_ready' => $readiness['components']['onboarding']['percentage'] >= 80,
            'is_operations_ready' => $readiness['components']['operations']['status'] === 'ready',
            'readiness_snapshot_at' => now(),
            'can_skip_readiness' => $launchState->can_skip_readiness ?? false,
        ]);

        // Also update subscription columns
        if ($business->subscription) {
            $business->subscription->update([
                'has_all_channels_configured' => $readiness['components']['messaging']['status'] === 'ready',
            ]);
        }

        $this->auditLogger->log(
            actor: $request->user(),
            action: 'launch.refresh_readiness',
            subjectType: Business::class,
            subjectId: $business->id,
            payload: [
                'new_readiness_snapshot_at' => now()->toIso8601String(),
                'messaging_ready' => $readiness['components']['messaging']['status'] === 'ready',
                'billing_ready' => $readiness['components']['billing']['score'] >= 100,
                'voice_ready' => $readiness['components']['voice']['status'] === 'ready',
            ],
            request: $request,
            businessId: $business->id,
        );

        return redirect()->route('admin.onboarding.show', $business)
            ->with('success', "Readiness snapshot refreshed for {$business->name}.");
    }

    /**
     * Initialize launch state for a business that doesn't have one.
     *
     * @param  Business  $business
     * @return BusinessLaunchState
     */
    private function initializeLaunchState(Business $business): BusinessLaunchState
    {
        return BusinessLaunchState::create([
            'business_id' => $business->id,
            'launch_stage' => 'onboarding',
            'onboarding_started_at' => now(),
        ]);
    }

    /**
     * Get summary data for the admin dashboard.
     *
     * @param  Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function summary(Request $request): \Illuminate\Http\JsonResponse
    {
        return response()->json($this->launchReadiness->summary());
    }
}
