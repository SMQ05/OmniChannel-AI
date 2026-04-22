<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\BusinessLaunchState;
use App\Models\MessagingChannelConnection;
use App\Models\Plan;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\ChannelReadinessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Manages the business (tenant) list for super_admin.
 *
 * Operations:
 *  - index()    — paginated list with search, stats per business
 *  - toggle()   — activate / deactivate a tenant
 *  - updatePlan() — change the billing plan
 */
class AdminBusinessController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {
    }

    /**
     * Render the full businesses list.
     *
     * Includes: name, plan, status, appointments this month, last active.
     *
     * @param  Request  $request
     * @return View
     */
    public function index(Request $request): View
    {
        $query = Business::query()
            ->with(['subscription.plan', 'messagingChannels', 'messagingConnections'])
            ->withCount([
                'appointments as appointments_this_month' => function ($q): void {
                    $q->whereMonth('start_time', now()->month)
                      ->whereYear('start_time', now()->year);
                },
            ])
            ->withMax('conversationLogs as last_active', 'updated_at')
            ->orderByDesc('last_active');

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'ilike', "%{$search}%")
                  ->orWhere('slug', 'ilike', "%{$search}%");
            });
        }

        if ($request->filled('plan')) {
            $query->where('plan', $request->input('plan'));
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->input('status') === 'active');
        }

        $businesses = $query->paginate(25)->withQueryString();
        $plans = Plan::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
        $channelReadinessService = app(ChannelReadinessService::class);
        $messagingStatesByBusiness = $businesses->getCollection()
            ->mapWithKeys(fn (Business $business): array => [$business->id => $channelReadinessService->forBusiness($business)])
            ->all();

        return view('admin.businesses.index', [
            'businesses' => $businesses,
            'filters'    => $request->only(['search', 'plan', 'status']),
            'plans' => $plans,
            'messagingStatesByBusiness' => $messagingStatesByBusiness,
        ]);
    }

    /**
     * Toggle a business's active status.
     *
     * @param  Request   $request
     * @param  Business  $business
     * @return JsonResponse
     */
    public function toggle(Request $request, Business $business): JsonResponse
    {
        $previousStatus = $business->is_active;
        $business->update(['is_active' => !$business->is_active]);

        // Audit log for business activation/deactivation
        $this->auditLogger->log(
            actor: $request->user(),
            action: 'business.status_changed',
            subjectType: Business::class,
            subjectId: $business->id,
            payload: [
                'previous_status' => $previousStatus ? 'active' : 'inactive',
                'new_status' => $business->is_active ? 'active' : 'inactive',
                'changed_by_user_id' => $request->user()->id,
            ],
            request: $request,
            businessId: $business->id,
        );

        return response()->json([
            'is_active' => $business->is_active,
            'message'   => $business->is_active ? 'Business activated.' : 'Business deactivated.',
        ]);
    }

    /**
     * Update a business's billing plan.
     *
     * @param  Request   $request
     * @param  Business  $business
     * @return RedirectResponse
     */
    public function updatePlan(Request $request, Business $business): RedirectResponse
    {
        $validated = $request->validate([
            'plan' => ['required', Rule::exists('plans', 'code')],
        ]);

        $plan = Plan::query()->where('code', $validated['plan'])->firstOrFail();

        $oldPlan = $business->plan;
        $business->update(['plan' => $plan->code]);
        $business->subscription()->updateOrCreate(
            [],
            [
                'plan_id' => $plan->id,
                'status' => $plan->code,
                'current_period_start' => $business->subscription?->current_period_start ?? now()->startOfMonth(),
                'current_period_end' => $business->subscription?->current_period_end ?? now()->endOfMonth(),
            ],
        );

        // Audit log for billing plan change
        $this->auditLogger->log(
            actor: $request->user(),
            action: 'billing.plan_changed',
            subjectType: Business::class,
            subjectId: $business->id,
            payload: [
                'previous_plan' => $oldPlan,
                'new_plan' => $plan->code,
                'plan_name' => $plan->name,
                'changed_by_user_id' => $request->user()->id,
            ],
            request: $request,
            businessId: $business->id,
        );

        return redirect()->route('admin.businesses.index')
            ->with('success', "{$business->name} updated to {$plan->name} plan.");
    }

    public function updateSubscription(Request $request, Business $business): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', 'max:32'],
            'warn_at_ratio' => ['required', 'numeric', 'min:0', 'max:1'],
            'enforce_limits' => ['sometimes', 'boolean'],
            'admin_override' => ['sometimes', 'boolean'],
            'included_quotas' => ['nullable', 'string'],
            'feature_flags' => ['nullable', 'string'],
            'overage_counters' => ['nullable', 'string'],
            'current_period_start' => ['nullable', 'date'],
            'current_period_end' => ['nullable', 'date', 'after_or_equal:current_period_start'],
        ]);

        $oldStatus = $business->subscription?->lifecycle_status;

        $business->subscription()->updateOrCreate(
            [],
            [
                'plan_id' => $business->subscription?->plan_id,
                'status' => $validated['status'],
                'warn_at_ratio' => (float) $validated['warn_at_ratio'],
                'enforce_limits' => $request->boolean('enforce_limits'),
                'admin_override' => $request->boolean('admin_override'),
                'included_quotas' => $this->decodeJson($validated['included_quotas'] ?? null, 'included_quotas'),
                'feature_flags' => $this->decodeJson($validated['feature_flags'] ?? null, 'feature_flags'),
                'overage_counters' => $this->decodeJson($validated['overage_counters'] ?? null, 'overage_counters'),
                'current_period_start' => $validated['current_period_start'] ?? $business->subscription?->current_period_start ?? now()->startOfMonth(),
                'current_period_end' => $validated['current_period_end'] ?? $business->subscription?->current_period_end ?? now()->endOfMonth(),
            ],
        );

        // Audit log for subscription controls update
        $this->auditLogger->log(
            actor: $request->user(),
            action: 'billing.subscription_updated',
            subjectType: Business::class,
            subjectId: $business->id,
            payload: [
                'previous_status' => $oldStatus,
                'new_status' => $validated['status'],
                'warn_at_ratio_changed' => $request->has('warn_at_ratio'),
                'admin_override_changed' => $request->has('admin_override'),
                'changed_by_user_id' => $request->user()->id,
            ],
            request: $request,
            businessId: $business->id,
        );

        return redirect()->route('admin.businesses.index')
            ->with('success', "Subscription controls updated for {$business->name}.");
    }

    public function updateOwnerPassword(Request $request, Business $business): RedirectResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'string', 'confirmed', 'min:8'],
        ]);

        $owner = $business->users()
            ->where('role', 'business_owner')
            ->orderBy('id')
            ->first() ?? $business->users()->orderBy('id')->first();

        if ($owner === null) {
            return redirect()->route('admin.businesses.index')
                ->withErrors(['owner_password' => "No user account exists for {$business->name}."]);
        }

        $oldPasswordHash = $owner->password;

        $owner->update([
            'password' => Hash::make($validated['password']),
        ]);

        // Audit log for admin-initiated password change
        $this->auditLogger->log(
            actor: $request->user(),
            action: 'admin.password_changed',
            subjectType: User::class,
            subjectId: $owner->id,
            payload: [
                'business_id' => $business->id,
                'business_name' => $business->name,
                'user_email' => $owner->email,
                'user_role' => $owner->role,
            ],
            request: $request,
            businessId: $business->id,
        );

        return redirect()->route('admin.businesses.index')
            ->with('success', "Login password updated for {$owner->email}.");
    }

    public function updateBusinessStatus(Request $request, Business $business): RedirectResponse
    {
        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $previousStatus = $business->is_active;

        $business->update(['is_active' => $validated['is_active']]);

        // Audit log for business activation/deactivation
        $this->auditLogger->log(
            actor: $request->user(),
            action: 'business.status_changed',
            subjectType: Business::class,
            subjectId: $business->id,
            payload: [
                'previous_status' => $previousStatus ? 'active' : 'inactive',
                'new_status' => $validated['is_active'] ? 'active' : 'inactive',
                'changed_by_user_id' => $request->user()->id,
            ],
            request: $request,
            businessId: $business->id,
        );

        return redirect()->route('admin.businesses.index')
            ->with('success', $validated['is_active'] ? 'Business activated.' : 'Business deactivated.');
    }

    public function updateBusinessLaunchState(Request $request, Business $business): RedirectResponse
    {
        $validated = $request->validate([
            'launch_stage' => ['required', 'string', 'in:onboarding,incomplete,configured,ready,live'],
            'can_skip_readiness' => ['nullable', 'boolean'],
        ]);

        $launchState = $business->launchState ?? $this->initializeLaunchState($business);

        $previousStage = $launchState->launch_stage;

        $launchState->update([
            'launch_stage' => $validated['launch_stage'],
            'can_skip_readiness' => $request->boolean('can_skip_readiness'),
        ]);

        // Audit log for launch stage changes
        $this->auditLogger->log(
            actor: $request->user(),
            action: 'launch.stage_changed',
            subjectType: Business::class,
            subjectId: $business->id,
            payload: [
                'previous_stage' => $previousStage,
                'new_stage' => $validated['launch_stage'],
                'can_skip_readiness' => $launchState->can_skip_readiness,
                'changed_by_user_id' => $request->user()->id,
            ],
            request: $request,
            businessId: $business->id,
        );

        return redirect()->route('admin.businesses.index')
            ->with('success', "Launch stage updated for {$business->name}.");
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

    public function updateMessagingConnection(
        Request $request,
        Business $business,
        string $channel,
        ChannelReadinessService $channelReadinessService,
        AuditLogger $auditLogger,
    ): RedirectResponse {
        abort_unless(in_array($channel, ['whatsapp', 'messenger'], true), 404);

        $validated = $request->validate($this->messagingRules($channel));
        $current = $channelReadinessService->forChannel($business, $channel);

        $provider = $channel === 'whatsapp'
            ? (string) ($validated['provider'] ?? 'meta_cloud')
            : 'meta';

        [$credentials, $runtimeConfig] = $this->messagingPayload($channel, $provider, $validated);
        $status = $channelReadinessService->connectionComplete($channel, $provider, $credentials, $runtimeConfig)
            ? 'connected'
            : 'incomplete';

        /** @var MessagingChannelConnection $connection */
        $connection = $channelReadinessService->connection($business, $channel)
            ?? new MessagingChannelConnection([
                'business_id' => $business->id,
                'channel' => $channel,
            ]);

        $connection->fill([
            'provider' => $provider,
            'status' => $status,
            'credentials' => $credentials,
            'runtime_config' => $runtimeConfig,
            'connected_at' => $status === 'connected' ? now() : null,
            'disconnected_at' => null,
            'disconnected_by_user_id' => null,
            'disconnect_reason' => null,
            'last_error' => null,
        ]);
        $connection->save();

        $auditLogger->log(
            actor: $request->user(),
            action: 'messaging.connection_saved',
            subjectType: MessagingChannelConnection::class,
            subjectId: $connection->id,
            payload: [
                'channel' => $channel,
                'provider' => $provider,
                'previous_provider' => $current['provider'],
                'status' => $status,
            ],
            request: $request,
            businessId: $business->id,
        );

        return redirect()->route('admin.businesses.index')
            ->with('success', ucfirst($channel) . ' connection saved for ' . $business->name . '.');
    }

    public function disconnectMessagingConnection(
        Request $request,
        Business $business,
        string $channel,
        ChannelReadinessService $channelReadinessService,
        AuditLogger $auditLogger,
    ): RedirectResponse {
        abort_unless(in_array($channel, ['whatsapp', 'messenger'], true), 404);

        $request->validate([
            'confirm_disconnect' => ['accepted'],
            'disconnect_reason' => ['nullable', 'string', 'max:255'],
        ]);

        $connection = $channelReadinessService->connection($business, $channel);

        if ($connection === null) {
            return redirect()->route('admin.businesses.index')
                ->withErrors(['messaging' => ucfirst($channel) . ' has no managed connection to disconnect.']);
        }

        $reason = trim((string) $request->input('disconnect_reason', ''));

        $connection->forceFill([
            'status' => 'disconnected',
            'credentials' => [],
            'runtime_config' => [],
            'connected_at' => null,
            'disconnected_at' => now(),
            'disconnected_by_user_id' => $request->user()->id,
            'disconnect_reason' => $reason !== '' ? $reason : 'Disconnected from admin controls.',
            'last_error' => null,
        ])->save();

        $auditLogger->log(
            actor: $request->user(),
            action: 'messaging.connection_disconnected',
            subjectType: MessagingChannelConnection::class,
            subjectId: $connection->id,
            payload: [
                'channel' => $channel,
                'provider' => $connection->provider,
                'reason' => $connection->disconnect_reason,
            ],
            request: $request,
            businessId: $business->id,
        );

        return redirect()->route('admin.businesses.index')
            ->with('success', ucfirst($channel) . ' connection disconnected for ' . $business->name . '.');
    }

    public function testMessagingConnection(
        Request $request,
        Business $business,
        string $channel,
        ChannelReadinessService $channelReadinessService,
    ): JsonResponse {
        abort_unless(in_array($channel, ['whatsapp', 'messenger'], true), 404);

        $state = $channelReadinessService->forChannel($business, $channel);
        $connection = $state['connection'];

        if (!$connection instanceof MessagingChannelConnection) {
            return response()->json(['success' => false, 'message' => 'No managed connection exists yet.'], 422);
        }

        try {
            $result = match (true) {
                $channel === 'whatsapp' && $state['provider'] === 'twilio' => $this->testTwilio($state['credentials']),
                default => $this->testMetaToken((string) ($state['credentials']['access_token'] ?? '')),
            };

            $connection->forceFill([
                'last_tested_at' => now(),
                'last_test_status' => $result['success'] ? 'passed' : 'failed',
                'last_test_message' => $result['message'],
                'last_error' => $result['success'] ? null : $result['message'],
            ])->save();

            return response()->json($result);
        } catch (\Throwable $exception) {
            $connection->forceFill([
                'last_tested_at' => now(),
                'last_test_status' => 'failed',
                'last_test_message' => 'Connection error: ' . $exception->getMessage(),
                'last_error' => $exception->getMessage(),
            ])->save();

            return response()->json([
                'success' => false,
                'message' => 'Connection error: ' . $exception->getMessage(),
            ], 500);
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeJson(?string $value, string $field): ?array
    {
        $trimmed = trim((string) $value);

        if ($trimmed === '') {
            return null;
        }

        /** @var mixed $decoded */
        $decoded = json_decode($trimmed, true);

        if (!is_array($decoded)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                $field => 'Must be valid JSON object or array.',
            ]);
        }

        return $decoded;
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function messagingRules(string $channel): array
    {
        if ($channel === 'whatsapp') {
            return [
                'provider' => ['required', Rule::in(['meta_cloud', 'twilio'])],
                'phone_number_id' => ['nullable', 'string', 'max:64'],
                'access_token' => ['nullable', 'string', 'max:500'],
                'verify_token' => ['nullable', 'string', 'max:255'],
                'app_secret' => ['nullable', 'string', 'max:255'],
                'twilio_account_sid' => ['nullable', 'string', 'max:255'],
                'twilio_auth_token' => ['nullable', 'string', 'max:255'],
                'twilio_from_number' => ['nullable', 'string', 'max:255'],
            ];
        }

        return [
            'page_id' => ['nullable', 'string', 'max:64'],
            'access_token' => ['nullable', 'string', 'max:500'],
            'verify_token' => ['nullable', 'string', 'max:255'],
            'app_secret' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{0: array<string, string>, 1: array<string, string>}
     */
    private function messagingPayload(string $channel, string $provider, array $validated): array
    {
        if ($channel === 'whatsapp' && $provider === 'twilio') {
            return [
                array_filter([
                    'twilio_account_sid' => (string) ($validated['twilio_account_sid'] ?? ''),
                    'twilio_auth_token' => (string) ($validated['twilio_auth_token'] ?? ''),
                ], static fn (string $value): bool => trim($value) !== ''),
                array_filter([
                    'twilio_from_number' => (string) ($validated['twilio_from_number'] ?? ''),
                ], static fn (string $value): bool => trim($value) !== ''),
            ];
        }

        if ($channel === 'whatsapp') {
            return [
                array_filter([
                    'access_token' => (string) ($validated['access_token'] ?? ''),
                    'verify_token' => (string) ($validated['verify_token'] ?? ''),
                    'app_secret' => (string) ($validated['app_secret'] ?? ''),
                ], static fn (string $value): bool => trim($value) !== ''),
                array_filter([
                    'phone_number_id' => (string) ($validated['phone_number_id'] ?? ''),
                ], static fn (string $value): bool => trim($value) !== ''),
            ];
        }

        return [
            array_filter([
                'access_token' => (string) ($validated['access_token'] ?? ''),
                'verify_token' => (string) ($validated['verify_token'] ?? ''),
                'app_secret' => (string) ($validated['app_secret'] ?? ''),
            ], static fn (string $value): bool => trim($value) !== ''),
            array_filter([
                'page_id' => (string) ($validated['page_id'] ?? ''),
            ], static fn (string $value): bool => trim($value) !== ''),
        ];
    }

    /**
     * @param  array<string, mixed>  $credentials
     * @return array{success: bool, message: string}
     */
    private function testTwilio(array $credentials): array
    {
        $sid = (string) ($credentials['twilio_account_sid'] ?? '');
        $token = (string) ($credentials['twilio_auth_token'] ?? '');

        if ($sid === '' || $token === '') {
            return ['success' => false, 'message' => 'Twilio SID or auth token is missing.'];
        }

        $response = Http::withBasicAuth($sid, $token)
            ->timeout(10)
            ->get("https://api.twilio.com/2010-04-01/Accounts/{$sid}.json");

        return $response->successful()
            ? ['success' => true, 'message' => 'Twilio connection successful.']
            : ['success' => false, 'message' => 'Twilio error: ' . ($response->json('message') ?? $response->body())];
    }

    /**
     * @return array{success: bool, message: string}
     */
    private function testMetaToken(string $accessToken): array
    {
        if ($accessToken === '') {
            return ['success' => false, 'message' => 'No access token configured.'];
        }

        $response = Http::withToken($accessToken)
            ->timeout(10)
            ->get('https://graph.facebook.com/v19.0/me');

        return $response->successful()
            ? ['success' => true, 'message' => 'Connection successful.']
            : ['success' => false, 'message' => 'Token invalid: ' . ($response->json('error.message') ?? $response->body())];
    }
}
