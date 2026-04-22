<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BillingPrice;
use App\Models\Plan;
use App\Services\Billing\BillingPriceCatalogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdminBillingPriceController extends Controller
{
    public function index(): View
    {
        return view('admin.billing.prices.index', [
            'billingPrices' => BillingPrice::query()
                ->with(['plan', 'metricRates', 'creditPolicies', 'subscriptions'])
                ->orderBy('code')
                ->orderByDesc('version')
                ->get(),
            'plans' => Plan::query()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, BillingPriceCatalogService $billingPriceCatalogService): RedirectResponse
    {
        $validated = $request->validate($this->rules());

        $billingPriceCatalogService->store(
            attributes: $this->priceAttributes($validated),
            metricRates: $this->decodeJsonArray($validated['metric_rates'] ?? null, 'metric_rates'),
            creditPolicies: $this->decodeJsonArray($validated['credit_policies'] ?? null, 'credit_policies'),
        );

        return redirect()->route('admin.billing.prices.index')
            ->with('success', 'Billing price created.');
    }

    public function update(
        Request $request,
        BillingPrice $billingPrice,
        BillingPriceCatalogService $billingPriceCatalogService,
    ): RedirectResponse {
        $validated = $request->validate($this->rules($billingPrice));

        $stored = $billingPriceCatalogService->store(
            attributes: $this->priceAttributes($validated),
            metricRates: $this->decodeJsonArray($validated['metric_rates'] ?? null, 'metric_rates'),
            creditPolicies: $this->decodeJsonArray($validated['credit_policies'] ?? null, 'credit_policies'),
            existing: $billingPrice,
        );

        $message = $stored->is($billingPrice)
            ? 'Billing price updated.'
            : 'Billing price versioned and saved as a new commercial definition.';

        return redirect()->route('admin.billing.prices.index')
            ->with('success', $message);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function rules(?BillingPrice $billingPrice = null): array
    {
        $codeRules = ['required', 'string', 'max:64'];

        if ($billingPrice === null || $billingPrice->version === 1) {
            $codeRules[] = Rule::unique('billing_prices', 'code')
                ->where(fn ($query) => $query->where('version', 1))
                ->ignore($billingPrice?->id);
        }

        return [
            'plan_id' => ['nullable', Rule::exists('plans', 'id')],
            'code' => $codeRules,
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:4000'],
            'status' => ['required', 'string', 'max:32'],
            'currency' => ['required', 'string', 'size:3'],
            'recurring_amount' => ['nullable', 'numeric', 'min:0'],
            'recurring_interval_unit' => ['nullable', Rule::in(['day', 'week', 'month', 'year'])],
            'recurring_interval_count' => ['nullable', 'integer', 'min:1'],
            'setup_fee_amount' => ['nullable', 'numeric', 'min:0'],
            'setup_fee_behavior' => ['nullable', 'string', 'max:32'],
            'trial_days' => ['nullable', 'integer', 'min:0'],
            'provider_driver' => ['nullable', 'string', 'max:64'],
            'provider_sellable_ref' => ['nullable', 'string', 'max:191'],
            'provider_variant_ref' => ['nullable', 'string', 'max:191'],
            'provider_metadata' => ['nullable', 'string'],
            'metric_rates' => ['nullable', 'string'],
            'credit_policies' => ['nullable', 'string'],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function priceAttributes(array $validated): array
    {
        return [
            'plan_id' => $validated['plan_id'] ?? null,
            'code' => $validated['code'],
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'status' => $validated['status'],
            'currency' => strtoupper((string) $validated['currency']),
            'recurring_amount_minor' => isset($validated['recurring_amount']) ? $this->toMinorUnits((string) $validated['recurring_amount']) : null,
            'recurring_interval_unit' => $validated['recurring_interval_unit'] ?? null,
            'recurring_interval_count' => $validated['recurring_interval_count'] ?? null,
            'setup_fee_amount_minor' => isset($validated['setup_fee_amount']) ? $this->toMinorUnits((string) $validated['setup_fee_amount']) : null,
            'setup_fee_behavior' => $validated['setup_fee_behavior'] ?? 'invoice_once',
            'trial_days' => $validated['trial_days'] ?? null,
            'provider_driver' => $this->emptyToNull($validated['provider_driver'] ?? null),
            'provider_sellable_ref' => $this->emptyToNull($validated['provider_sellable_ref'] ?? null),
            'provider_variant_ref' => $this->emptyToNull($validated['provider_variant_ref'] ?? null),
            'provider_metadata' => $this->decodeJsonObject($validated['provider_metadata'] ?? null, 'provider_metadata'),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function decodeJsonArray(?string $value, string $field): array
    {
        $trimmed = trim((string) $value);

        if ($trimmed === '') {
            return [];
        }

        /** @var mixed $decoded */
        $decoded = json_decode($trimmed, true);

        if (!is_array($decoded)) {
            throw ValidationException::withMessages([
                $field => 'Must be valid JSON.',
            ]);
        }

        return array_values($decoded);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeJsonObject(?string $value, string $field): ?array
    {
        $trimmed = trim((string) $value);

        if ($trimmed === '') {
            return null;
        }

        /** @var mixed $decoded */
        $decoded = json_decode($trimmed, true);

        if (!is_array($decoded)) {
            throw ValidationException::withMessages([
                $field => 'Must be valid JSON.',
            ]);
        }

        return $decoded;
    }

    private function toMinorUnits(string $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    private function emptyToNull(?string $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
