@extends('admin.layouts.admin')

@section('title', 'Support - Super Admin')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h2 class="text-2xl font-bold text-white">Support Actions Workspace</h2>
        <div class="flex gap-2">
            <a href="{{ route('admin.support.export-audit') }}" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white text-sm font-medium rounded-lg">Export Audit</a>
        </div>
    </div>

    {{-- Support Actions Info Card --}}
    <div class="bg-emerald-900/20 border border-emerald-500/30 rounded-lg p-4">
        <div class="flex items-start gap-3">
            <svg class="w-5 h-5 text-emerald-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            <div>
                <h3 class="text-sm font-semibold text-emerald-400">Auditable Support Actions</h3>
                <p class="text-xs text-emerald-200/80 mt-1">
                    These actions are narrow in scope and properly logged. Actions like "retry all failed jobs" or "batch replay everything" are intentionally excluded.
                </p>
            </div>
        </div>
    </div>

    {{-- Search and Filter --}}
    <div class="bg-gray-800 rounded-lg border border-gray-700 p-4">
        <form method="GET" class="flex gap-4 items-center">
            <div class="flex-1">
                <input type="text" name="search" value="{{ request('search') }}"
                       placeholder="Search businesses..." class="w-full bg-gray-700 text-white border border-gray-600 rounded-lg px-4 py-2">
            </div>
            <select name="status" class="bg-gray-700 text-white border border-gray-600 rounded-lg px-3 py-2">
                <option value="">All Statuses</option>
                <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
                <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
            </select>
            <button type="submit" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white text-sm font-medium rounded-lg">Filter</button>
        </form>
    </div>

    {{-- Businesses Table --}}{{-- Businesses Table --}}
    <div class="bg-gray-800 rounded-lg border border-gray-700 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-700">
            <h3 class="text-lg font-semibold text-white">{{ $businesses->total() }} businesses</h3>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-900/50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Business</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Subscription</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Last Active</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-400 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-700">
                    @foreach($businesses as $business)
                        <tr class="hover:bg-gray-700/30 transition-colors">
                            <td class="px-6 py-4">
                                <div class="font-medium text-white">{{ $business->name }}</div>
                                <div class="text-sm text-gray-400">{{ $business->slug }}</div>
                                <div class="text-xs text-gray-500 mt-1">
                                    {{ $business->users()->count() }} users, {{ $business->appointments()->count() }} appointments
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <span class="px-2 py-1 rounded text-xs font-medium
                                    {{ $business->is_active ? 'bg-emerald-500/20 text-emerald-400' : 'bg-red-500/20 text-red-400' }}">
                                    {{ $business->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm text-gray-300">{{ $business->subscription?->plan?->name ?? 'No Plan' }}</div>
                                <div class="text-xs text-gray-500">
                                    {{ $business->subscription?->lifecycle_status ?? 'N/A' }}
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm text-gray-300">
                                    {{ $business->conversationLogs()->orderByDesc('updated_at')->first()?->updated_at ? $business->conversationLogs()->orderByDesc('updated_at')->first()->updated_at->diffForHumans() : 'Never' }}
                                </div>
                            </td>
                            <td class="px-6 py-4 text-right space-x-2">
                                <a href="{{ route('admin.support.show', $business) }}"
                                   class="px-2 py-1 bg-red-600 hover:bg-red-700 text-white text-xs font-medium rounded-lg">
                                    Open Workspace
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="px-6 py-4 border-t border-gray-700">
            {{ $businesses->links() }}
        </div>
    </div>
</div>
@endsection
