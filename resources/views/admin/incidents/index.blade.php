@extends('admin.layouts.admin')

@section('title', 'Incidents - Super Admin')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h2 class="text-2xl font-bold text-white">Incident Banners</h2>
        <a href="{{ route('admin.incidents.create') }}"
           class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-medium rounded-lg transition-colors">
            Create Incident
        </a>
    </div>

    {{-- Filters --}}
    <div class="bg-gray-800 rounded-lg border border-gray-700 p-4">
        <form method="GET" class="flex flex-wrap gap-4 items-center">
            <div class="flex-1 min-w-[200px]">
                <label class="block text-xs text-gray-400 mb-1">Status</label>
                <select name="status" class="w-full bg-gray-700 text-white border border-gray-600 rounded-lg px-3 py-2 text-sm">
                    <option value="">All Statuses</option>
                    @foreach($statuses as $status)
                        <option value="{{ $status }}" {{ request('status') === $status ? 'selected' : '' }}>{{ ucfirst($status) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex-1 min-w-[200px]">
                <label class="block text-xs text-gray-400 mb-1">Severity</label>
                <select name="severity" class="w-full bg-gray-700 text-white border border-gray-600 rounded-lg px-3 py-2 text-sm">
                    <option value="">All Severities</option>
                    @foreach($severities as $severity)
                        <option value="{{ $severity }}" {{ request('severity') === $severity ? 'selected' : '' }}>{{ ucfirst($severity) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex-1 min-w-[200px]">
                <label class="block text-xs text-gray-400 mb-1">Scope</label>
                <select name="scope" class="w-full bg-gray-700 text-white border border-gray-600 rounded-lg px-3 py-2 text-sm">
                    <option value="">All Scopes</option>
                    <option value="platform" {{ request('scope') === 'platform' ? 'selected' : '' }}>Platform-wide</option>
                    <option value="business" {{ request('scope') === 'business' ? 'selected' : '' }}>Business-specific</option>
                </select>
            </div>
            <button type="submit" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white text-sm font-medium rounded-lg transition-colors">
                Filter
            </button>
        </form>
    </div>

    {{-- Incidents Table --}}
    <div class="bg-gray-800 rounded-lg border border-gray-700 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-700">
            <h3 class="text-lg font-semibold text-white">{{ $incidents->total() }} incidents</h3>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-900/50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Severity</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Title</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Scope</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Time Window</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Created</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-400 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-700">
                    @foreach($incidents as $incident)
                        <tr class="hover:bg-gray-700/30 transition-colors">
                            <td class="px-6 py-4">
                                <span class="px-2 py-1 rounded text-xs font-medium
                                    {{ $incident->statusClass() }}">
                                    {{ ucfirst($incident->status) }}
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <span class="px-2 py-1 rounded text-xs font-medium
                                    {{ $incident->severityClass() }}">
                                    {{ ucfirst($incident->severity) }}
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <div class="font-medium text-white">{{ $incident->title }}</div>
                                <div class="text-sm text-gray-400 line-clamp-2">
                                    {{ $incident->message }}
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <span class="text-sm {{ $incident->is_platform_wide ? 'text-blue-400' : 'text-amber-400' }}">
                                    {{ $incident->is_platform_wide ? 'Platform-wide' : 'Business-specific' }}
                                </span>
                                @if(!$incident->is_platform_wide && $incident->business)
                                    <div class="text-xs text-gray-500 mt-1">{{ $incident->business->name }}</div>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-300">
                                <div>{{ $incident->starts_at ? $incident->starts_at->format('M j, Y H:i') : 'Now' }}</div>
                                <div class="text-gray-500">{{ $incident->ends_at ? $incident->ends_at->format('M j, Y H:i') : 'No end' }}</div>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-400">
                                {{ $incident->created_at->diffForHumans() }}
                            </td>
                            <td class="px-6 py-4 text-right space-x-2">
                                @if($incident->status === 'draft')
                                    <form action="{{ route('admin.incidents.publish', $incident) }}" method="POST" class="inline">
                                        @csrf
                                        <button type="submit" class="px-2 py-1 bg-emerald-600 text-white text-xs font-medium rounded hover:bg-emerald-700">Publish</button>
                                    </form>
                                @elseif($incident->status === 'published')
                                    <form action="{{ route('admin.incidents.archive', $incident) }}" method="POST" class="inline">
                                        @csrf
                                        <button type="submit" class="px-2 py-1 bg-amber-600 text-white text-xs font-medium rounded hover:bg-amber-700">Archive</button>
                                    </form>
                                    <form action="{{ route('admin.incidents.resolve', $incident) }}" method="POST" class="inline">
                                        @csrf
                                        <button type="submit" class="px-2 py-1 bg-blue-600 text-white text-xs font-medium rounded hover:bg-blue-700">Resolve</button>
                                    </form>
                                @endif
                                <a href="{{ route('admin.incidents.edit', $incident) }}" class="px-2 py-1 bg-gray-600 text-white text-xs font-medium rounded hover:bg-gray-500">Edit</a>
                                <form action="{{ route('admin.incidents.destroy', $incident) }}" method="POST" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="px-2 py-1 bg-red-600 text-white text-xs font-medium rounded hover:bg-red-700" onclick="return confirm('Are you sure?')">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="px-6 py-4 border-t border-gray-700">
            {{ $incidents->links() }}
        </div>
    </div>

    {{-- Active Incidents Summary (for display on tenant dashboard) --}}
    <div class="bg-gray-800 rounded-lg border border-gray-700 p-6">
        <h3 class="text-lg font-semibold text-white mb-4">Active Incidents (Visible to Tenants)</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            @php
                $activePlatform = \App\Models\IncidentBanner::query()
                    ->where('is_platform_wide', true)
                    ->where('status', 'published')
                    ->where(function ($q) { $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()); })
                    ->where(function ($q) { $q->whereNull('ends_at')->orWhere('ends_at', '>', now()); })
                    ->count();

                $activeBusiness = \App\Models\IncidentBanner::query()
                    ->where('is_platform_wide', false)
                    ->where('status', 'published')
                    ->where(function ($q) { $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()); })
                    ->where(function ($q) { $q->whereNull('ends_at')->orWhere('ends_at', '>', now()); })
                    ->count();
            @endphp
            <div class="p-4 bg-gray-700/50 rounded-lg text-center">
                <div class="text-3xl font-bold text-red-400">{{ $activePlatform }}</div>
                <div class="text-sm text-gray-400 mt-1">Platform-wide active</div>
            </div>
            <div class="p-4 bg-gray-700/50 rounded-lg text-center">
                <div class="text-3xl font-bold text-amber-400">{{ $activeBusiness }}</div>
                <div class="text-sm text-gray-400 mt-1">Business-specific active</div>
            </div>
            <div class="p-4 bg-gray-700/50 rounded-lg text-center">
                <div class="text-3xl font-bold text-blue-400">{{ $activePlatform + $activeBusiness }}</div>
                <div class="text-sm text-gray-400 mt-1">Total active</div>
            </div>
        </div>
    </div>
</div>
@endsection
