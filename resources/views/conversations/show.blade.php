<x-layouts.app :title="'Conversation — ' . ($log->patient?->name ?? 'Unknown')">

<div class="max-w-3xl mx-auto">

    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-3">
            <a href="{{ route('conversations.index') }}" class="text-[var(--text-muted)] hover:text-[var(--text-strong)] transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
            </a>
            <div>
                <h2 class="text-lg font-bold text-white">{{ $log->patient?->name ?? 'Unknown Patient' }}</h2>
                <div class="flex items-center gap-2 mt-0.5">
                    <span class="text-xs text-gray-500">{{ ucfirst($log->channel) }}</span>
                    <span class="text-gray-700">·</span>
                    <span class="text-xs text-gray-500">{{ $log->ai_model_used ?? 'unknown model' }}</span>
                    @if($log->human_mode)
                        <span class="flex items-center gap-1 px-2 py-0.5 bg-red-500/20 text-red-400 border border-red-500/30 rounded-full text-xs">
                            <span class="w-1.5 h-1.5 bg-red-400 rounded-full"></span>
                            Human mode active
                        </span>
                    @endif
                </div>
            </div>
        </div>

        {{-- Take Over button --}}
        @unless($log->human_mode)
            <form method="POST" action="{{ route('conversations.takeover', $log) }}">
                @csrf
                <button type="submit"
                        class="flex items-center gap-2 px-4 py-2 btn-danger">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                    Take Over
                </button>
            </form>
        @endunless
    </div>

    {{-- Chat bubbles --}}
    <div class="panel p-5 space-y-4 mb-6 max-h-[60vh] overflow-y-auto">
        @forelse($log->messages ?? [] as $message)
            @php
                $isUser = ($message['role'] ?? '') === 'user';
                $ts = \Carbon\Carbon::parse($message['timestamp'] ?? null)?->setTimezone($timezone);
            @endphp

            <div class="flex {{ $isUser ? 'justify-start' : 'justify-end' }} gap-2">
                @if($isUser)
                    <div class="w-7 h-7 rounded-full bg-gray-700 flex items-center justify-center text-xs font-bold text-white flex-shrink-0 mt-1">
                        {{ strtoupper(substr($log->patient?->name ?? '?', 0, 1)) }}
                    </div>
                @endif

                <div class="max-w-sm">
                    <div class="px-4 py-2.5 rounded-2xl text-sm leading-relaxed
                        {{ $isUser
                            ? 'bg-gray-800 text-gray-100 rounded-tl-sm'
                            : 'bg-indigo-600/20 border border-indigo-500/30 text-indigo-100 rounded-tr-sm' }}">
                        {{ $message['content'] ?? '' }}
                    </div>
                    @if($ts)
                        <p class="text-xs text-gray-600 mt-1 {{ $isUser ? 'text-left' : 'text-right' }}">
                            {{ $ts->format('g:i A') }}
                        </p>
                    @endif
                </div>

                @unless($isUser)
                    <div class="w-7 h-7 rounded-full bg-indigo-600/30 flex items-center justify-center text-xs font-bold text-indigo-300 flex-shrink-0 mt-1">
                        AI
                    </div>
                @endunless
            </div>
        @empty
            <p class="text-center text-gray-600 text-sm py-8">No messages in this conversation.</p>
        @endforelse
    </div>

    {{-- Linked appointment --}}
    @if($log->appointment)
        <div class="panel p-5">
            <p class="text-xs text-gray-600 uppercase tracking-wider mb-3">Linked Appointment</p>
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-semibold text-white">{{ $log->appointment->service_type }}</p>
                    <p class="text-xs text-gray-500">
                        {{ \Carbon\Carbon::parse($log->appointment->start_time)->setTimezone($timezone)->format('M j, Y g:i A') }}
                        · {{ $log->appointment->status }}
                    </p>
                </div>
                <a href="{{ route('appointments.show', $log->appointment) }}"
                   class="text-xs text-[var(--brand)] hover:text-[var(--brand-strong)]">View →</a>
            </div>
        </div>
    @endif

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <div class="panel p-5">
            <p class="text-xs text-gray-600 uppercase tracking-wider mb-3">Outbound Delivery</p>
            <div class="space-y-3">
                @forelse($log->outboundAttempts->sortByDesc('id') as $attempt)
                    <div class="panel-subtle px-4 py-3">
                        <div class="flex items-center justify-between gap-3">
                            <div class="text-sm text-white">{{ \Illuminate\Support\Str::limit($attempt->message_text, 80) }}</div>
                            <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs {{ $attempt->status === 'sent' ? 'bg-emerald-500/20 text-emerald-400' : 'bg-amber-500/20 text-amber-300' }}">
                                {{ $attempt->status }}
                            </span>
                        </div>
                        <div class="mt-2 text-xs text-gray-500">
                            Attempts: {{ $attempt->attempts }} · {{ $attempt->sent_at?->setTimezone($timezone)->format('M j, g:i A') ?? 'not sent' }}
                        </div>
                        @if($attempt->last_error)
                            <div class="mt-1 text-xs text-red-300">{{ $attempt->last_error }}</div>
                        @endif
                    </div>
                @empty
                    <p class="text-sm text-gray-500">No outbound attempts recorded.</p>
                @endforelse
            </div>
        </div>

        <div class="panel p-5">
            <p class="text-xs text-gray-600 uppercase tracking-wider mb-3">Internal Notes</p>
            <form method="POST" action="{{ route('conversations.notes.store', $log) }}" class="space-y-3">
                @csrf
                <textarea name="note" rows="3" class="w-full field" placeholder="Add an internal note or next action"></textarea>
                <button type="submit" class="rounded-lg bg-indigo-500 px-3 py-2 text-sm font-medium text-white">Add Note</button>
            </form>
            <div class="mt-4 space-y-3">
                @forelse($log->notes->sortByDesc('id') as $note)
                    <div class="panel-subtle px-4 py-3">
                        <div class="text-sm text-white">{{ $note->note }}</div>
                        <div class="mt-1 text-xs text-gray-500">{{ $note->user?->name ?? 'Unknown' }} · {{ $note->created_at->setTimezone($timezone)->format('M j, g:i A') }}</div>
                    </div>
                @empty
                    <p class="text-sm text-gray-500">No internal notes yet.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>

</x-layouts.app>
