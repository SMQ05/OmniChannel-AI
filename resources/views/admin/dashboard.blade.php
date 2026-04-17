<x-admin.layouts.admin title="Admin Overview">

{{-- =====================================================================
     PLATFORM STAT CARDS
     ===================================================================== --}}
<div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-8">

    @php
        $statCards = [
            ['label' => 'Total Businesses',  'value' => $totalBusinesses,  'color' => 'indigo'],
            ['label' => 'Active Businesses', 'value' => $activeBusinesses, 'color' => 'emerald'],
            ['label' => 'Messages Today',    'value' => $messagesToday,    'color' => 'purple'],
            ['label' => 'Live AI Sessions',  'value' => $activeAiSessions, 'color' => 'blue'],
            ['label' => 'Pending Handoffs',  'value' => $pendingHandoffs,  'color' => 'red'],
        ];
    @endphp

    @foreach($statCards as $card)
        <div class="relative overflow-hidden rounded-2xl bg-gray-900/60 backdrop-blur border border-gray-800 p-5">
            <div class="absolute inset-0 bg-gradient-to-br from-{{ $card['color'] }}-600/10 to-transparent pointer-events-none"></div>
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">{{ $card['label'] }}</p>
            <p class="text-4xl font-bold text-white">{{ $card['value'] }}</p>
            <div class="mt-2 h-0.5 bg-{{ $card['color'] }}-500/30 rounded"></div>
        </div>
    @endforeach
</div>

<div class="grid grid-cols-1 xl:grid-cols-2 gap-6">

    {{-- =====================================================================
         QUEUE DEPTH MONITOR
         ===================================================================== --}}
    <div class="rounded-2xl bg-gray-900/60 backdrop-blur border border-gray-800 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-800 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-white">Queue Depths</h2>
            <a href="{{ route('admin.horizon') }}"
               class="text-xs text-indigo-400 hover:text-indigo-300 transition-colors">
                Open Horizon →
            </a>
        </div>

        <div class="p-5 space-y-4">
            @foreach($queueDepths as $queue => $depth)
                @php
                    $isWarn = $depth !== null && $depth > 100;
                    $isAlert = $depth !== null && $depth > 500;
                @endphp
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <span class="text-sm font-medium text-gray-300 capitalize">{{ $queue }}</span>
                        <span class="text-sm font-bold
                            {{ $isAlert ? 'text-red-400' : ($isWarn ? 'text-yellow-400' : 'text-emerald-400') }}">
                            {{ $depth ?? '—' }}
                            @if($isAlert) <span class="text-xs font-normal">⚠ backlog</span> @endif
                        </span>
                    </div>
                    <div class="h-1.5 bg-gray-800 rounded-full overflow-hidden">
                        @if($depth !== null)
                            <div class="h-full rounded-full transition-all
                                {{ $isAlert ? 'bg-red-500' : ($isWarn ? 'bg-yellow-500' : 'bg-emerald-500') }}"
                                 style="width: {{ min(100, ($depth / 10)) }}%">
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        <div class="px-5 py-3 border-t border-gray-800">
            <p class="text-xs text-gray-600">Depths &gt; 100 = warning · &gt; 500 = backlog alert</p>
        </div>
    </div>

    {{-- =====================================================================
         RECENT BUSINESSES
         ===================================================================== --}}
    <div class="rounded-2xl bg-gray-900/60 backdrop-blur border border-gray-800 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-800 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-white">Recent Businesses</h2>
            <a href="{{ route('admin.businesses.index') }}"
               class="text-xs text-indigo-400 hover:text-indigo-300 transition-colors">
                View all →
            </a>
        </div>

        <div class="divide-y divide-gray-800">
            @forelse($recentBusinesses as $business)
                <div class="flex items-center justify-between px-5 py-3.5">
                    <div class="flex items-center gap-3">
                        <div class="flex items-center gap-1.5">
                            <span class="w-2 h-2 rounded-full {{ $business->is_active ? 'bg-emerald-400' : 'bg-gray-600' }}"></span>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-white">{{ $business->name }}</p>
                            <p class="text-xs text-gray-500">{{ $business->slug }} · {{ $business->business_type }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="px-2 py-0.5 rounded-full text-xs font-medium
                            {{ match($business->plan) {
                                'enterprise' => 'bg-purple-500/20 text-purple-400',
                                'pro'        => 'bg-indigo-500/20 text-indigo-400',
                                'starter'    => 'bg-blue-500/20 text-blue-400',
                                default      => 'bg-gray-700 text-gray-400',
                            } }}">
                            {{ $business->plan }}
                        </span>
                        <form method="POST" action="{{ route('admin.businesses.impersonate', $business) }}">
                            @csrf
                            <button type="submit"
                                    class="text-xs text-amber-400 hover:text-amber-300 transition-colors">
                                Impersonate
                            </button>
                        </form>
                    </div>
                </div>
            @empty
                <div class="px-5 py-8 text-center text-gray-600 text-sm">No businesses yet.</div>
            @endforelse
        </div>
    </div>

</div>

</x-admin.layouts.admin>
