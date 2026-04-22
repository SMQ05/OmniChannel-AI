<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Business;
use App\Models\BusinessLaunchState;
use App\Models\BusinessSubscription;
use App\Models\ConversationLog;
use App\Services\Billing\BillingLedgerService;
use App\Services\Voice\VoiceConfigurationService;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * LaunchReadinessService - Aggregate readiness from multiple sources.
 *
 * This service aggregates launch readiness signals from various sources:
 *  - ChannelReadinessService for messaging channel status
 *  - BillingLedgerService for billing configuration
 *  - VoiceConfigurationService for voice setup
 *  - Business operations data (onboarding, API usage)
 *
 * The service does NOT provide runtime truth. It provides an ADMIN
 * workflow perspective on launch readiness.
 *
 * Key distinction:
 *  - ChannelReadinessService.forChannel() -> "Is this channel working NOW?"
 *  - LaunchReadinessService -> "Has the business completed onboarding?"
 */
class LaunchReadinessService
{
    public function __construct(
        private readonly ChannelReadinessService $channelReadiness,
        private readonly BillingLedgerService $billingLedger,
        private readonly VoiceConfigurationService $voiceConfig,
    ) {
    }

    /**
     * Get comprehensive launch readiness report for a business.
     *
     * Returns an array with:
     *  - total_score: 0-100 aggregated score
     *  - stage: current launch stage
     *  - components: breakdown of each readiness component
     *  - recommendations: what needs to be done to proceed
     *  - is_complete: boolean overall readiness
     *
     * @return array<string, mixed>
     */
    public function forBusiness(Business $business): array
    {
        $launchState = $this->getOrInitializeLaunchState($business);

        $components = [
            'messaging' => $this->checkMessaging($business),
            'billing' => $this->checkBilling($business),
            'voice' => $this->checkVoice($business),
            'operations' => $this->checkOperations($business),
            'onboarding' => $this->checkOnboarding($business),
        ];

        $score = $this->calculateScore($components);
        $stage = $this->determineStage($components, $launchState);
        $recommendations = $this->generateRecommendations($components);

        return [
            'business_id' => $business->id,
            'business_name' => $business->name,
            'total_score' => $score,
            'stage' => $stage,
            'components' => $components,
            'recommendations' => $recommendations,
            'is_complete' => $score >= 80 && in_array($stage, ['ready', 'live']),
            'launch_state' => $launchState,
            'readiness_snapshot_at' => now(),
        ];
    }

    /**
     * Check messaging readiness.
     *
     * @return array<string, mixed>
     */
    private function checkMessaging(Business $business): array
    {
        $states = $this->channelReadiness->forBusiness($business);

        $enabledCount = 0;
        $readyCount = 0;
        $errors = [];

        foreach ($states as $channel => $state) {
            if ($state['enabled']) {
                $enabledCount++;
            }
            if ($state['ready']) {
                $readyCount++;
            }
            if (!empty($state['errors'])) {
                $errors = array_merge($errors, $state['errors']);
            }
        }

        return [
            'channel_count' => count($states),
            'enabled_count' => $enabledCount,
            'ready_count' => $readyCount,
            'errors' => $errors,
            'status' => $enabledCount > 0 && $enabledCount === $readyCount ? 'ready' : 'pending',
            'score' => $enabledCount === 0 ? 0 : ($enabledCount === $readyCount ? 100 : 50),
        ];
    }

    /**
     * Check billing readiness.
     *
     * @return array<string, mixed>
     */
    private function checkBilling(Business $business): array
    {
        $billingAccount = $business->billingAccount;

        if ($billingAccount === null) {
            return [
                'status' => 'pending',
                'error' => 'No billing account configured',
                'score' => 0,
            ];
        }

        // Check provider configuration
        $hasProvider = $billingAccount->provider_driver !== null;
        $hasAccountRef = $billingAccount->provider_account_ref !== null;

        $score = 0;
        if ($hasProvider) {
            $score += 50;
        }
        if ($hasAccountRef) {
            $score += 50;
        }

        $status = $hasProvider ? 'configured' : 'pending';
        if ($score === 100) {
            $status = 'ready';
        }

        return [
            'provider' => $billingAccount->provider_driver,
            'has_provider' => $hasProvider,
            'has_account_ref' => $hasAccountRef,
            'status' => $status,
            'score' => $score,
        ];
    }

    /**
     * Check voice readiness.
     *
     * @return array<string, mixed>
     */
    private function checkVoice(Business $business): array
    {
        $channels = $business->voiceChannels;

        $hasChannels = $channels->count() > 0;
        $hasConfiguredChannels = $channels->where('is_configured', true)->count();

        $errors = [];
        foreach ($channels as $channel) {
            $config = $this->voiceConfig->forChannel($business, $channel->channel_id);
            if (isset($config['error'])) {
                $errors[] = $config['error'];
            }
        }

        $score = $hasChannels ? 100 : 0;
        if ($hasChannels && $hasConfiguredChannels === 0) {
            $score = 50;
        }

        $status = $hasChannels ? ($hasConfiguredChannels > 0 ? 'ready' : 'pending') : 'pending';

        return [
            'channel_count' => $channels->count(),
            'configured_count' => $hasConfiguredChannels,
            'errors' => $errors,
            'status' => $status,
            'score' => $score,
        ];
    }

    /**
     * Check operations readiness.
     *
     * @return array<string, mixed>
     */
    private function checkOperations(Business $business): array
    {
        // Check for at least one provider
        $hasProviders = $business->providers()->count() > 0;

        // Check for at least one service
        $hasServices = $business->services()->count() > 0;

        // Check for business hours configuration
        $hasHours = !empty($business->operations_config['business_hours'] ?? []);

        // Check for availability rules
        $hasRules = !empty($business->operations_config['availability_rules'] ?? []);

        $score = 0;
        if ($hasProviders) {
            $score += 25;
        }
        if ($hasServices) {
            $score += 25;
        }
        if ($hasHours) {
            $score += 25;
        }
        if ($hasRules) {
            $score += 25;
        }

        $status = 'pending';
        if ($score >= 75) {
            $status = 'ready';
        } elseif ($score >= 50) {
            $status = 'partial';
        }

        return [
            'has_providers' => $hasProviders,
            'has_services' => $hasServices,
            'has_hours' => $hasHours,
            'has_rules' => $hasRules,
            'status' => $status,
            'score' => $score,
        ];
    }

    /**
     * Check onboarding completion.
     *
     * @return array<string, mixed>
     */
    private function checkOnboarding(Business $business): array
    {
        $launchState = $business->launchState;
        $subscription = $business->subscription;

        $hasTeam = $business->users()->where('role', '!=', 'staff')->count() > 0;

        // Count setup steps completed (from ai_config and operations_config)
        $aiConfig = $business->ai_config ?? [];
        $opsConfig = $business->operations_config ?? [];
        $steps = [
            'ai_configured' => !empty($aiConfig['llm_provider'] ?? null),
            'ai_name_set' => !empty($aiConfig['ai_name'] ?? null),
            'business_hours_set' => !empty($opsConfig['business_hours'] ?? null),
            'availability_rules_set' => !empty($opsConfig['availability_rules'] ?? null),
            'team_member_added' => $hasTeam,
        ];

        $completedSteps = count(array_filter($steps));
        $totalSteps = count($steps);
        $percentage = (int) round(($completedSteps / $totalSteps) * 100);

        $isCompleted = $launchState !== null && $launchState->onboarding_completed_at !== null;

        return [
            'percentage' => $percentage,
            'is_completed' => $isCompleted,
            'completed_steps' => $completedSteps,
            'total_steps' => $totalSteps,
            'steps' => $steps,
            'onboarding_started_at' => $launchState?->onboarding_started_at,
            'onboarding_completed_at' => $launchState?->onboarding_completed_at,
            'status' => $isCompleted ? 'complete' : 'in_progress',
            'score' => $percentage,
        ];
    }

    /**
     * Calculate total score from components.
     *
     * @param  array<string, array<string, mixed>>  $components
     * @return int
     */
    private function calculateScore(array $components): int
    {
        $weights = [
            'messaging' => 0.25,
            'billing' => 0.20,
            'voice' => 0.15,
            'operations' => 0.20,
            'onboarding' => 0.20,
        ];

        $total = 0;
        foreach ($components as $key => $component) {
            $weight = $weights[$key] ?? 0.2;
            $total += ($component['score'] ?? 0) * $weight;
        }

        return (int) round($total);
    }

    /**
     * Determine current launch stage based on components.
     *
     * @param  array<string, array<string, mixed>>  $components
     * @return string
     */
    private function determineStage(array $components, BusinessLaunchState $launchState): string
    {
        $score = $this->calculateScore($components);

        // Check if already in a specific stage
        if ($launchState->launch_stage !== 'onboarding') {
            return $launchState->launch_stage;
        }

        // Determine stage from components
        if ($score >= 80) {
            return 'ready';
        }
        if ($score >= 50) {
            return 'configured';
        }
        if ($score >= 25) {
            return 'incomplete';
        }

        return 'onboarding';
    }

    /**
     * Generate recommendations based on gaps.
     *
     * @param  array<string, array<string, mixed>>  $components
     * @return array<string>
     */
    private function generateRecommendations(array $components): array
    {
        $recommendations = [];

        if ($components['messaging']['score'] < 100) {
            $recommendations[] = 'Configure at least one messaging channel (WhatsApp or Messenger)';
        }

        if ($components['billing']['score'] < 100) {
            $recommendations[] = 'Configure billing provider account';
        }

        if ($components['voice']['score'] < 100) {
            $recommendations[] = 'Set up at least one voice channel';
        }

        if ($components['operations']['score'] < 75) {
            $recommendations[] = 'Configure business hours and availability rules';
        }

        if ($components['onboarding']['percentage'] < 100) {
            $recommendations[] = 'Complete business onboarding profile';
        }

        return $recommendations;
    }

    /**
     * Get or initialize launch state for a business.
     *
     * @return BusinessLaunchState
     */
    private function getOrInitializeLaunchState(Business $business): BusinessLaunchState
    {
        $launchState = $business->launchState;

        if ($launchState !== null) {
            return $launchState;
        }

        // Initialize new launch state
        return BusinessLaunchState::create([
            'business_id' => $business->id,
            'launch_stage' => 'onboarding',
        ]);
    }

    /**
     * Mark onboarding as complete for a business.
     *
     * @param  Business  $business
     * @return BusinessLaunchState
     */
    public function completeOnboarding(Business $business): BusinessLaunchState
    {
        $launchState = $this->getOrInitializeLaunchState($business);

        $launchState->update([
            'onboarding_completed_at' => now(),
            'launch_stage' => 'ready',
        ]);

        return $launchState;
    }

    /**
     * Get summary for all businesses.
     *
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        $total = Business::query()->count();
        $launched = BusinessLaunchState::query()
            ->where('launch_stage', '>=', 'ready')
            ->count();

        $byStage = BusinessLaunchState::query()
            ->select('launch_stage', \Illuminate\Support\Facades\DB::raw('count(*) as count'))
            ->groupBy('launch_stage')
            ->pluck('count', 'launch_stage')
            ->toArray();

        return [
            'total_businesses' => $total,
            'launched_businesses' => $launched,
            'by_stage' => $byStage,
            'launch_rate' => $total > 0 ? round(($launched / $total) * 100) : 0,
        ];
    }

    /**
     * Get onboarding progress for a business.
     *
     * @param  Business  $business
     * @return array<string, mixed>
     */
    public function onboardingProgress(Business $business): array
    {
        $launchState = $business->launchState;

        if ($launchState === null) {
            return [
                'is_onboarding' => true,
                'percentage' => 0,
                'completed_steps' => 0,
                'total_steps' => 0,
                'steps' => [
                    'ai_configured' => false,
                    'ai_name_set' => false,
                    'business_hours_set' => false,
                    'availability_rules_set' => false,
                    'team_member_added' => false,
                ],
            ];
        }

        $progress = $this->checkOnboarding($business);

        return [
            'is_onboarding' => $launchState->launch_stage === 'onboarding',
            'percentage' => $progress['percentage'],
            'completed_steps' => $progress['completed_steps'],
            'total_steps' => $progress['total_steps'],
            'steps' => $progress['steps'],
            'onboarding_started_at' => $launchState->onboarding_started_at,
            'onboarding_completed_at' => $launchState->onboarding_completed_at,
        ];
    }
}
