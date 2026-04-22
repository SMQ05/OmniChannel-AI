@extends('admin.layouts.admin')

@section('title', 'Onboarding - Super Admin')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h2 class="text-2xl font-bold text-white">Onboarding Overview</h2>
        <div class="flex gap-4">
            <a href="{{ route('admin.onboarding.summary') }}"
               class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-medium rounded-lg transition-colors">
                Refresh Summary
            </a>
        </div>
    </div>

    {{-- Launch Stage Summary Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
        <div class="bg-gray-800 rounded-lg p-4 border border-gray-700">
            <div class="text-sm text-gray-400">Total Businesses</div>
            <div class="text-2xl font-bold text-white mt-1">{{ $totalBusinesses }}</div>
        </div>
        <div class="bg-gray-800 rounded-lg p-4 border border-gray-700">
            <div class="text-sm text-gray-400">Launched</div>
            <div class="text-2xl font-bold text-emerald-400 mt-1">{{ $launchedBusinesses }}</div>
        </div>
        <div class="bg-gray-800 rounded-lg p-4 border border-gray-700">
            <div class="text-sm text-gray-400">Onboarding</div>
            <div class="text-2xl font-bold text-blue-400 mt-1">{{ $stages['onboarding'] ?? 0 }}</div>
        </div>
        <div class="bg-gray-800 rounded-lg p-4 border border-gray-700">
            <div class="text-sm text-gray-400">Incomplete</div>
            <div class="text-2xl font-bold text-amber-400 mt-1">{{ $stages['incomplete'] ?? 0 }}</div>
        </div>
        <div class="bg-gray-800 rounded-lg p-4 border border-gray-700">
            <div class="text-sm text-gray-400">Ready/Live</div>
            <div class="text-2xl font-bold text-emerald-400 mt-1">{{ $stages['ready'] ?? 0 + ($stages['live'] ?? 0) }}</div>
        </div>
    </div>

    {{-- Launch Stage Filter Tabs --}}
    <div class="flex gap-2 overflow-x-auto pb-2">
        <a href="{{ route('admin.onboarding.index') }}"
           class="px-4 py-2 bg-gray-700 text-white rounded-lg text-sm font-medium whitespace-nowrap {{ !request()->input('stage') ? 'ring-2 ring-red-500' : '' }}">
            All
        </a>
        @foreach(['onboarding', 'incomplete', 'configured', 'ready', 'live'] as $stage)
            <a href="{{ route('admin.onboarding.index', ['stage' => $stage]) }}"
               class="px-4 py-2 bg-gray-800 text-gray-300 hover:bg-gray-700 rounded-lg text-sm font-medium whitespace-nowrap {{ request()->input('stage') === $stage ? 'ring-2 ring-red-500' : '' }}">
                {{ ucfirst($stage) }}
            </a>
        @endforeach
    </div>

    {{-- Businesses Table --}}
    <div class="bg-gray-800 rounded-lg border border-gray-700 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-700 flex items-center justify-between">
            <h3 class="text-lg font-semibold text-white">Business Launch States</h3>
            <div class="text-sm text-gray-400">
                {{ $launchStates->total() }} businesses
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-900/50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Business</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Stage</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Onboarding</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Launch</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Readiness</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-400 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-700">
                    @foreach($launchStates as $state)
                        <tr class="hover:bg-gray-700/30 transition-colors">
                            <td class="px-6 py-4">
                                <div class="font-medium text-white">{{ $state->business?->name }}</div>
                                <div class="text-sm text-gray-400">{{ $state->business?->slug }}</div>
                            </td>
                            <td class="px-6 py-4">
                                <span class="px-2 py-1 rounded-full text-xs font-medium
                                    {{ $state->launch_stage === 'onboarding' ? 'bg-blue-500/20 text-blue-400' :
                                       ($state->launch_stage === 'incomplete' ? 'bg-amber-500/20 text-amber-400' :
                                       ($state->launch_stage === 'configured' ? 'bg-purple-500/20 text-purple-400' :
                                       ($state->launch_stage === 'ready' ? 'bg-emerald-500/20 text-emerald-400' :
                                       'bg-emerald-600/20 text-emerald-300'))) }}">
                                    {{ ucfirst($state->launch_stage) }}
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm text-gray-300">
                                    {{ $state->onboarding_completed_at ? 'Complete' : 'In Progress' }}
                                </div>
                                <div class="text-xs text-gray-500 mt-0.5">
                                    {{ $state->onboarding_started_at ? $state->onboarding_started_at->diffForHumans() : 'Not started' }}
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm text-gray-300">
                                    {{ $state->launch_approved_at ? 'Approved' : 'Pending' }}
                                </div>
                                <div class="text-xs text-gray-500 mt-0.5">
                                    {{ $state->live_at ? 'Live at: ' . $state->live_at->format('M j, Y') : '' }}
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex gap-2 text-xs">
                                    <span class="{{ $state->is_messaging_ready ? 'text-emerald-400' : 'text-red-400' }}" title="Messaging">
                                        <svg class="w-3 h-3 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
                                        {{ $state->is_messaging_ready ? 'OK' : '-' }}
                                    </span>
                                    <span class="{{ $state->is_billing_ready ? 'text-emerald-400' : 'text-red-400' }}" title="Billing">
                                        <svg class="w-3 h-3 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                                        {{ $state->is_billing_ready ? 'OK' : '-' }}
                                    </span>
                                    <span class="{{ $state->is_voice_ready ? 'text-emerald-400' : 'text-red-400' }}" title="Voice">
                                        <svg class="w-3 h-3 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"/></svg>
                                        {{ $state->is_voice_ready ? 'OK' : '-' }}
                                    </span>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <a href="{{ route('admin.onboarding.show', $state->business_id) }}"
                                   class="text-red-400 hover:text-red-300 font-medium text-sm">
                                    Manage
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        <div class="px-6 py-4 border-t border-gray-700">
            {{ $launchStates->links() }}
        </div>
    </div>
</div>
@endsection
