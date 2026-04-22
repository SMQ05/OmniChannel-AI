<x-admin.layouts.admin title="Compliance">
<div class="space-y-6">
    <section class="panel p-5">
        <p class="text-xs uppercase tracking-[0.16em] text-gray-500">Phase 8 Compliance</p>
        <h2 class="mt-2 text-lg font-semibold text-white">Governance queue, retention guardrails, and auditable execution</h2>
        <p class="mt-2 text-sm text-gray-300">
            Current architectural weakness: the shared brain is not visually or structurally centralized enough.
            This surface improves controls without changing distributed runtime and billing truth boundaries.
        </p>
    </section>

    <section class="panel p-5">
        <div class="flex items-center justify-between gap-3">
            <h3 class="text-sm font-semibold text-white">Governance Requests</h3>
            <form method="GET" action="{{ route('admin.compliance.index') }}" class="flex items-center gap-2">
                <select name="status" class="rounded-lg border border-gray-700 bg-gray-900 px-3 py-2 text-xs text-gray-200">
                    <option value="">All statuses</option>
                    @foreach(['pending_approval', 'approved', 'running', 'completed', 'failed', 'rejected'] as $status)
                        <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ $status }}</option>
                    @endforeach
                </select>
                <select name="type" class="rounded-lg border border-gray-700 bg-gray-900 px-3 py-2 text-xs text-gray-200">
                    <option value="">All types</option>
                    @foreach(['export', 'delete'] as $type)
                        <option value="{{ $type }}" @selected(($filters['type'] ?? '') === $type)>{{ $type }}</option>
                    @endforeach
                </select>
                <button class="rounded-lg bg-indigo-500 px-3 py-2 text-xs font-medium text-white">Filter</button>
            </form>
        </div>

        <div class="mt-4 overflow-x-auto">
            <table class="min-w-full text-left text-sm text-gray-300">
                <thead class="text-xs uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="pb-2 pr-4">ID</th>
                        <th class="pb-2 pr-4">Business</th>
                        <th class="pb-2 pr-4">Type</th>
                        <th class="pb-2 pr-4">Status</th>
                        <th class="pb-2 pr-4">Requested</th>
                        <th class="pb-2 pr-4">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-800">
                    @forelse($governanceRequests as $request)
                        <tr>
                            <td class="py-2 pr-4">#{{ $request->id }}</td>
                            <td class="py-2 pr-4">{{ $request->business?->name ?? 'platform' }}</td>
                            <td class="py-2 pr-4">{{ $request->request_type }}</td>
                            <td class="py-2 pr-4">{{ $request->status }}</td>
                            <td class="py-2 pr-4">{{ $request->requested_at?->diffForHumans() }}</td>
                            <td class="py-2 pr-4">
                                <a href="{{ route('admin.compliance.show', $request) }}" class="text-indigo-400 hover:text-indigo-300">Open</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-4 text-gray-500">No governance requests.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $governanceRequests->links() }}</div>
    </section>

    <section class="panel p-5">
        <h3 class="text-sm font-semibold text-white">Retention Defaults</h3>
        <p class="mt-2 text-xs text-gray-500">
            Global defaults apply first. Business overrides are bounded (min {{ $retentionBounds['min_days'] ?? '?' }} / max {{ $retentionBounds['max_days'] ?? '?' }} days).
        </p>
        <div class="mt-3 grid gap-3 md:grid-cols-2 lg:grid-cols-4 text-sm">
            <div class="panel-subtle p-3 text-gray-200">Conversation logs: {{ $retentionDefaults['conversation_logs_days'] ?? 'n/a' }}d</div>
            <div class="panel-subtle p-3 text-gray-200">Inbound webhooks: {{ $retentionDefaults['inbound_webhooks_days'] ?? 'n/a' }}d</div>
            <div class="panel-subtle p-3 text-gray-200">Outbound attempts: {{ $retentionDefaults['outbound_attempts_days'] ?? 'n/a' }}d</div>
            <div class="panel-subtle p-3 text-gray-200">Voice events: {{ $retentionDefaults['voice_events_days'] ?? 'n/a' }}d</div>
        </div>
    </section>

    <section class="panel p-5 space-y-4">
        <h3 class="text-sm font-semibold text-white">Update Business Retention Override</h3>
        <form method="POST" action="{{ route('admin.compliance.retention.update', ['business' => 0]) }}" class="grid gap-3 md:grid-cols-2"
              onsubmit="this.action='{{ url('/admin/compliance/retention') }}/'+this.business_id.value;">
            @csrf
            @method('PATCH')
            <select name="business_id" class="rounded-lg border border-gray-700 bg-gray-900 px-3 py-2 text-sm text-gray-200">
                @foreach($businesses as $business)
                    <option value="{{ $business->id }}">{{ $business->name }} ({{ $business->slug }})</option>
                @endforeach
            </select>
            <select name="deletion_strategy" class="rounded-lg border border-gray-700 bg-gray-900 px-3 py-2 text-sm text-gray-200">
                <option value="anonymize">anonymize</option>
                <option value="hard_delete">hard_delete</option>
            </select>
            <input type="number" name="conversation_logs_days" class="rounded-lg border border-gray-700 bg-gray-900 px-3 py-2 text-sm text-gray-200" placeholder="Conversation logs days">
            <input type="number" name="inbound_webhooks_days" class="rounded-lg border border-gray-700 bg-gray-900 px-3 py-2 text-sm text-gray-200" placeholder="Inbound webhooks days">
            <input type="number" name="outbound_attempts_days" class="rounded-lg border border-gray-700 bg-gray-900 px-3 py-2 text-sm text-gray-200" placeholder="Outbound attempts days">
            <input type="number" name="voice_events_days" class="rounded-lg border border-gray-700 bg-gray-900 px-3 py-2 text-sm text-gray-200" placeholder="Voice events days">
            <input type="datetime-local" name="legal_hold_until" class="rounded-lg border border-gray-700 bg-gray-900 px-3 py-2 text-sm text-gray-200 md:col-span-2">
            <button class="rounded-lg bg-indigo-500 px-3 py-2 text-sm font-medium text-white md:col-span-2">Save Override</button>
        </form>

        <form method="POST" action="{{ route('admin.compliance.retention.run') }}" class="grid gap-3 md:grid-cols-4 panel-subtle p-4">
            @csrf
            <select name="business_id" class="rounded-lg border border-gray-700 bg-gray-900 px-3 py-2 text-xs text-gray-200">
                <option value="">All businesses</option>
                @foreach($businesses as $business)
                    <option value="{{ $business->id }}">{{ $business->name }}</option>
                @endforeach
            </select>
            <input name="run_key" class="rounded-lg border border-gray-700 bg-gray-900 px-3 py-2 text-xs text-gray-200" placeholder="Optional run UUID">
            <label class="flex items-center gap-2 text-xs text-gray-300">
                <input type="checkbox" name="dry_run" value="1" checked>
                Dry run
            </label>
            <button class="rounded-lg bg-emerald-500 px-3 py-2 text-xs font-medium text-white">Run Retention</button>
        </form>
    </section>
</div>
</x-admin.layouts.admin>
