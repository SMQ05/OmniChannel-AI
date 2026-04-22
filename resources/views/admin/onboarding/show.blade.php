@extends('admin.layouts.admin')

@section('title', 'Onboarding - ' . $business->name)

@section('content')
<div class="space-y-6">
    {{-- Breadcrumb and Action Bar --}}
    <div class="flex items-center justify-between">
        <nav class="flex items-center text-sm text-gray-400">
            <a href="{{ route('admin.onboarding.index') }}" class="hover:text-white">Onboarding</a>
            <svg class="w-4 h-4 mx-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            <span class="text-white">{{ $business->name }}</span>
        </nav>
        <div class="flex gap-2">
            @if($launchState->launch_stage !== 'ready' && $launchState->launch_stage !== 'live')
                <form action="{{ route('admin.onboarding.complete', $business) }}" method="POST">
                    @csrf
                    <button type="submit" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium rounded-lg transition-colors">
                        Complete Onboarding
                    </button>
                </form>
            @endif
            <a href="{{ route('admin.businesses.index') }}" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white text-sm font-medium rounded-lg transition-colors">
                Back to Businesses
            </a>
        </div>
    </div>

    {{-- Business Header Card --}}
    <div class="bg-gray-800 rounded-lg border border-gray-700 p-6">
        <div class="flex items-start justify-between">
            <div>
                <h2 class="text-2xl font-bold text-white">{{ $business->name }}</h2>
                <div class="mt-2 flex flex-wrap gap-2">
                    <span class="px-3 py-1 bg-gray-700 text-gray-300 rounded-lg text-sm">{{ $business->slug }}</span>
                    <span class="px-3 py-1 bg-gray-700 text-gray-300 rounded-lg text-sm">{{ $business->timezone }}</span>
                    <span class="px-3 py-1 bg-gray-700 text-gray-300 rounded-lg text-sm">{{ $business->plan }}</span>
                </div>
            </div>
            <div class="text-right">
                <div class="text-sm text-gray-400">Created</div>
                <div class="text-gray-300">{{ $business->created_at->format('M j, Y') }}</div>
            </div>
        </div>
    </div>

    {{-- Launch Stage Progress --}}
    <div class="bg-gray-800 rounded-lg border border-gray-700 p-6">
        <h3 class="text-lg font-semibold text-white mb-4">Launch Stage Progress</h3>
        <div class="relative pt-1">
            <div class="flex mb-2 items-center justify-between">
                <div>
                    <span class="text-xs font-semibold inline-block py-1 px-2 uppercase rounded text-white bg-blue-600">
                        {{ ucfirst($launchState->launch_stage) }}
                    </span>
                </div>
                <div class="text-right">
                    <span class="text-xs font-semibold inline-block text-gray-400">
                        {{ $onboardingProgress['percentage'] }}% Complete
                    </span>
                </div>
            </div>
            <div class="overflow-hidden h-2 mb-4 text-xs flex rounded bg-gray-700">
                <div style="width: {{ $onboardingProgress['percentage'] }}%" class="shadow-none flex flex-col text-center whitespace-nowrap text-white justify-center bg-blue-500 transition-all duration-500">
                </div>
            </div>
        </div>

        {{-- Steps Progress --}}
        <div class="grid grid-cols-2 md:grid-cols-3 gap-4 mt-6">
            <div class="p-4 bg-gray-700/50 rounded-lg {{ $onboardingProgress['completed_steps'] >= 1 ? 'border-emerald-500/50' : '' }}">
                <div class="text-sm font-medium text-white">AI Configured</div>
                <div class="text-xs text-gray-400 mt-1">{{ $onboardingProgress['steps']['ai_configured'] ? 'Yes' : 'No' }}</div>
            </div>
            <div class="p-4 bg-gray-700/50 rounded-lg {{ $onboardingProgress['completed_steps'] >= 2 ? 'border-emerald-500/50' : '' }}">
                <div class="text-sm font-medium text-white">AI Name Set</div>
                <div class="text-xs text-gray-400 mt-1">{{ $onboardingProgress['steps']['ai_name_set'] ? 'Yes' : 'No' }}</div>
            </div>
            <div class="p-4 bg-gray-700/50 rounded-lg {{ $onboardingProgress['completed_steps'] >= 3 ? 'border-emerald-500/50' : '' }}">
                <div class="text-sm font-medium text-white">Business Hours</div>
                <div class="text-xs text-gray-400 mt-1">{{ $onboardingProgress['steps']['business_hours_set'] ? 'Yes' : 'No' }}</div>
            </div>
            <div class="p-4 bg-gray-700/50 rounded-lg {{ $onboardingProgress['completed_steps'] >= 4 ? 'border-emerald-500/50' : '' }}">
                <div class="text-sm font-medium text-white">Availability Rules</div>
                <div class="text-xs text-gray-400 mt-1">{{ $onboardingProgress['steps']['availability_rules_set'] ? 'Yes' : 'No' }}</div>
            </div>
            <div class="p-4 bg-gray-700/50 rounded-lg {{ $onboardingProgress['completed_steps'] >= 5 ? 'border-emerald-500/50' : '' }}">
                <div class="text-sm font-medium text-white">Team Member</div>
                <div class="text-xs text-gray-400 mt-1">{{ $onboardingProgress['steps']['team_member_added'] ? 'Yes' : 'No' }}</div>
            </div>
            <div class="p-4 bg-gray-700/50 rounded-lg">
                <div class="text-sm font-medium text-white">Completed</div>
                <div class="text-xs text-emerald-400 mt-1">
                    {{ $onboardingProgress['completed_steps'] }}/{{ $onboardingProgress['total_steps'] }} steps
                </div>
            </div>
        </div>
    </div>

    {{-- Launch Readiness Summary --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="bg-gray-800 rounded-lg border border-gray-700 p-6">
            <h3 class="text-lg font-semibold text-white mb-4">Launch Readiness Score</h3>
            <div class="flex items-center gap-6">
                <div class="relative w-32 h-32 flex items-center justify-center">
                    <svg class="w-full h-full transform -rotate-90">
                        <circle cx="64" cy="64" r="60" stroke="currentColor" stroke-width="8" fill="transparent" class="text-gray-700"/>
                        <circle cx="64" cy="64" r="60" stroke="currentColor" stroke-width="8" fill="transparent"
                                stroke-dasharray="{{ $readiness['total_score'] * 3.77 }} 377"
                                class="{{ $readiness['total_score'] >= 80 ? 'text-emerald-500' : 'text-amber-500' }}"/>
                    </svg>
                    <div class="absolute text-center">
                        <div class="text-3xl font-bold text-white">{{ $readiness['total_score'] }}%</div>
                        <div class="text-xs text-gray-400 mt-1">Readiness</div>
                    </div>
                </div>
                <div class="flex-1 space-y-3">
                    @foreach($readiness['components'] as $component => $data)
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-gray-300 capitalize">{{ str_replace('_', ' ', $component) }}</span>
                            <div class="flex items-center gap-2">
                                <span class="text-gray-400">{{ $data['score'] ?? 0 }}%</span>
                                @if($data['score'] >= 80)
                                    <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                @else
                                    <svg class="w-4 h-4 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="bg-gray-800 rounded-lg border border-gray-700 p-6">
            <h3 class="text-lg font-semibold text-white mb-4">Readiness Details</h3>
            <div class="space-y-4">
                <div class="p-4 bg-gray-700/50 rounded-lg">
                    <div class="text-sm font-medium text-white mb-2">Messaging</div>
                    <div class="text-xs text-gray-400">
                        @if($readiness['components']['messaging']['errors'])
                            <div class="text-red-400 mt-1">
                                @foreach($readiness['components']['messaging']['errors'] as $error)
                                    <div class="mb-1">• {{ $error }}</div>
                                @endforeach
                            </div>
                        @else
                            <span class="text-emerald-400">All channels ready</span>
                        @endif
                    </div>
                </div>
                <div class="p-4 bg-gray-700/50 rounded-lg">
                    <div class="text-sm font-medium text-white mb-2">Billing</div>
                    <div class="text-xs text-gray-400">
                        @if($readiness['components']['billing']['status'] === 'ready')
                            <span class="text-emerald-400">Configured</span>
                        @else
                            <span class="text-red-400">{{ $readiness['components']['billing']['error'] ?? 'Not configured' }}</span>
                        @endif
                    </div>
                </div>
                <div class="p-4 bg-gray-700/50 rounded-lg">
                    <div class="text-sm font-medium text-white mb-2">Voice</div>
                    <div class="text-xs text-gray-400">
                        {{ $readiness['components']['voice']['channel_count'] }} channels,
                        {{ $readiness['components']['voice']['configured_count'] }} configured
                    </div>
                </div>
                <div class="p-4 bg-gray-700/50 rounded-lg">
                    <div class="text-sm font-medium text-white mb-2">Operations</div>
                    <div class="text-xs text-gray-400">
                        {{ $readiness['components']['operations']['has_providers'] ? 'Providers configured' : 'No providers' }},
                        {{ $readiness['components']['operations']['has_services'] ? 'Services configured' : 'No services' }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Actions --}}
    <div class="bg-gray-800 rounded-lg border border-gray-700 p-6">
        <h3 class="text-lg font-semibold text-white mb-4">Launch Actions</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            @if($launchState->launch_stage !== 'ready' && $launchState->launch_stage !== 'live')
                <form action="{{ route('admin.onboarding.approve', $business) }}" method="POST" class="p-4 border-2 border-dashed border-red-500/50 rounded-lg hover:border-red-500 transition-colors">
                    @csrf
                    <button type="submit" class="w-full text-left">
                        <div class="text-sm font-medium text-red-400 mb-1">Approve Launch</div>
                        <div class="text-xs text-gray-500">Mark this business as ready for launch</div>
                    </button>
                </form>
            @endif

            <form action="{{ route('admin.onboarding.refresh-readiness', $business) }}" method="POST" class="p-4 border-2 border-dashed border-blue-500/50 rounded-lg hover:border-blue-500 transition-colors">
                @csrf
                <button type="submit" class="w-full text-left">
                    <div class="text-sm font-medium text-blue-400 mb-1">Refresh Readiness</div>
                    <div class="text-xs text-gray-500">Update readiness snapshot</div>
                </button>
            </form>

            <form action="{{ route('admin.onboarding.reset', $business) }}" method="POST" class="p-4 border-2 border-dashed border-amber-500/50 rounded-lg hover:border-amber-500 transition-colors">
                @csrf
                <button type="submit" class="w-full text-left">
                    <div class="text-sm font-medium text-amber-400 mb-1">Reset Launch State</div>
                    <div class="text-xs text-gray-500">Reset to onboarding stage</div>
                </button>
            </form>
        </div>
    </div>

    {{-- Readiness Snapshot --}}
    @if($launchState->readiness_snapshot_at)
        <div class="bg-gray-800 rounded-lg border border-gray-700 p-6">
            <h3 class="text-lg font-semibold text-white mb-2">Last Readiness Snapshot</h3>
            <div class="text-sm text-gray-400">
                Snapshot taken: {{ $launchState->readiness_snapshot_at->diffForHumans() }}
                @if($launchState->can_skip_readiness)
                    <span class="ml-2 px-2 py-0.5 bg-amber-500/20 text-amber-400 rounded text-xs">Readiness check bypassed</span>
                @endif
            </div>
        </div>
    @endif
</div>
@endsection
