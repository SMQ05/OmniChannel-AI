<x-admin.layouts.admin :title="'Compliance Request #'.$governanceRequest->id">
<div class="space-y-6">
    <section class="panel p-5">
        <h2 class="text-lg font-semibold text-white">Request #{{ $governanceRequest->id }}</h2>
        <p class="mt-2 text-sm text-gray-300">
            {{ $governanceRequest->request_type }} request for {{ $governanceRequest->business?->name ?? 'platform' }}.
            Status: <span class="font-semibold">{{ $governanceRequest->status }}</span>.
        </p>
        <p class="mt-2 text-xs text-gray-500">
            This workflow is approval-driven and async. Execution results are audit logged and do not redefine runtime truth.
        </p>
    </section>

    <section class="panel p-5 grid gap-3 md:grid-cols-2 text-sm text-gray-300">
        <div><span class="text-gray-500">Requested by:</span> {{ $governanceRequest->requestedBy?->email ?? 'system' }}</div>
        <div><span class="text-gray-500">Requested at:</span> {{ $governanceRequest->requested_at?->toDateTimeString() }}</div>
        <div><span class="text-gray-500">Approved by:</span> {{ $governanceRequest->approvedBy?->email ?? '—' }}</div>
        <div><span class="text-gray-500">Executed by:</span> {{ $governanceRequest->executedBy?->email ?? '—' }}</div>
        <div class="md:col-span-2"><span class="text-gray-500">Reason:</span> {{ $governanceRequest->request_reason ?? '—' }}</div>
        <div class="md:col-span-2"><span class="text-gray-500">Filters:</span> <pre class="mt-1 whitespace-pre-wrap text-xs text-gray-400">{{ json_encode($governanceRequest->requested_filters, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre></div>
        <div class="md:col-span-2"><span class="text-gray-500">Execution policy:</span> <pre class="mt-1 whitespace-pre-wrap text-xs text-gray-400">{{ json_encode($governanceRequest->execution_policy, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre></div>
        <div class="md:col-span-2"><span class="text-gray-500">Result summary:</span> <pre class="mt-1 whitespace-pre-wrap text-xs text-gray-400">{{ json_encode($governanceRequest->result_summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre></div>
    </section>

    <section class="panel p-5">
        <div class="flex flex-wrap gap-3">
            @if($governanceRequest->status === 'pending_approval')
                <form method="POST" action="{{ route('admin.compliance.approve', $governanceRequest) }}" class="flex items-center gap-2">
                    @csrf
                    <input name="approval_reason" class="rounded-lg border border-gray-700 bg-gray-900 px-3 py-2 text-xs text-gray-200" placeholder="Approval note (optional)">
                    <button class="rounded-lg bg-emerald-500 px-3 py-2 text-xs font-medium text-white">Approve + Queue</button>
                </form>

                <form method="POST" action="{{ route('admin.compliance.reject', $governanceRequest) }}" class="flex items-center gap-2">
                    @csrf
                    <input name="rejection_reason" class="rounded-lg border border-gray-700 bg-gray-900 px-3 py-2 text-xs text-gray-200" placeholder="Rejection reason" required>
                    <button class="rounded-lg bg-red-500 px-3 py-2 text-xs font-medium text-white">Reject</button>
                </form>
            @endif

            @if(in_array($governanceRequest->status, ['approved', 'failed'], true))
                <form method="POST" action="{{ route('admin.compliance.run', $governanceRequest) }}">
                    @csrf
                    <button class="rounded-lg bg-indigo-500 px-3 py-2 text-xs font-medium text-white">Queue Execution</button>
                </form>
            @endif

            @if($governanceRequest->artifact_path && $governanceRequest->status === 'completed' && !$governanceRequest->artifactIsExpired())
                <a href="{{ route('admin.compliance.artifact', $governanceRequest) }}"
                   class="rounded-lg bg-gray-700 px-3 py-2 text-xs font-medium text-white hover:bg-gray-600">
                    Download Artifact
                </a>
            @endif
        </div>
    </section>
</div>
</x-admin.layouts.admin>
