<x-layouts.app title="Dashboard">

{{-- =====================================================================
     STAT CARDS — Alpine.js polls /dashboard/stats every 30 seconds
     ===================================================================== --}}
<div
    x-data="{
        stats: @js($stats),
        async refresh() {
            const res = await fetch('{{ route('dashboard.stats') }}', {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const data = await res.json();
            this.stats = data.stats;
        }
    }"
    x-init="setInterval(() => refresh(), 30000)"
>
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">

        {{-- Today's Bookings --}}
        <div class="relative overflow-hidden rounded-2xl bg-gray-900/60 backdrop-blur border border-gray-800 p-5">
            <div class="absolute inset-0 bg-gradient-to-br from-indigo-600/10 to-transparent pointer-events-none"></div>
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Today's Bookings</p>
            <p class="text-4xl font-bold text-white" x-text="stats.bookings_today">{{ $stats['bookings_today'] }}</p>
            <div class="mt-2 h-0.5 bg-indigo-500/30 rounded"></div>
        </div>

        {{-- This Week --}}
        <div class="relative overflow-hidden rounded-2xl bg-gray-900/60 backdrop-blur border border-gray-800 p-5">
            <div class="absolute inset-0 bg-gradient-to-br from-emerald-600/10 to-transparent pointer-events-none"></div>
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">This Week</p>
            <p class="text-4xl font-bold text-white" x-text="stats.bookings_this_week">{{ $stats['bookings_this_week'] }}</p>
            <div class="mt-2 h-0.5 bg-emerald-500/30 rounded"></div>
        </div>

        {{-- Pending Handoffs --}}
        <div class="relative overflow-hidden rounded-2xl bg-gray-900/60 backdrop-blur border border-gray-800 p-5">
            <div class="absolute inset-0 bg-gradient-to-br from-red-600/10 to-transparent pointer-events-none"></div>
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Pending Handoffs</p>
            <p class="text-4xl font-bold text-white" x-text="stats.pending_handoffs">{{ $stats['pending_handoffs'] }}</p>
            <div class="mt-2 h-0.5 bg-red-500/30 rounded"></div>
        </div>

        {{-- Messages Today --}}
        <div class="relative overflow-hidden rounded-2xl bg-gray-900/60 backdrop-blur border border-gray-800 p-5">
            <div class="absolute inset-0 bg-gradient-to-br from-purple-600/10 to-transparent pointer-events-none"></div>
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Messages Today</p>
            <p class="text-4xl font-bold text-white" x-text="stats.messages_today">{{ $stats['messages_today'] }}</p>
            <div class="mt-2 h-0.5 bg-purple-500/30 rounded"></div>
        </div>
    </div>
</div>

@php
    $dashboardUsageMetrics = collect($usageSummary['metrics'])
        ->keyBy('metric')
        ->only(['messages_received', 'messages_sent', 'reminders_sent'])
        ->values();
@endphp

<div class="mb-6 grid grid-cols-1 xl:grid-cols-2 gap-6">
    <div class="rounded-2xl bg-gray-900/60 backdrop-blur border border-gray-800 p-5">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="text-sm font-semibold text-white">Plan & Usage</h2>
                <p class="mt-1 text-xs text-gray-500">{{ $usageSummary['plan']['name'] }} plan · {{ ucfirst($usageSummary['plan']['status']) }}</p>
            </div>
            <a href="{{ route('settings.subscription') }}" class="text-xs text-indigo-400 hover:text-indigo-300">Open usage →</a>
        </div>

        <div class="mt-4 space-y-4">
            @foreach($dashboardUsageMetrics as $metric)
                <div>
                    <div class="flex items-center justify-between gap-3 text-xs">
                        <span class="text-gray-300">{{ $metric['label'] }}</span>
                        <span class="{{ $metric['warning'] ? 'text-amber-300' : 'text-gray-500' }}">
                            {{ floor($metric['used']) == $metric['used'] ? number_format($metric['used'], 0) : number_format($metric['used'], 1) }}
                            @if($metric['limit'] !== null)
                                / {{ floor($metric['limit']) == $metric['limit'] ? number_format($metric['limit'], 0) : number_format($metric['limit'], 1) }}
                            @endif
                        </span>
                    </div>
                    <div class="mt-2 h-2 overflow-hidden rounded-full bg-gray-800">
                        <div class="h-full rounded-full {{ $metric['warning'] ? 'bg-amber-400' : 'bg-indigo-500' }}" style="width: {{ $metric['ratio'] !== null ? max(min($metric['ratio'] * 100, 100), 0) : 0 }}%"></div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="rounded-2xl bg-gray-900/60 backdrop-blur border border-gray-800 p-5">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="text-sm font-semibold text-white">Voice Rollout</h2>
                <p class="mt-1 text-xs text-gray-500">Feature-flagged product surface for phone and SIP call handling.</p>
            </div>
            <a href="{{ route('settings.voice') }}" class="text-xs text-indigo-400 hover:text-indigo-300">Open voice →</a>
        </div>

        <div class="mt-4 grid grid-cols-2 gap-4">
            <div class="rounded-xl border border-gray-800 p-4">
                <div class="text-xs uppercase tracking-wide text-gray-500">Readiness</div>
                <div class="mt-2 text-lg font-semibold {{ $voiceState['ready'] ? 'text-emerald-400' : 'text-amber-300' }}">{{ $voiceState['ready'] ? 'Ready' : 'Needs setup' }}</div>
            </div>
            <div class="rounded-xl border border-gray-800 p-4">
                <div class="text-xs uppercase tracking-wide text-gray-500">Active Channels</div>
                <div class="mt-2 text-lg font-semibold text-white">{{ $voiceState['summary']['active_channels'] }}</div>
            </div>
        </div>

        <div class="mt-4 text-xs text-gray-500">
            @if($voiceState['issues'] === [])
                Voice configuration is aligned with the current plan and platform provider readiness.
            @else
                {{ \Illuminate\Support\Str::limit(implode(' ', $voiceState['issues']), 180) }}
            @endif
        </div>
    </div>
</div>

<div class="grid grid-cols-1 xl:grid-cols-3 gap-6">

    {{-- =====================================================================
         TODAY'S APPOINTMENTS FEED
         ===================================================================== --}}
    <div class="xl:col-span-2 rounded-2xl bg-gray-900/60 backdrop-blur border border-gray-800 overflow-hidden"
         x-data="{
             appointments: @js($todayAppointments->resolve(request())),
             async refresh() {
                 const res = await fetch('{{ route('appointments.feed') }}', {
                     headers: { 'X-Requested-With': 'XMLHttpRequest' }
                 });
                 this.appointments = await res.json();
             }
         }"
         x-init="setInterval(() => refresh(), 30000)">

        <div class="px-5 py-4 border-b border-gray-800 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-white">Today's Schedule</h2>
            <a href="{{ route('appointments.index') }}" class="text-xs text-indigo-400 hover:text-indigo-300">View all →</a>
        </div>

        <div class="divide-y divide-gray-800">
            <template x-for="appt in appointments" :key="appt.id">
                <div class="flex items-center gap-4 px-5 py-3.5 hover:bg-gray-800/40 transition-colors">
                    <div class="w-1 h-10 rounded-full flex-shrink-0"
                         :style="'background-color: ' + (appt.provider?.color ?? '#6366f1')"></div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-white truncate" x-text="appt.patient?.name ?? '—'"></p>
                        <p class="text-xs text-gray-500" x-text="appt.service_type + ' · ' + (appt.provider?.display_name ?? '')"></p>
                    </div>
                    <div class="text-right flex-shrink-0">
                        <p class="text-sm font-medium text-gray-300" x-text="appt.time_display"></p>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium"
                              :class="{
                                  'bg-emerald-500/20 text-emerald-400': appt.status === 'confirmed',
                                  'bg-gray-500/20 text-gray-400':       appt.status === 'completed',
                                  'bg-red-500/20 text-red-400':         appt.status === 'cancelled',
                              }"
                              x-text="appt.status">
                        </span>
                    </div>
                </div>
            </template>

            <template x-if="appointments.length === 0">
                <div class="px-5 py-10 text-center text-gray-600 text-sm">
                    No appointments scheduled for today.
                </div>
            </template>
        </div>
    </div>

    {{-- =====================================================================
         HUMAN HANDOFF ALERT PANEL
         ===================================================================== --}}
    <div class="rounded-2xl bg-gray-900/60 backdrop-blur border border-gray-800 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-800 flex items-center gap-2">
            @if($pendingHandoffs->count() > 0)
                <span class="w-2 h-2 bg-red-400 rounded-full"></span>
            @endif
            <h2 class="text-sm font-semibold text-white">Human Handoffs</h2>
        </div>

        <div class="divide-y divide-gray-800">
            @forelse($pendingHandoffs as $handoff)
                <div class="px-5 py-3.5">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-white truncate">
                                {{ $handoff->patient?->name ?? 'Unknown' }}
                            </p>
                            <p class="text-xs text-gray-500 mt-0.5">
                                {{ ucfirst($handoff->channel) }} ·
                                {{ $handoff->updated_at->diffForHumans() }}
                            </p>
                        </div>
                        <a href="{{ route('conversations.show', $handoff) }}"
                           class="flex-shrink-0 px-3 py-1 bg-red-500/20 text-red-400 border border-red-500/30 rounded-full text-xs font-medium hover:bg-red-500/30 transition-colors">
                            View
                        </a>
                    </div>
                </div>
            @empty
                <div class="px-5 py-8 text-center text-gray-600 text-sm">
                    No active handoffs.
                </div>
            @endforelse
        </div>
    </div>

</div>

{{-- =====================================================================
     RECENT CONVERSATIONS
     ===================================================================== --}}
<div class="mt-6 rounded-2xl bg-gray-900/60 backdrop-blur border border-gray-800 overflow-hidden">
    <div class="px-5 py-4 border-b border-gray-800 flex items-center justify-between">
        <h2 class="text-sm font-semibold text-white">Recent Conversations</h2>
        <a href="{{ route('conversations.index') }}" class="text-xs text-indigo-400 hover:text-indigo-300">View all →</a>
    </div>

    <div class="divide-y divide-gray-800">
        @forelse($recentConversations as $log)
            <a href="{{ route('conversations.show', $log) }}"
               class="flex items-center gap-4 px-5 py-3.5 hover:bg-gray-800/40 transition-colors block">
                <div class="w-8 h-8 rounded-full bg-gray-700 flex items-center justify-center text-sm font-bold text-white flex-shrink-0">
                    {{ strtoupper(substr($log->patient?->name ?? '?', 0, 1)) }}
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-white">{{ $log->patient?->name ?? 'Unknown' }}</p>
                    @if(!empty($log->messages))
                        <p class="text-xs text-gray-500 truncate">
                            {{ last($log->messages)['content'] ?? '' }}
                        </p>
                    @endif
                </div>
                <div class="text-right flex-shrink-0">
                    <p class="text-xs text-gray-600">{{ $log->updated_at->diffForHumans() }}</p>
                    <span class="inline-flex items-center gap-1 text-xs"
                          @class(['text-gray-500' => !$log->human_mode, 'text-red-400' => $log->human_mode])>
                        @if($log->human_mode)
                            <span class="w-1.5 h-1.5 bg-red-400 rounded-full"></span> Human
                        @else
                            <span class="w-1.5 h-1.5 bg-emerald-400 rounded-full"></span> AI
                        @endif
                    </span>
                </div>
            </a>
        @empty
            <div class="px-5 py-8 text-center text-gray-600 text-sm">No conversations yet.</div>
        @endforelse
    </div>
</div>

<div class="mt-6 text-center">
    <a href="https://kynexsolutions.com" target="_blank" rel="noreferrer" class="text-xs text-gray-500 hover:text-indigo-300">
        Kynex Solutions (kynexsolutions.com)
    </a>
</div>

</x-layouts.app>
