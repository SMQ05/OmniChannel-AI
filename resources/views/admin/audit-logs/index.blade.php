@extends('admin.layouts.admin')

@section('title', 'Audit Logs - Super Admin')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h2 class="text-2xl font-bold text-white">Audit Log Explorer</h2>
        <div class="flex gap-2">
            <a href="{{ route('admin.audit-logs.export') }}" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white text-sm font-medium rounded-lg">Export CSV</a>
        </div>
    </div>

    {{-- Audit Log Info Card --}}
    <div class="bg-purple-900/20 border border-purple-500/30 rounded-lg p-4">
        <div class="flex items-start gap-3">
            <svg class="w-5 h-5 text-purple-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
            <div>
                <h3 class="text-sm font-semibold text-purple-400">Comprehensive Audit Trail</h3>
                <p class="text-xs text-purple-200/80 mt-1">
                    This page shows all privileged admin actions including impersonation, support actions, credential changes, and more.
                </p>
            </div>
        </div>
    </div>

    {{-- Search and Filters --}}
    <div class="bg-gray-800 rounded-lg border border-gray-700 p-4">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs text-gray-400 mb-1">Action Category</label>
                <select name="action_category" class="w-full bg-gray-700 text-white border border-gray-600 rounded-lg px-3 py-2">
                    <option value="">All Categories</option>
                    <option value="impersonation" {{ request('action_category') === 'impersonation' ? 'selected' : '' }}>Impersonation</option>
                    <option value="support" {{ request('action_category') === 'support' ? 'selected' : '' }}>Support Actions</option>
                    <option value="credential" {{ request('action_category') === 'credential' ? 'selected' : '' }}>Credential Changes</option>
                    <option value="launch" {{ request('action_category') === 'launch' ? 'selected' : '' }}>Launch Stage</option>
                    <option value="incident" {{ request('action_category') === 'incident' ? 'selected' : '' }}>Incident Actions</option>
                    <option value="ai_policy" {{ request('action_category') === 'ai_policy' ? 'selected' : '' }}>AI Policy</option>
                    <option value="billing" {{ request('action_category') === 'billing' ? 'selected' : '' }}>Billing</option>
                </select>
            </div>
            <div>
                <label class="block text-xs text-gray-400 mb-1">Date Range</label>
                <div class="flex gap-2">
                    <input type="date" name="date_from" value="{{ request('date_from') }}"
                           class="w-1/2 bg-gray-700 text-white border border-gray-600 rounded-lg px-2 py-2">
                    <input type="date" name="date_to" value="{{ request('date_to') }}"
                           class="w-1/2 bg-gray-700 text-white border border-gray-600 rounded-lg px-2 py-2">
                </div>
            </div>
            <div class="flex gap-2 items-end">
                <button type="submit" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white text-sm font-medium rounded-lg">Filter</button>
                <a href="{{ route('admin.audit-logs.index') }}" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white text-sm font-medium rounded-lg">Reset</a>
            </div>
        </form>
    </div>

    {{-- Audit Logs Table --}}
    <div class="bg-gray-800 rounded-lg border border-gray-700 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-700">
            <h3 class="text-lg font-semibold text-white">{{ $logs->total() }} audit entries</h3>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-900/50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Time</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Actor</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Action</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Subject</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Request ID</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-400 uppercase tracking-wider">IP</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-700">
                    @foreach($logs as $log)
                        <tr class="hover:bg-gray-700/30 transition-colors">
                            <td class="px-6 py-4">
                                <div class="text-sm text-gray-300">{{ $log->created_at->format('M j, Y H:i') }}</div>
                                <div class="text-xs text-gray-500">{{ $log->created_at->diffForHumans() }}</div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="font-medium text-white">{{ $log->actorUser?->email ?? 'System' }}</div>
                                <div class="text-xs text-gray-500">{{ $log->actor_role ?? 'N/A' }}</div>
                            </td>
                            <td class="px-6 py-4">
                                <span class="px-2 py-1 rounded text-xs font-medium bg-gray-700 text-gray-300">
                                    {{ $log->action }}
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm text-gray-300">
                                    {{ Str::beforeLast($log->subject_type, '\\') }}
                                    <span class="text-gray-500">#{{ $log->subject_id }}</span>
                                </div>
                                @if($log->business)
                                    <div class="text-xs text-gray-500">{{ $log->business->name }}</div>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                <span class="text-xs font-mono text-gray-400" title="{{ $log->request_id }}">
                                    {{ Str::limit($log->request_id, 12) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <span class="text-xs text-gray-500">{{ $log->ip_address ?? '-' }}</span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="px-6 py-4 border-t border-gray-700">
            {{ $logs->links() }}
        </div>
    </div>
</div>
@endsection
