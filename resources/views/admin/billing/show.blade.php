<x-admin.layouts.admin :title="'Billing: ' . $business->name">
<div class="space-y-6">
    <div class="panel p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h2 class="text-lg font-semibold text-white">{{ $business->name }}</h2>
                <p class="mt-1 text-sm text-gray-400">Manual/admin-truth billing surface. Local documents, local ledger, lifecycle controls, and portal/account metadata are managed here.</p>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('admin.billing.index') }}" class="rounded-lg border border-gray-700 px-4 py-2 text-sm text-gray-200 hover:bg-gray-800">All Billing</a>
                <a href="{{ route('admin.billing.prices.index') }}" class="rounded-lg border border-gray-700 px-4 py-2 text-sm text-gray-200 hover:bg-gray-800">Pricing Catalog</a>
            </div>
        </div>
        <div class="mt-4 rounded-xl border border-amber-500/30 bg-amber-500/10 p-4 text-sm text-amber-100">
            <div class="font-semibold">Current architectural weakness</div>
            <div class="mt-1 text-amber-100/80">The shared brain is not visually or structurally centralized enough. Billing truth is clearer here, but messaging, voice, webhook, and runtime control still remain split across separate services and surfaces.</div>
        </div>
    </div>

    <div class="grid gap-4 md:grid-cols-4">
        <div class="panel p-5">
            <div class="text-xs uppercase tracking-wide text-gray-500">Billing Lifecycle</div>
            <div class="mt-2 text-3xl font-semibold text-white">{{ ucfirst(str_replace('_', ' ', $billingSummary['subscription']['lifecycle_status'] ?? 'unconfigured')) }}</div>
        </div>
        <div class="panel p-5">
            <div class="text-xs uppercase tracking-wide text-gray-500">Legacy Status</div>
            <div class="mt-2 text-2xl font-semibold text-white">{{ ucfirst(str_replace('_', ' ', $billingSummary['subscription']['legacy_status'] ?? 'n/a')) }}</div>
            <div class="mt-2 text-xs text-gray-500">{{ $billingSummary['subscription']['legacy_status_note'] }}</div>
        </div>
        <div class="panel p-5">
            <div class="text-xs uppercase tracking-wide text-gray-500">Open Balance</div>
            <div class="mt-2 text-3xl font-semibold text-white">${{ number_format(($billingSummary['open_balance_minor'] ?? 0) / 100, 2) }}</div>
        </div>
        <div class="panel p-5">
            <div class="text-xs uppercase tracking-wide text-gray-500">Price Version</div>
            <div class="mt-2 text-2xl font-semibold text-white">
                {{ $billingSummary['subscription']['billing_price_code'] ? $billingSummary['subscription']['billing_price_code'] . ' v' . $billingSummary['subscription']['billing_price_version'] : 'Not assigned' }}
            </div>
        </div>
    </div>

    <div class="grid gap-6 xl:grid-cols-2">
        <form method="POST" action="{{ route('admin.billing.account.update', $business) }}" class="panel p-6 space-y-4">
            @csrf
            @method('PATCH')
            <div>
                <h3 class="text-sm font-semibold text-white">Billing Account</h3>
                <p class="mt-1 text-xs text-gray-500">Opaque provider refs and portal/session capability live here. No provider webhook sync is active yet.</p>
            </div>
            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label class="mb-1 block text-xs text-gray-500">Provider Driver</label>
                    <input type="text" name="provider_driver" value="{{ old('provider_driver', $business->billingAccount?->provider_driver) }}" class="w-full field" placeholder="configured_portal">
                </div>
                <div>
                    <label class="mb-1 block text-xs text-gray-500">Provider Account Ref</label>
                    <input type="text" name="provider_account_ref" value="{{ old('provider_account_ref', $business->billingAccount?->provider_account_ref) }}" class="w-full field">
                </div>
                <div>
                    <label class="mb-1 block text-xs text-gray-500">Currency</label>
                    <input type="text" name="currency" value="{{ old('currency', $business->billingAccount?->currency ?? 'USD') }}" class="w-full field">
                </div>
                <div>
                    <label class="mb-1 block text-xs text-gray-500">Collection Status</label>
                    <input type="text" name="collection_status" value="{{ old('collection_status', $business->billingAccount?->collection_status ?? 'unconfigured') }}" class="w-full field">
                </div>
                <div>
                    <label class="mb-1 block text-xs text-gray-500">Billing Email</label>
                    <input type="email" name="billing_email" value="{{ old('billing_email', $business->billingAccount?->billing_email) }}" class="w-full field">
                </div>
                <div>
                    <label class="mb-1 block text-xs text-gray-500">Invoice Email</label>
                    <input type="email" name="invoice_email" value="{{ old('invoice_email', $business->billingAccount?->invoice_email) }}" class="w-full field">
                </div>
                <div>
                    <label class="mb-1 block text-xs text-gray-500">Default Payment State</label>
                    <input type="text" name="default_payment_state" value="{{ old('default_payment_state', $business->billingAccount?->default_payment_state ?? 'unknown') }}" class="w-full field">
                </div>
                <label class="flex items-center gap-2 pt-6 text-sm text-gray-300">
                    <input type="checkbox" name="portal_capable" value="1" {{ old('portal_capable', $business->billingAccount?->portal_capable) ? 'checked' : '' }}>
                    Portal Capable
                </label>
            </div>
            <div>
                <label class="mb-1 block text-xs text-gray-500">Provider Metadata JSON</label>
                <textarea name="provider_metadata" rows="5" class="w-full rounded-lg border border-gray-700 bg-gray-800 px-3 py-2 font-mono text-xs text-gray-100">{{ old('provider_metadata', json_encode($business->billingAccount?->provider_metadata ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) }}</textarea>
            </div>
            <div class="flex justify-end">
                <button type="submit" class="btn-primary px-4 py-2">Save Billing Account</button>
            </div>
        </form>

        <div class="panel p-6 space-y-4">
            <div>
                <h3 class="text-sm font-semibold text-white">Commercial Assignment</h3>
                <p class="mt-1 text-xs text-gray-500">Assign a versioned billing price to the existing subscription record without changing legacy status behavior.</p>
            </div>
            <form method="POST" action="{{ route('admin.billing.subscription.update', $business) }}" class="space-y-4">
                @csrf
                @method('PATCH')
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="md:col-span-2">
                        <label class="mb-1 block text-xs text-gray-500">Billing Price</label>
                        <select name="billing_price_id" class="w-full field">
                            @foreach($billingPrices as $billingPrice)
                                <option value="{{ $billingPrice->id }}" @selected(old('billing_price_id', $business->subscription?->billing_price_id) == $billingPrice->id)>
                                    {{ $billingPrice->code }} v{{ $billingPrice->version }} · {{ $billingPrice->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs text-gray-500">Billing Cycle Anchor</label>
                        <input type="datetime-local" name="billing_cycle_anchor_at" value="{{ old('billing_cycle_anchor_at', optional($business->subscription?->billing_cycle_anchor_at)->format('Y-m-d\TH:i')) }}" class="w-full field">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs text-gray-500">Next Invoice At</label>
                        <input type="datetime-local" name="next_invoice_at" value="{{ old('next_invoice_at', optional($business->subscription?->next_invoice_at)->format('Y-m-d\TH:i')) }}" class="w-full field">
                    </div>
                </div>
                <div class="flex justify-end">
                    <button type="submit" class="btn-primary px-4 py-2">Save Commercial Assignment</button>
                </div>
            </form>
            <div class="flex justify-start">
                <form method="POST" action="{{ route('admin.billing.documents.run-cycle', $business) }}">
                    @csrf
                    <button type="submit" class="rounded-lg border border-indigo-500/30 bg-indigo-500/20 px-4 py-2 text-sm font-medium text-indigo-200 hover:bg-indigo-500/30">Run Cycle Now</button>
                </form>
            </div>
        </div>
    </div>

    <div class="grid gap-6 xl:grid-cols-2">
        <form method="POST" action="{{ route('admin.billing.ledger.adjustment.store', $business) }}" class="panel p-6 space-y-4">
            @csrf
            <div>
                <h3 class="text-sm font-semibold text-white">Manual Ledger Adjustment</h3>
                <p class="mt-1 text-xs text-gray-500">Manual admin-truth credit and debit actions write into the canonical billing balance ledger.</p>
            </div>
            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label class="mb-1 block text-xs text-gray-500">Adjustment Type</label>
                    <select name="entry_type" class="w-full field">
                        <option value="manual_credit">Manual Credit</option>
                        <option value="manual_debit">Manual Debit</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs text-gray-500">Balance Bucket</label>
                    <input type="text" name="balance_bucket" value="account_credit" class="w-full field">
                </div>
                <div>
                    <label class="mb-1 block text-xs text-gray-500">Amount</label>
                    <input type="number" name="amount" min="0.01" step="0.01" class="w-full field">
                </div>
                <div>
                    <label class="mb-1 block text-xs text-gray-500">Document Link</label>
                    <select name="billing_document_id" class="w-full field">
                        <option value="">None</option>
                        @foreach($billingSummary['documents'] as $document)
                            <option value="{{ $document->id }}">{{ $document->number ?: $document->document_key }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div>
                <label class="mb-1 block text-xs text-gray-500">Reason</label>
                <input type="text" name="reason" class="w-full field" placeholder="Manual service recovery credit">
            </div>
            <div class="flex items-start justify-between gap-6">
                <div class="space-y-2 text-sm">
                    <div class="font-medium text-white">Current Balances</div>
                    @forelse($billingSummary['balances'] as $bucket => $amountMinor)
                        <div class="flex items-center justify-between gap-3 text-gray-400">
                            <span>{{ $bucket }}</span>
                            <span>${{ number_format($amountMinor / 100, 2) }}</span>
                        </div>
                    @empty
                        <div class="text-gray-500">No ledger balances yet.</div>
                    @endforelse
                </div>
                <button type="submit" class="btn-primary px-4 py-2">Apply Ledger Entry</button>
            </div>
        </form>

        <div class="panel p-6 space-y-4">
            <div>
                <h3 class="text-sm font-semibold text-white">Lifecycle Controls</h3>
                <p class="mt-1 text-xs text-gray-500">Suspension blocks new outbound sends, reminders, and new voice sessions. Inbound webhooks still record.</p>
            </div>
            <div class="rounded-xl border border-gray-800 p-4 text-sm text-gray-300">
                <div>Lifecycle status: <span class="font-semibold text-white">{{ ucfirst(str_replace('_', ' ', $billingSummary['subscription']['lifecycle_status'] ?? 'unconfigured')) }}</span></div>
                <div class="mt-2">Legacy compatibility status: <span class="font-semibold text-white">{{ ucfirst(str_replace('_', ' ', $billingSummary['subscription']['legacy_status'] ?? 'n/a')) }}</span></div>
            </div>
            <form method="POST" action="{{ route('admin.billing.suspend', $business) }}" class="space-y-3 rounded-xl border border-red-500/30 bg-red-500/10 p-4">
                @csrf
                @method('PATCH')
                <div class="text-sm font-semibold text-red-200">Suspend Billing Lifecycle</div>
                <input type="text" name="reason" class="w-full field" placeholder="Past due manual suspension">
                <label class="flex items-center gap-2 text-sm text-red-100">
                    <input type="checkbox" name="confirm_suspend" value="1">
                    Confirm suspension
                </label>
                <button type="submit" class="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-500">Suspend</button>
            </form>
            <form method="POST" action="{{ route('admin.billing.reactivate', $business) }}" class="space-y-3 rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-4">
                @csrf
                @method('PATCH')
                <div class="text-sm font-semibold text-emerald-200">Reactivate Billing Lifecycle</div>
                <label class="flex items-center gap-2 text-sm text-emerald-100">
                    <input type="checkbox" name="confirm_reactivate" value="1">
                    Confirm reactivation
                </label>
                <button type="submit" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-500">Reactivate</button>
            </form>
        </div>
    </div>

    <div class="panel p-6">
        <h3 class="text-sm font-semibold text-white">Billing Documents</h3>
        <div class="mt-4 overflow-x-auto">
            <table class="min-w-full text-left text-sm text-gray-300">
                <thead class="text-xs uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="pb-3 pr-4">Document</th>
                        <th class="pb-3 pr-4">Status</th>
                        <th class="pb-3 pr-4">Total</th>
                        <th class="pb-3 pr-4">Due</th>
                        <th class="pb-3 pr-4">Issued</th>
                        <th class="pb-3">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-800">
                    @forelse($billingSummary['documents'] as $document)
                        <tr>
                            <td class="py-3 pr-4">
                                <div class="font-medium text-white">{{ $document->number ?: $document->document_key }}</div>
                                <div class="text-xs text-gray-500">{{ optional($document->period_start)->format('M d') ?? 'n/a' }} - {{ optional($document->period_end)->format('M d, Y') ?? 'n/a' }}</div>
                            </td>
                            <td class="py-3 pr-4">{{ ucfirst(str_replace('_', ' ', $document->status)) }}</td>
                            <td class="py-3 pr-4">${{ number_format($document->total_minor / 100, 2) }}</td>
                            <td class="py-3 pr-4">${{ number_format($document->amount_due_minor / 100, 2) }}</td>
                            <td class="py-3 pr-4">{{ optional($document->issued_at)->format('M d, Y') ?? 'Draft' }}</td>
                            <td class="py-3">
                                @if($document->amount_due_minor > 0)
                                    <form method="POST" action="{{ route('admin.billing.documents.mark-paid', $document) }}" class="flex flex-wrap items-center gap-2">
                                        @csrf
                                        @method('PATCH')
                                        <input type="number" name="amount" step="0.01" min="0.01" placeholder="{{ number_format($document->amount_due_minor / 100, 2) }}" class="field max-w-28">
                                        <input type="text" name="reference" placeholder="Reference" class="field max-w-40">
                                        <button type="submit" class="rounded-lg border border-gray-700 px-3 py-2 text-xs font-medium text-gray-200 hover:bg-gray-800">Mark Paid</button>
                                    </form>
                                @else
                                    <span class="text-xs text-emerald-400">No amount due</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-5 text-gray-500">No billing documents have been generated yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
</x-admin.layouts.admin>
