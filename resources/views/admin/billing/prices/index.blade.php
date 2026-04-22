<x-admin.layouts.admin title="Billing Price Catalog">
<div class="grid gap-6 xl:grid-cols-3">
    <form method="POST" action="{{ route('admin.billing.prices.store') }}" class="panel p-6 space-y-4 xl:col-span-1">
        @csrf
        <div>
            <h2 class="text-sm font-semibold text-white">Create Billing Price</h2>
            <p class="mt-1 text-xs text-gray-500">Commercial pricing is versioned and separate from plan entitlements.</p>
        </div>
        <div>
            <label class="mb-1 block text-xs text-gray-500">Plan</label>
            <select name="plan_id" class="w-full field">
                <option value="">No plan link</option>
                @foreach($plans as $plan)
                    <option value="{{ $plan->id }}">{{ $plan->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-1">
            <div>
                <label class="mb-1 block text-xs text-gray-500">Code</label>
                <input type="text" name="code" class="w-full field" placeholder="starter-monthly">
            </div>
            <div>
                <label class="mb-1 block text-xs text-gray-500">Name</label>
                <input type="text" name="name" class="w-full field" placeholder="Starter Monthly">
            </div>
            <div>
                <label class="mb-1 block text-xs text-gray-500">Status</label>
                <input type="text" name="status" value="active" class="w-full field">
            </div>
            <div>
                <label class="mb-1 block text-xs text-gray-500">Currency</label>
                <input type="text" name="currency" value="USD" class="w-full field">
            </div>
            <div>
                <label class="mb-1 block text-xs text-gray-500">Recurring Amount</label>
                <input type="number" step="0.01" min="0" name="recurring_amount" class="w-full field">
            </div>
            <div>
                <label class="mb-1 block text-xs text-gray-500">Interval Unit</label>
                <select name="recurring_interval_unit" class="w-full field">
                    <option value="month">Month</option>
                    <option value="week">Week</option>
                    <option value="day">Day</option>
                    <option value="year">Year</option>
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs text-gray-500">Interval Count</label>
                <input type="number" min="1" name="recurring_interval_count" value="1" class="w-full field">
            </div>
            <div>
                <label class="mb-1 block text-xs text-gray-500">Setup Fee</label>
                <input type="number" step="0.01" min="0" name="setup_fee_amount" class="w-full field">
            </div>
            <div>
                <label class="mb-1 block text-xs text-gray-500">Setup Fee Behavior</label>
                <input type="text" name="setup_fee_behavior" value="invoice_once" class="w-full field">
            </div>
            <div>
                <label class="mb-1 block text-xs text-gray-500">Trial Days</label>
                <input type="number" min="0" name="trial_days" class="w-full field">
            </div>
            <div>
                <label class="mb-1 block text-xs text-gray-500">Provider Driver</label>
                <input type="text" name="provider_driver" class="w-full field">
            </div>
            <div>
                <label class="mb-1 block text-xs text-gray-500">Provider Sellable Ref</label>
                <input type="text" name="provider_sellable_ref" class="w-full field">
            </div>
            <div>
                <label class="mb-1 block text-xs text-gray-500">Provider Variant Ref</label>
                <input type="text" name="provider_variant_ref" class="w-full field">
            </div>
        </div>
        <div>
            <label class="mb-1 block text-xs text-gray-500">Description</label>
            <textarea name="description" rows="3" class="w-full field"></textarea>
        </div>
        <div>
            <label class="mb-1 block text-xs text-gray-500">Provider Metadata JSON</label>
            <textarea name="provider_metadata" rows="4" class="w-full rounded-lg border border-gray-700 bg-gray-800 px-3 py-2 font-mono text-xs text-gray-100">{}</textarea>
        </div>
        <div>
            <label class="mb-1 block text-xs text-gray-500">Metric Rates JSON</label>
            <textarea name="metric_rates" rows="8" class="w-full rounded-lg border border-gray-700 bg-gray-800 px-3 py-2 font-mono text-xs text-gray-100">[]</textarea>
        </div>
        <div>
            <label class="mb-1 block text-xs text-gray-500">Credit Policies JSON</label>
            <textarea name="credit_policies" rows="6" class="w-full rounded-lg border border-gray-700 bg-gray-800 px-3 py-2 font-mono text-xs text-gray-100">[]</textarea>
        </div>
        <button type="submit" class="btn-primary w-full px-4 py-2">Create Billing Price</button>
    </form>

    <div class="space-y-4 xl:col-span-2">
        @foreach($billingPrices as $billingPrice)
            <form method="POST" action="{{ route('admin.billing.prices.update', $billingPrice) }}" class="panel p-6 space-y-4">
                @csrf
                @method('PATCH')
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h2 class="text-sm font-semibold text-white">{{ $billingPrice->name }}</h2>
                        <p class="text-xs text-gray-500">{{ $billingPrice->code }} · v{{ $billingPrice->version }} · {{ $billingPrice->plan?->name ?? 'No plan link' }}</p>
                    </div>
                    <div class="text-right text-xs text-gray-500">
                        <div>Status: {{ $billingPrice->status }}</div>
                        <div class="mt-1">{{ $billingPrice->subscriptions()->count() }} attached subscriptions</div>
                    </div>
                </div>
                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs text-gray-500">Plan</label>
                        <select name="plan_id" class="w-full field">
                            <option value="">No plan link</option>
                            @foreach($plans as $plan)
                                <option value="{{ $plan->id }}" @selected($billingPrice->plan_id === $plan->id)>{{ $plan->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs text-gray-500">Code</label>
                        <input type="text" name="code" value="{{ $billingPrice->code }}" class="w-full field">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs text-gray-500">Name</label>
                        <input type="text" name="name" value="{{ $billingPrice->name }}" class="w-full field">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs text-gray-500">Status</label>
                        <input type="text" name="status" value="{{ $billingPrice->status }}" class="w-full field">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs text-gray-500">Currency</label>
                        <input type="text" name="currency" value="{{ $billingPrice->currency }}" class="w-full field">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs text-gray-500">Recurring Amount</label>
                        <input type="number" step="0.01" min="0" name="recurring_amount" value="{{ $billingPrice->recurring_amount_minor !== null ? number_format($billingPrice->recurring_amount_minor / 100, 2, '.', '') : '' }}" class="w-full field">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs text-gray-500">Interval Unit</label>
                        <input type="text" name="recurring_interval_unit" value="{{ $billingPrice->recurring_interval_unit }}" class="w-full field">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs text-gray-500">Interval Count</label>
                        <input type="number" min="1" name="recurring_interval_count" value="{{ $billingPrice->recurring_interval_count }}" class="w-full field">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs text-gray-500">Setup Fee</label>
                        <input type="number" step="0.01" min="0" name="setup_fee_amount" value="{{ $billingPrice->setup_fee_amount_minor !== null ? number_format($billingPrice->setup_fee_amount_minor / 100, 2, '.', '') : '' }}" class="w-full field">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs text-gray-500">Setup Fee Behavior</label>
                        <input type="text" name="setup_fee_behavior" value="{{ $billingPrice->setup_fee_behavior }}" class="w-full field">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs text-gray-500">Trial Days</label>
                        <input type="number" min="0" name="trial_days" value="{{ $billingPrice->trial_days }}" class="w-full field">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs text-gray-500">Provider Driver</label>
                        <input type="text" name="provider_driver" value="{{ $billingPrice->provider_driver }}" class="w-full field">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs text-gray-500">Provider Sellable Ref</label>
                        <input type="text" name="provider_sellable_ref" value="{{ $billingPrice->provider_sellable_ref }}" class="w-full field">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs text-gray-500">Provider Variant Ref</label>
                        <input type="text" name="provider_variant_ref" value="{{ $billingPrice->provider_variant_ref }}" class="w-full field">
                    </div>
                </div>
                <div>
                    <label class="mb-1 block text-xs text-gray-500">Description</label>
                    <textarea name="description" rows="2" class="w-full field">{{ $billingPrice->description }}</textarea>
                </div>
                <div>
                    <label class="mb-1 block text-xs text-gray-500">Provider Metadata JSON</label>
                    <textarea name="provider_metadata" rows="4" class="w-full rounded-lg border border-gray-700 bg-gray-800 px-3 py-2 font-mono text-xs text-gray-100">{{ json_encode($billingPrice->provider_metadata ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</textarea>
                </div>
                <div>
                    <label class="mb-1 block text-xs text-gray-500">Metric Rates JSON</label>
                    <textarea name="metric_rates" rows="8" class="w-full rounded-lg border border-gray-700 bg-gray-800 px-3 py-2 font-mono text-xs text-gray-100">{{ json_encode($billingPrice->metricRates->map->only(['metric', 'currency', 'billable_unit', 'unit_size', 'aggregation_strategy', 'rounding_mode', 'pricing_model', 'unit_amount_minor', 'free_units', 'cap_units', 'balance_bucket', 'event_filters', 'metadata', 'is_active'])->values()->all(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</textarea>
                </div>
                <div>
                    <label class="mb-1 block text-xs text-gray-500">Credit Policies JSON</label>
                    <textarea name="credit_policies" rows="6" class="w-full rounded-lg border border-gray-700 bg-gray-800 px-3 py-2 font-mono text-xs text-gray-100">{{ json_encode($billingPrice->creditPolicies->map->only(['code', 'balance_bucket', 'currency', 'amount_minor', 'grant_cadence', 'expires_with_period', 'carry_forward', 'metadata', 'is_active'])->values()->all(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</textarea>
                </div>
                <div class="rounded-xl border border-gray-800 bg-gray-950/40 p-4 text-xs text-gray-400">
                    Once a billing price is attached to a subscription or document line, updates version the commercial definition instead of mutating historical billing terms in place.
                </div>
                <div class="flex justify-end">
                    <button type="submit" class="btn-primary px-4 py-2">Save Billing Price</button>
                </div>
            </form>
        @endforeach
    </div>
</div>
</x-admin.layouts.admin>
