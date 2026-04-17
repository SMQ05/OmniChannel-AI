<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Business;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        return view('admin.businesses.index', [
            'businesses' => $businesses,
            'filters'    => $request->only(['search', 'plan', 'status']),
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
            'plan' => ['required', Rule::in(['trial', 'starter', 'pro', 'enterprise'])],
        ]);

        $business->update($validated);

        return redirect()->route('admin.businesses.index')
            ->with('success', "{$business->name} updated to {$validated['plan']} plan.");
    }
}
