@extends('admin.layouts.admin')

@section('title', 'Audit Log - ' . $auditLog->action)

@section('content')
<div class="max-w-4xl">
    <nav class="flex items-center text-sm text-gray-400 mb-6">
        <a href="{{ route('admin.audit-logs.index') }}" class="hover:text-white">Audit Logs</a>
        <svg class="w-4 h-4 mx-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        <span class="text-white">Details</span>
    </nav>

    <div class="bg-gray-800 rounded-lg border border-gray-700 p-6">
        <div class="flex items-start justify-between mb-6">
            <div>
                <h2 class="text-xl font-bold text-white">Audit Log Entry #{{ $auditLog->id }}</h2>
                <div class="mt-2 flex items-center gap-3">
                    <span class="px-3 py-1 bg-red-500/20 text-red-400 rounded-lg text-sm font-medium">
                        {{ $auditLog->action }}
                    </span>
                    <span class="text-sm text-gray-500">{{ $auditLog->created_at->format('M j, Y H:i:s') }}</span>
                </div>
            </div>
            <a href="{{ route('admin.audit-logs.export') }}" class="px-3 py-1 bg-gray-700 hover:bg-gray-600 text-white text-sm font-medium rounded-lg">
                Export All
            </a>
        </div>

        <div class="grid grid-cols-2 gap-6 mb-6">
            <div class="p-4 bg-gray-700/30 rounded-lg">
                <h4 class="text-sm font-medium text-gray-400 mb-2">Actor</h4>
                <div class="text-sm text-gray-300">
                    <div>{{ $auditLog->actorUser?->email ?? 'System' }}</div>
                    <div class="text-xs text-gray-500 mt-1">{{ $auditLog->actor_role ?? 'N/A' }}</div>
                </div>
            </div>
            <div class="p-4 bg-gray-700/30 rounded-lg">
                <h4 class="text-sm font-medium text-gray-400 mb-2">Business</h4>
                <div class="text-sm text-gray-300">
                    <div>{{ $auditLog->business?->name ?? 'N/A' }}</div>
                    <div class="text-xs text-gray-500 mt-1">ID: {{ $auditLog->business_id ?? 'N/A' }}</div>
                </div>
            </div>
            <div class="p-4 bg-gray-700/30 rounded-lg">
                <h4 class="text-sm font-medium text-gray-400 mb-2">Subject</h4>
                <div class="text-sm text-gray-300">
                    <div>{{ $auditLog->subject_type }}</div>
                    <div class="text-xs text-gray-500 mt-1">ID: {{ $auditLog->subject_id ?? 'N/A' }}</div>
                </div>
            </div>
            <div class="p-4 bg-gray-700/30 rounded-lg">
                <h4 class="text-sm font-medium text-gray-400 mb-2">Request</h4>
                <div class="text-sm text-gray-300">
                    <div class="truncate" title="{{ $auditLog->request_id }}">
                        {{ $auditLog->request_id ?? 'N/A' }}
                    </div>
                    <div class="text-xs text-gray-500 mt-1">{{ $auditLog->ip_address ?? 'N/A' }}</div>
                </div>
            </div>
        </div>

        <div class="bg-gray-700/30 rounded-lg p-4 mb-6">
            <h4 class="text-sm font-medium text-gray-400 mb-2">User Agent</h4>
            <div class="text-xs text-gray-500 font-mono break-all">{{ $auditLog->user_agent ?? 'N/A' }}</div>
        </div>

        <div class="bg-gray-700/30 rounded-lg p-4">
            <h4 class="text-sm font-medium text-gray-400 mb-2">Payload</h4>
            <pre class="text-xs text-gray-300 font-mono overflow-auto max-h-96 bg-gray-900 p-3 rounded">{{ json_encode($auditLog->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
        </div>

        <div class="mt-6 pt-6 border-t border-gray-700">
            <a href="{{ route('admin.audit-logs.index') }}" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white text-sm font-medium rounded-lg">
                Back to Audit Logs
            </a>
        </div>
    </div>
</div>
@endsection
