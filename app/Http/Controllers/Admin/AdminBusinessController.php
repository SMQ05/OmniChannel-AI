<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Plan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
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
            ->with(['subscription.plan'])
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

        return view('admin.businesses.index', [
            'businesses' => $businesses,
            'filters'    => $request->only(['search', 'plan', 'status']),
            'plans' => $plans,
        ]);
    }

    /**
     * Toggle a business's active status.
     *
     * @param  Business  $business
     * @return JsonResponse
     */
    public function toggle(Business $business): JsonResponse
    {
        $business->update(['is_active' => !$business->is_active]);

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

        $owner->update([
            'password' => Hash::make($validated['password']),
        ]);

        return redirect()->route('admin.businesses.index')
            ->with('success', "Login password updated for {$owner->email}.");
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
}
