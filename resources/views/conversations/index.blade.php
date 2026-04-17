<x-layouts.app title="Conversations">

{{-- Search + filter bar --}}
<form method="GET" action="{{ route('conversations.index') }}" class="flex flex-wrap gap-2 mb-6">
    <input type="text" name="search" value="{{ $filters['search'] ?? '' }}"
           placeholder="Search by patient name…"
           class="flex-1 min-w-48 bg-gray-800 border border-gray-700 text-gray-300 text-sm rounded-lg px-3 py-2
                  placeholder-gray-600 focus:ring-indigo-500 focus:border-indigo-500">

    <select name="channel"
            class="bg-gray-800 border border-gray-700 text-gray-300 text-sm rounded-lg px-3 py-2">
        <option value="">All Channels</option>
        <option value="whatsapp"  {{ ($filters['channel'] ?? '') === 'whatsapp'  ? 'selected' : '' }}>WhatsApp</option>
        <option value="messenger" {{ ($filters['channel'] ?? '') === 'messenger' ? 'selected' : '' }}>Messenger</option>
    </select>

    <input type="date" name="date" value="{{ $filters['date'] ?? '' }}"
           class="bg-gray-800 border border-gray-700 text-gray-300 text-sm rounded-lg px-3 py-2">

    <label class="flex items-center gap-2 px-3 py-2 bg-gray-800 border border-gray-700 rounded-lg text-sm text-gray-400 cursor-pointer">
        <input type="checkbox" name="handoff_only" value="1"
               {{ ($filters['handoff_only'] ?? '') === '1' ? 'checked' : '' }}
               class="rounded border-gray-600 bg-gray-700 text-red-500">
        Handoffs only
    </label>

    <button type="submit"
            class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-medium rounded-lg transition-colors">
        Filter
    </button>
</form>

{{-- Conversation list --}}
<div class="rounded-2xl bg-gray-900/60 backdrop-blur border border-gray-800 overflow-hidden">
    <div class="divide-y divide-gray-800">
        @forelse($logs as $log)
            @php
                $lastAttempt = $log->outboundAttempts->sortByDesc('id')->first();
            @endphp
            <a href="{{ route('conversations.show', $log) }}"
               class="flex items-center gap-4 px-5 py-4 hover:bg-gray-800/40 transition-colors block">

                {{-- Avatar --}}
                <div class="w-10 h-10 rounded-full bg-gray-700 flex items-center justify-center text-sm font-bold text-white flex-shrink-0">
                    {{ strtoupper(substr($log->patient?->name ?? '?', 0, 1)) }}
                </div>

                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2">
                        <p class="text-sm font-semibold text-white">{{ $log->patient?->name ?? 'Unknown' }}</p>
                        @if($log->human_mode)
                            <span class="flex items-center gap-1 px-2 py-0.5 bg-red-500/20 text-red-400 border border-red-500/30 rounded-full text-xs">
                                <span class="w-1.5 h-1.5 bg-red-400 rounded-full animate-ping"></span>
                                Handoff
                            </span>
                        @endif
                    </div>
                    @if(!empty($log->messages))
                        <p class="text-xs text-gray-500 truncate mt-0.5">
                            {{ last($log->messages)['content'] ?? '' }}
                        </p>
                    @endif
                </div>

                <div class="text-right flex-shrink-0 space-y-1">
                    <p class="text-xs text-gray-600">{{ $log->updated_at->diffForHumans() }}</p>
                    <div class="flex items-center justify-end gap-1.5">
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs
                            {{ $log->channel === 'whatsapp' ? 'bg-green-500/20 text-green-400' : 'bg-blue-500/20 text-blue-400' }}">
                            {{ ucfirst($log->channel) }}
                        </span>
                        @if($lastAttempt)
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs
                                {{ $lastAttempt->status === 'sent' ? 'bg-emerald-500/20 text-emerald-400' : 'bg-amber-500/20 text-amber-300' }}">
                                {{ $lastAttempt->status }}
                            </span>
                        @endif
                        <span class="text-xs text-gray-600">{{ $log->ai_model_used ?? '' }}</span>
                    </div>
                </div>
            </a>
        @empty
            <div class="px-5 py-16 text-center text-gray-600 text-sm">
                No conversations found.
            </div>
        @endforelse
    </div>

    {{-- Pagination --}}
    @if($logs->hasPages())
        <div class="px-5 py-4 border-t border-gray-800">
            {{ $logs->links() }}
        </div>
    @endif
</div>

</x-layouts.app>
