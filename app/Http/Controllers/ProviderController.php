<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Provider;
use App\Models\ProviderBlockedDate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * CRUD controller for provider management.
 *
 * Handles creating/editing providers, managing working hours,
 * blocked dates, and activation toggling.
 * All queries are automatically scoped to the authenticated tenant.
 */
class ProviderController extends Controller
{
    /**
     * Render the provider list.
     *
     * @param  Request  $request
     * @return View
     */
    public function index(Request $request): View
    {
        $providers = Provider::query()
            ->withCount('appointments')
            ->orderBy('name')
            ->get();

        return view('providers.index', ['providers' => $providers]);
    }

    /**
     * Render the provider creation form.
     *
     * @return View
     */
    public function create(): View
    {
        return view('providers.show', ['provider' => null]);
    }

    /**
     * Store a new provider.
     *
     * @param  Request  $request
     * @return RedirectResponse
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateProvider($request);
        $validated['business_id'] = $request->user()->business_id;
        $validated['is_active']   = true;

        Provider::create($validated);

        return redirect()->route('providers.index')->with('success', 'Provider created.');
    }

    /**
     * Render the provider edit/detail view.
     *
     * @param  Request   $request
     * @param  Provider  $provider
     * @return View
     */
    public function show(Request $request, Provider $provider): View
    {
        $provider->loadMissing(['blockedDates']);

        return view('providers.show', [
            'provider' => $provider,
            'timezone' => $request->user()->business->timezone,
        ]);
    }

    /**
     * Update provider details and working hours.
     *
     * @param  Request   $request
     * @param  Provider  $provider
     * @return RedirectResponse
     */
    public function update(Request $request, Provider $provider): RedirectResponse
    {
        $validated = $this->validateProvider($request);
        $provider->update($validated);

        return redirect()->route('providers.show', $provider)->with('success', 'Provider updated.');
    }

    /**
     * Toggle a provider's active status.
     *
     * @param  Provider  $provider
     * @return JsonResponse
     */
    public function toggle(Provider $provider): JsonResponse
    {
        $provider->update(['is_active' => !$provider->is_active]);

        return response()->json(['is_active' => $provider->is_active]);
    }

    /**
     * Add a blocked date for the provider.
     *
     * @param  Request   $request
     * @param  Provider  $provider
     * @return RedirectResponse
     */
    public function addBlockedDate(Request $request, Provider $provider): RedirectResponse
    {
        $validated = $request->validate([
            'blocked_date' => ['required', 'date', 'after_or_equal:today'],
            'reason'       => ['nullable', 'string', 'max:255'],
        ]);

        $provider->blockedDates()->create($validated);

        return redirect()->route('providers.show', $provider)->with('success', 'Blocked date added.');
    }

    /**
     * Remove a blocked date from the provider.
     *
     * @param  Provider             $provider
     * @param  ProviderBlockedDate  $blockedDate
     * @return RedirectResponse
     */
    public function removeBlockedDate(Provider $provider, ProviderBlockedDate $blockedDate): RedirectResponse
    {
        // Ensure the blocked date belongs to this provider
        abort_if($blockedDate->provider_id !== $provider->id, 403);

        $blockedDate->delete();

        return redirect()->route('providers.show', $provider)->with('success', 'Blocked date removed.');
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    /**
     * Validate provider create/update input.
     *
     * @param  Request  $request
     * @return array<string, mixed>
     */
    private function validateProvider(Request $request): array
    {
        return $request->validate([
            'name'                   => ['required', 'string', 'max:255'],
            'title'                  => ['nullable', 'string', 'max:100'],
            'specialization'         => ['nullable', 'string', 'max:255'],
            'slot_duration_minutes'  => ['required', 'integer', 'min:5', 'max:480'],
            'working_hours'          => ['required', 'array'],
            'working_hours.*.active' => ['required', 'boolean'],
            'working_hours.*.start'  => ['nullable', 'string', 'regex:/^\d{2}:\d{2}$/'],
            'working_hours.*.end'    => ['nullable', 'string', 'regex:/^\d{2}:\d{2}$/'],
        ]);
    }
}
