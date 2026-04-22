<x-layouts.app title="Data Controls">
<div class="space-y-6">
    <section class="panel p-6">
        <h1 class="text-xl font-semibold text-white">Data Controls</h1>
        <p class="mt-2 text-sm text-gray-300">
            Tenant-local diagnostics and governance request entrypoint.
            This page does not centralize runtime truth; messaging, voice, webhook, billing, and runtime behavior remain distributed.
        </p>
        <p class="mt-2 text-xs text-gray-500">
            Current architectural weakness: the shared brain is not visually or structurally centralized enough.
        </p>
    </section>

    <section class="grid gap-6 xl:grid-cols-2">
        <div class="panel p-6 space-y-4">
            <h2 class="text-sm font-semibold text-white">Request Export (Approval Required)</h2>
            <p class="text-xs text-gray-500">Exports are private, expiring, and scoped to this business. Admin approval and async execution are required.</p>
            <form method="POST" action="{{ route('settings.data-controls.export') }}" class="space-y-3">
                @csrf
                <textarea name="reason" class="field min-h-[90px]" placeholder="Optional reason for export request"></textarea>
                <button class="rounded-lg bg-indigo-500 px-3 py-2 text-sm font-medium text-white">Submit Export Request</button>
            </form>
        </div>

        <div class="panel p-6 space-y-4">
            <h2 class="text-sm font-semibold text-white">Request Deletion (Approval Required)</h2>
            <p class="text-xs text-gray-500">Deletion is conservative. Billing/audit/legal-retention records are preserved; scoped anonymization may apply.</p>
            <form method="POST" action="{{ route('settings.data-controls.delete') }}" class="space-y-3">
                @csrf
                <select name="mode" class="field" required>
                    <option value="anonymize">anonymize (recommended)</option>
                    <option value="hard_delete">hard_delete (policy dependent)</option>
                </select>
                <textarea name="reason" class="field min-h-[90px]" required placeholder="Required reason (min 10 chars)"></textarea>
                <button class="rounded-lg bg-red-500 px-3 py-2 text-sm font-medium text-white">Submit Deletion Request</button>
            </form>
        </div>
    </section>

    <section class="panel p-6">
        <h2 class="text-sm font-semibold text-white">Local Diagnostics (Tenant Operability)</h2>
        <div class="mt-4 grid gap-4 md:grid-cols-3 text-sm">
            <div class="panel-subtle p-4 text-gray-200">
                Queue failed jobs: {{ $diagnostics['queue']['failed_jobs_count'] ?? 'n/a' }}
            </div>
            <div class="panel-subtle p-4 text-gray-200">
                Latest inbound: {{ $diagnostics['latest_inbound']?->status ?? 'none' }}
            </div>
            <div class="panel-subtle p-4 text-gray-200">
                Voice ready: {{ ($diagnostics['voice']['ready'] ?? false) ? 'yes' : 'no' }}
            </div>
        </div>
        <p class="mt-3 text-xs text-gray-500">For cross-tenant monitoring and governance execution, admin uses the platform control plane.</p>
    </section>

    <section class="panel p-6">
        <h2 class="text-sm font-semibold text-white">Governance Requests</h2>
        <div class="mt-4 overflow-x-auto">
            <table class="min-w-full text-left text-sm text-gray-300">
                <thead class="text-xs uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="pb-2 pr-4">ID</th>
                        <th class="pb-2 pr-4">Type</th>
                        <th class="pb-2 pr-4">Status</th>
                        <th class="pb-2 pr-4">Requested</th>
                        <th class="pb-2 pr-4">Artifact</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-800">
                    @forelse($requests as $request)
                        <tr>
                            <td class="py-2 pr-4">#{{ $request->id }}</td>
                            <td class="py-2 pr-4">{{ $request->request_type }}</td>
                            <td class="py-2 pr-4">{{ $request->status }}</td>
                            <td class="py-2 pr-4">{{ $request->requested_at?->diffForHumans() }}</td>
                            <td class="py-2 pr-4">
                                @if($request->artifact_path && $request->status === 'completed' && !$request->artifactIsExpired())
                                    <a href="{{ route('settings.data-controls.artifact', $request) }}" class="text-indigo-400 hover:text-indigo-300">Download</a>
                                @elseif($request->artifact_path && $request->artifactIsExpired())
                                    <span class="text-amber-300">expired</span>
                                @else
                                    <span class="text-gray-500">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-4 text-gray-500">No governance requests yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
</x-layouts.app>
