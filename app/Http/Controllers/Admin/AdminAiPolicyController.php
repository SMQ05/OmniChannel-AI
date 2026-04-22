<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Plan;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * AdminAiPolicyController - Admin control plane for AI policy management.
 *
 * This controller provides visibility and controls for AI-related policies:
 *  - Rate limiting configuration
 *  - Guardrails settings
 *  - Feature flags
 *  - Fallback provider configuration
 *
 * IMPORTANT: This does NOT imply centralized runtime behavior.
 * Runtime logic still reads from:
 *  - Business.ai_config
 *  - BusinessSubscription.feature_flags
 *  - config/kynex.php features
 *
 * This is purely for ADMIN VISIBILITY and OVERRIDE configuration.
 */
class AdminAiPolicyController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {
    }

    /**
     * Render the AI policy overview page.
     *
     * Shows AI policies for all businesses.
     *
     * @param  Request  $request
     * @return View
     */
    public function index(Request $request): View
    {
        $businessesQuery = Business::query()
            ->with(['subscription.plan', 'launchState'])
            ->orderByDesc('created_at');

        if ($request->filled('search')) {
            $search = $request->input('search');
            $businessesQuery->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                  ->orWhere('slug', 'ilike', "%{$search}%");
            });
        }

        if ($request->filled('has_override')) {
            $businessesQuery->whereNotNull('ai_rate_limit_per_hour')
                ->orWhereNotNull('ai_fallback_provider');
        }

        $businesses = $businessesQuery->paginate(50)->withQueryString();

        return view('admin.ai-policy.index', [
            'businesses' => $businesses,
            'filters' => $request->only(['search', 'has_override']),
        ]);
    }

    /**
     * View AI policy for a specific business.
     *
     * @param  Business  $business
     * @return View
     */
    public function show(Business $business): View
    {
        $plan = $business->subscription?->plan;
        $launchState = $business->launchState;

        // Get merged feature flags
        $featureFlags = $this->getMergedFeatureFlags($business, $plan);

        return view('admin.ai-policy.show', [
            'business' => $business,
            'plan' => $plan,
            'launchState' => $launchState,
            'featureFlags' => $featureFlags,
        ]);
    }

    /**
     * Update AI policy for a business.
     *
     * @param  Request  $request
     * @param  Business  $business
     * @return RedirectResponse
     */
    public function update(Request $request, Business $business): RedirectResponse
    {
        $validated = $request->validate([
            'ai_rate_limit_per_hour' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'ai_rate_limit_per_day' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'ai_content_safety_enabled' => ['nullable', 'boolean'],
            'ai_pii_detection_enabled' => ['nullable', 'boolean'],
            'ai_hallucination_guard_enabled' => ['nullable', 'boolean'],
            'ai_voice_agent_enabled' => ['nullable', 'boolean'],
            'ai_email_agent_enabled' => ['nullable', 'boolean'],
            'ai_chat_only_enabled' => ['nullable', 'boolean'],
            'ai_fallback_provider' => ['nullable', 'string', 'in:claude,gpt4o,openrouter,minimax,none'],
        ]);

        $original = $business->only([
            'ai_rate_limit_per_hour',
            'ai_rate_limit_per_day',
            'ai_content_safety_enabled',
            'ai_pii_detection_enabled',
            'ai_hallucination_guard_enabled',
            'ai_voice_agent_enabled',
            'ai_email_agent_enabled',
            'ai_chat_only_enabled',
            'ai_fallback_provider',
        ]);

        $business->update($validated);

        $changes = $this->getChangedFields($original, $validated);

        if (!empty($changes)) {
            $this->auditLogger->log(
                actor: $request->user(),
                action: 'ai_policy.updated',
                subjectType: Business::class,
                subjectId: $business->id,
                payload: [
                    'changes' => $changes,
                    'updated_by_user_id' => $request->user()->id,
                ],
                request: $request,
                businessId: $business->id,
            );
        }

        return redirect()->route('admin.ai-policy.show', $business)
            ->with('success', 'AI policy updated.');
    }

    /**
     * Reset AI policy to plan defaults for a business.
     *
     * @param  Request  $request
     * @param  Business  $business
     * @return RedirectResponse
     */
    public function resetToDefaults(Request $request, Business $business): RedirectResponse
    {
        $plan = $business->subscription?->plan;

        if (!$plan) {
            return redirect()->back()
                ->withErrors(['plan' => 'No plan found for this business.']);
        }

        $defaultFlags = $plan->feature_flags['ai'] ?? [];
        $defaults = [
            'rate_limit_per_hour' => 100,
            'rate_limit_per_day' => 1000,
            'content_safety_enabled' => true,
            'pii_detection_enabled' => true,
            'hallucination_guard_enabled' => true,
            'voice_agent_enabled' => false,
            'email_agent_enabled' => false,
            'chat_only_enabled' => false,
        ];

        $business->update([
            'ai_rate_limit_per_hour' => (int) ($defaultFlags['rate_limit_per_hour'] ?? $defaults['rate_limit_per_hour']),
            'ai_rate_limit_per_day' => (int) ($defaultFlags['rate_limit_per_day'] ?? $defaults['rate_limit_per_day']),
            'ai_content_safety_enabled' => (bool) ($defaultFlags['content_safety_enabled'] ?? $defaults['content_safety_enabled']),
            'ai_pii_detection_enabled' => (bool) ($defaultFlags['pii_detection_enabled'] ?? $defaults['pii_detection_enabled']),
            'ai_hallucination_guard_enabled' => (bool) ($defaultFlags['hallucination_guard_enabled'] ?? $defaults['hallucination_guard_enabled']),
            'ai_voice_agent_enabled' => (bool) ($defaultFlags['voice_agent_enabled'] ?? $defaults['voice_agent_enabled']),
            'ai_email_agent_enabled' => (bool) ($defaultFlags['email_agent_enabled'] ?? $defaults['email_agent_enabled']),
            'ai_chat_only_enabled' => (bool) ($defaultFlags['chat_only_enabled'] ?? $defaults['chat_only_enabled']),
            'ai_fallback_provider' => $defaultFlags['fallback_provider'] ?? null,
        ]);

        $this->auditLogger->log(
            actor: $request->user(),
            action: 'ai_policy.reset_to_defaults',
            subjectType: Business::class,
            subjectId: $business->id,
            payload: [
                'plan_code' => $plan->code,
                'plan_name' => $plan->name,
                'reset_by_user_id' => $request->user()->id,
            ],
            request: $request,
            businessId: $business->id,
        );

        return redirect()->route('admin.ai-policy.show', $business)
            ->with('success', 'AI policy reset to plan defaults.');
    }

    /**
     * Get merged feature flags from plan and business overrides.
     *
     * @return array<string, mixed>
     */
    private function getMergedFeatureFlags(Business $business, ?Plan $plan): array
    {
        $planFlags = $plan?->feature_flags['ai'] ?? [];
        $businessOverrides = [
            'rate_limit_per_hour' => $business->ai_rate_limit_per_hour,
            'rate_limit_per_day' => $business->ai_rate_limit_per_day,
            'content_safety_enabled' => $business->ai_content_safety_enabled,
            'pii_detection_enabled' => $business->ai_pii_detection_enabled,
            'hallucination_guard_enabled' => $business->ai_hallucination_guard_enabled,
            'voice_agent_enabled' => $business->ai_voice_agent_enabled,
            'email_agent_enabled' => $business->ai_email_agent_enabled,
            'chat_only_enabled' => $business->ai_chat_only_enabled,
            'fallback_provider' => $business->ai_fallback_provider,
        ];

        return array_merge($planFlags, $businessOverrides);
    }

    /**
     * Get only the fields that changed.
     *
     * @param  array<string, mixed>  $original
     * @param  array<string, mixed>  $updated
     * @return array<string, array{from: mixed, to: mixed}>
     */
    private function getChangedFields(array $original, array $updated): array
    {
        $changes = [];

        foreach ($updated as $key => $value) {
            if ($value !== null && $value !== $original[$key] ?? null) {
                $changes[$key] = [
                    'from' => $original[$key] ?? null,
                    'to' => $value,
                ];
            }
        }

        return $changes;
    }

    /**
     * Check if a business has custom AI policy overrides.
     *
     * @param  Business  $business
     * @return bool
     */
    public function hasOverrides(Business $business): bool
    {
        return $business->ai_rate_limit_per_hour !== null ||
            $business->ai_fallback_provider !== null;
    }
}
