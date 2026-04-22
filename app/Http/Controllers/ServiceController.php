<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\BusinessService;
use App\Models\Provider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ServiceController extends Controller
{
    public function index(Request $request): View
    {
        $business = $request->user()->business;

        return view('services.index', [
            'business' => $business,
            'services' => BusinessService::query()
                ->where('business_id', $business->id)
                ->withCount('providers')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
            'providers' => Provider::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $business = $request->user()->business;
        $validated = $this->validateService($request, $business->id);

        $service = BusinessService::create([
            'business_id' => $business->id,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'duration_minutes' => $validated['duration_minutes'],
            'price' => $validated['price'] ?? null,
            'is_active' => (bool) ($validated['is_active'] ?? true),
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            'booking_rules' => $this->serviceBookingRules($validated),
        ]);

        if (!empty($validated['provider_ids'])) {
            $service->providers()->sync($validated['provider_ids']);
        }

        return redirect()->route('services.show', $service)->with('success', 'Service created.');
    }

    public function show(Request $request, BusinessService $service): View
    {
        $business = $request->user()->business;
        $service->loadMissing(['providers', 'appointments']);

        return view('services.show', [
            'business' => $business,
            'service' => $service,
            'providers' => Provider::query()
                ->where('is_active', true)
                ->withCount('services')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function update(Request $request, BusinessService $service): RedirectResponse
    {
        $validated = $this->validateService($request, $request->user()->business_id, $service);

        $service->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'duration_minutes' => $validated['duration_minutes'],
            'price' => $validated['price'] ?? null,
            'is_active' => (bool) ($validated['is_active'] ?? false),
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            'booking_rules' => $this->serviceBookingRules($validated),
        ]);

        return redirect()->route('services.show', $service)->with('success', 'Service updated.');
    }

    public function toggle(BusinessService $service): JsonResponse
    {
        $service->update(['is_active' => !$service->is_active]);

        return response()->json(['is_active' => $service->is_active]);
    }

    public function syncProviders(Request $request, BusinessService $service): RedirectResponse
    {
        $businessId = $request->user()->business_id;
        $validated = $request->validate([
            'provider_ids' => ['nullable', 'array'],
            'provider_ids.*' => ['integer', Rule::exists('providers', 'id')->where('business_id', $businessId)],
        ]);

        $service->providers()->sync($validated['provider_ids'] ?? []);

        return redirect()->route('services.show', $service)->with('success', 'Provider assignments updated.');
    }

    private function validateService(Request $request, int $businessId, ?BusinessService $service = null): array
    {
        return $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('business_services', 'name')
                    ->where('business_id', $businessId)
                    ->ignore($service?->id),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'duration_minutes' => ['required', 'integer', 'min:5', 'max:480'],
            'price' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
            'provider_ids' => ['nullable', 'array'],
            'provider_ids.*' => ['integer', Rule::exists('providers', 'id')->where('business_id', $businessId)],
            'booking_rules.buffer_before_minutes' => ['nullable', 'integer', 'min:0', 'max:240'],
            'booking_rules.buffer_after_minutes' => ['nullable', 'integer', 'min:0', 'max:240'],
            'booking_rules.allow_online_booking' => ['nullable', 'boolean'],
            'booking_rules.requires_manual_confirmation' => ['nullable', 'boolean'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function serviceBookingRules(array $validated): array
    {
        $rules = $validated['booking_rules'] ?? [];

        return [
            'buffer_before_minutes' => (int) ($rules['buffer_before_minutes'] ?? 0),
            'buffer_after_minutes' => (int) ($rules['buffer_after_minutes'] ?? 0),
            'allow_online_booking' => (bool) ($rules['allow_online_booking'] ?? true),
            'requires_manual_confirmation' => (bool) ($rules['requires_manual_confirmation'] ?? false),
        ];
    }
}
