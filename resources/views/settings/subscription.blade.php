<x-layouts.app title="Usage & Plan">
<div class="space-y-6">
    <div class="panel p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h2 class="text-xl font-semibold text-white">{{ $usageSummary['plan']['name'] }} Plan</h2>
                <p class="mt-1 text-sm text-gray-400">{{ $usageSummary['plan']['description'] ?: 'Current subscription settings and metered usage for this business.' }}</p>
            </div>
            <div class="text-right text-sm text-gray-300">
                <div>Period</div>
                <div class="mt-1 text-white">{{ $usageSummary['period']['start']->format('M d') }} - {{ $usageSummary['period']['end']->format('M d, Y') }}</div>
            </div>
        </div>
    </div>

    <div class="grid gap-4 md:grid-cols-3">
        <div class="panel p-5">
            <div class="text-xs uppercase tracking-wide text-gray-500">Legacy Status</div>
            <div class="mt-2 text-3xl font-semibold text-white">{{ ucfirst($usageSummary['plan']['legacy_status']) }}</div>
            <div class="mt-2 text-xs text-gray-500">{{ $usageSummary['plan']['legacy_status_note'] }}</div>
        </div>
        <div class="panel p-5">
            <div class="text-xs uppercase tracking-wide text-gray-500">Warnings Start At</div>
            <div class="mt-2 text-3xl font-semibold text-white">{{ (int) ($usageSummary['controls']['warn_at_ratio'] * 100) }}%</div>
        </div>
        <div class="panel p-5">
            <div class="text-xs uppercase tracking-wide text-gray-500">Billing Lifecycle</div>
            <div class="mt-2 text-3xl font-semibold text-white">{{ ucfirst(str_replace('_', ' ', $usageSummary['plan']['lifecycle_status'] ?? 'unconfigured')) }}</div>
            @if(auth()->user()->canPermission('business.billing.view'))
                <a href="{{ route('settings.billing') }}" class="mt-2 inline-flex text-xs text-indigo-300 hover:text-indigo-200">Open billing truth →</a>
            @endif
        </div>
    </div>

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="xl:col-span-2 panel p-6">
            <h3 class="text-sm font-semibold text-white">Quota Usage</h3>
            <div class="mt-4 space-y-4">
                @foreach($usageSummary['metrics'] as $metric)
                    <div>
                        <div class="flex items-center justify-between gap-3 text-sm">
                            <span class="text-white">{{ $metric['label'] }}</span>
                            <span class="{{ $metric['warning'] ? 'text-amber-300' : 'text-gray-400' }}">
                                {{ number_format($metric['used'], $metric['metric'] === 'llm_tokens_estimated' ? 0 : 1) }}
                                @if($metric['limit'] !== null)
                                    / {{ number_format($metric['limit'], $metric['metric'] === 'llm_tokens_estimated' ? 0 : 1) }}
                                @else
                                    / unlimited
                                @endif
                            </span>
                        </div>
                        <div class="mt-2 h-2 overflow-hidden rounded-full bg-gray-800">
                            <div class="h-full rounded-full {{ $metric['warning'] ? 'bg-amber-400' : 'bg-indigo-500' }}" style="width: {{ $metric['ratio'] !== null ? max(min($metric['ratio'] * 100, 100), 0) : 0 }}%"></div>
                        </div>
                        <div class="mt-1 text-xs {{ $metric['allowed'] ? 'text-gray-500' : 'text-red-400' }}">
                            @if($metric['limit'] === null)
                                No hard limit configured.
                            @elseif($metric['allowed'])
                                {{ number_format($metric['remaining'], $metric['metric'] === 'llm_tokens_estimated' ? 0 : 1) }} remaining this period.
                            @else
                                Limit exceeded. This metric is subject to enforcement.
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="panel p-6">
            <h3 class="text-sm font-semibold text-white">Included Features</h3>
            <div class="mt-4 space-y-3">
                @forelse($usageSummary['feature_flags'] as $flag => $enabled)
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-white">{{ str_replace('_', ' ', ucfirst($flag)) }}</span>
                        <span class="{{ $enabled ? 'text-emerald-400' : 'text-gray-500' }}">{{ $enabled ? 'Enabled' : 'Disabled' }}</span>
                    </div>
                @empty
                    <div class="text-sm text-gray-500">No feature flags configured for this subscription.</div>
                @endforelse
            </div>
        </div>
    </div>

    <div class="panel p-6">
        <div class="mb-4 rounded-xl border border-amber-500/30 bg-amber-500/10 p-4 text-sm text-amber-100">
            <div class="font-semibold">Current architectural weakness</div>
            <div class="mt-1 text-amber-100/80">This page stays usage and quota focused. Billing lifecycle, documents, and ledger truth live on the separate Billing page because the deeper control-plane is still split across multiple systems.</div>
        </div>
        <h3 class="text-sm font-semibold text-white">Latest Metered Events</h3>
        <div class="mt-4 overflow-x-auto">
            <table class="min-w-full text-left text-sm text-gray-300">
                <thead class="text-xs uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="pb-3 pr-4">Metric</th>
                        <th class="pb-3 pr-4">Channel</th>
                        <th class="pb-3 pr-4">Quantity</th>
                        <th class="pb-3 pr-4">Status</th>
                        <th class="pb-3">Recorded</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-800">
                    @forelse($usageSummary['latest_events'] as $event)
                        <tr>
                            <td class="py-3 pr-4">{{ str_replace('_', ' ', $event->metric) }}</td>
                            <td class="py-3 pr-4">{{ $event->channel ?: 'system' }}</td>
                            <td class="py-3 pr-4">{{ number_format((float) $event->quantity, $event->metric === 'llm_tokens_estimated' ? 0 : 1) }}</td>
                            <td class="py-3 pr-4">{{ $event->status }}</td>
                            <td class="py-3">{{ optional($event->recorded_at)->diffForHumans() ?? 'n/a' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-5 text-gray-500">No usage events recorded in the current period yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
</x-layouts.app>
