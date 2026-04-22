<x-layouts.app title="Billing">
<div class="space-y-6">
    <div class="panel p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h2 class="text-xl font-semibold text-white">Billing Visibility</h2>
                <p class="mt-1 text-sm text-gray-400">Billing truth lives here: lifecycle status, billing documents, and the balance ledger. The legacy subscription status remains visible only for compatibility.</p>
            </div>
            @if(auth()->user()->canPermission('business.billing.portal'))
                <form method="POST" action="{{ route('settings.billing.portal') }}">
                    @csrf
                    <button type="submit" class="btn-primary px-4 py-2" @disabled(!($billingSummary['account']?->portal_capable ?? false))>
                        Open Billing Portal
                    </button>
                </form>
            @endif
        </div>
        <div class="mt-4 rounded-xl border border-amber-500/30 bg-amber-500/10 p-4 text-sm text-amber-100">
            <div class="font-semibold">Current architectural weakness</div>
            <div class="mt-1 text-amber-100/80">The shared brain is not visually or structurally centralized enough. Billing improves truth and lifecycle clarity here, but messaging, voice, webhooks, and runtime control still remain split across separate surfaces.</div>
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
            <div class="text-xs uppercase tracking-wide text-gray-500">Next Invoice</div>
            <div class="mt-2 text-2xl font-semibold text-white">
                {{ optional($billingSummary['subscription']['next_invoice_at'] ?? null)?->format('M d, Y') ?? 'Not scheduled' }}
            </div>
        </div>
    </div>

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="panel p-6 xl:col-span-2">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h3 class="text-sm font-semibold text-white">Billing Documents</h3>
                    <p class="mt-1 text-xs text-gray-500">Recent invoices and billing adjustments generated from the billing catalog and rated usage events.</p>
                </div>
                <a href="{{ route('settings.subscription') }}" class="text-xs text-indigo-300 hover:text-indigo-200">Usage &amp; Plan →</a>
            </div>
            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full text-left text-sm text-gray-300">
                    <thead class="text-xs uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="pb-3 pr-4">Document</th>
                            <th class="pb-3 pr-4">Status</th>
                            <th class="pb-3 pr-4">Period</th>
                            <th class="pb-3 pr-4">Total</th>
                            <th class="pb-3">Due</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800">
                        @forelse($billingSummary['documents'] as $document)
                            <tr>
                                <td class="py-3 pr-4">
                                    <div class="font-medium text-white">{{ $document->number ?: $document->document_key }}</div>
                                    <div class="text-xs text-gray-500">{{ ucfirst($document->type) }}</div>
                                </td>
                                <td class="py-3 pr-4">{{ ucfirst(str_replace('_', ' ', $document->status)) }}</td>
                                <td class="py-3 pr-4">
                                    {{ optional($document->period_start)->format('M d') ?? 'n/a' }} - {{ optional($document->period_end)->format('M d, Y') ?? 'n/a' }}
                                </td>
                                <td class="py-3 pr-4">${{ number_format($document->total_minor / 100, 2) }}</td>
                                <td class="py-3">${{ number_format($document->amount_due_minor / 100, 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-5 text-gray-500">No billing documents exist yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="space-y-6">
            <div class="panel p-6">
                <h3 class="text-sm font-semibold text-white">Balance Ledger</h3>
                <div class="mt-4 space-y-3">
                    @forelse($billingSummary['balances'] as $bucket => $amountMinor)
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-white">{{ str_replace('_', ' ', ucfirst($bucket)) }}</span>
                            <span class="{{ $amountMinor >= 0 ? 'text-emerald-400' : 'text-red-400' }}">
                                ${{ number_format($amountMinor / 100, 2) }}
                            </span>
                        </div>
                    @empty
                        <div class="text-sm text-gray-500">No credits or balance activity posted yet.</div>
                    @endforelse
                </div>
            </div>

            <div class="panel p-6">
                <h3 class="text-sm font-semibold text-white">Estimated Overage</h3>
                <div class="mt-2 text-2xl font-semibold text-white">${{ number_format(($billingSummary['estimated_overages']['total_minor'] ?? 0) / 100, 2) }}</div>
                <div class="mt-4 space-y-3 text-sm">
                    @forelse($billingSummary['estimated_overages']['lines'] as $line)
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-white">{{ $line['description'] }}</span>
                            <span class="text-gray-400">${{ number_format(((int) $line['subtotal_minor']) / 100, 2) }}</span>
                        </div>
                    @empty
                        <div class="text-gray-500">No current rated overages for this billing window.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
</x-layouts.app>
