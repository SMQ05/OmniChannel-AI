<x-admin.layouts.admin title="Monitoring">
<div class="space-y-6">
    <section class="panel p-5">
        <p class="text-xs uppercase tracking-[0.16em] text-gray-500">Phase 8 Monitoring</p>
        <h2 class="mt-2 text-lg font-semibold text-white">Derived control-plane snapshots only</h2>
        <p class="mt-2 text-sm text-gray-300">
            Current architectural weakness: the shared brain is not visually or structurally centralized enough.
            Monitoring snapshots are derived aggregates for visibility only, not runtime or billing truth.
        </p>
        <div class="mt-4 flex flex-wrap items-center gap-3">
            <form method="POST" action="{{ route('admin.monitoring.capture-platform') }}">
                @csrf
                <button class="rounded-lg bg-indigo-500 px-3 py-2 text-sm font-medium text-white">Capture Platform Snapshot</button>
            </form>

            <form method="POST" action="{{ route('admin.monitoring.capture-tenant', ['business' => 0]) }}" class="flex items-center gap-2"
                  onsubmit="this.action='{{ url('/admin/monitoring/capture/tenant') }}/'+this.business_id.value;">
                @csrf
                <select name="business_id" class="rounded-lg border border-gray-700 bg-gray-900 px-3 py-2 text-sm text-gray-200">
                    @foreach($businesses as $business)
                        <option value="{{ $business->id }}">{{ $business->name }}</option>
                    @endforeach
                </select>
                <button class="rounded-lg bg-emerald-500 px-3 py-2 text-sm font-medium text-white">Capture Tenant Snapshot</button>
            </form>
        </div>
    </section>

    <section class="panel p-5">
        <h3 class="text-sm font-semibold text-white">Latest Platform Snapshot</h3>
        @if($latestPlatformSnapshot)
            @php($agg = $latestPlatformSnapshot->aggregates ?? [])
            <div class="mt-3 grid gap-3 md:grid-cols-2 lg:grid-cols-4 text-sm">
                <div class="panel-subtle p-3 text-gray-200">Businesses: {{ $agg['businesses']['total'] ?? 'n/a' }}</div>
                <div class="panel-subtle p-3 text-gray-200">Pending Governance: {{ $agg['governance']['pending_approval'] ?? 'n/a' }}</div>
                <div class="panel-subtle p-3 text-gray-200">Failed Outbound (24h): {{ $agg['messaging']['failed_outbound_last_24h'] ?? 'n/a' }}</div>
                <div class="panel-subtle p-3 text-gray-200">Captured: {{ $latestPlatformSnapshot->captured_at?->diffForHumans() }}</div>
            </div>
            <p class="mt-3 text-xs text-gray-500">{{ $agg['runtime_truth_note'] ?? '' }}</p>
        @else
            <p class="mt-3 text-sm text-gray-400">No platform snapshot captured yet.</p>
        @endif
    </section>

    <section class="panel p-5">
        <h3 class="text-sm font-semibold text-white">Recent Tenant Snapshots</h3>
        <div class="mt-4 overflow-x-auto">
            <table class="min-w-full text-left text-sm text-gray-300">
                <thead class="text-xs uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="pb-2 pr-4">Business</th>
                        <th class="pb-2 pr-4">Issues</th>
                        <th class="pb-2 pr-4">Voice Ready</th>
                        <th class="pb-2 pr-4">Channel Ready</th>
                        <th class="pb-2 pr-4">Captured</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-800">
                    @forelse($tenantSnapshots as $snapshot)
                        @php($agg = $snapshot->aggregates ?? [])
                        <tr>
                            <td class="py-2 pr-4">{{ $snapshot->business?->name ?? 'n/a' }}</td>
                            <td class="py-2 pr-4">{{ $agg['issues']['count'] ?? 'n/a' }}</td>
                            <td class="py-2 pr-4">{{ ($agg['voice']['ready'] ?? false) ? 'yes' : 'no' }}</td>
                            <td class="py-2 pr-4">{{ $agg['channels']['ready'] ?? 0 }}/{{ $agg['channels']['total'] ?? 0 }}</td>
                            <td class="py-2 pr-4">{{ $snapshot->captured_at?->diffForHumans() }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-4 text-gray-500">No tenant snapshots captured yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
</x-admin.layouts.admin>
