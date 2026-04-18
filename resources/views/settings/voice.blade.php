<x-layouts.app title="Voice Agent">
@php
    $settings = $voiceState['settings'];
    $platform = $voiceState['platform'];
    $voiceChannels = $voiceState['channels'];
    $recentCalls = $voiceState['recent_calls'];
@endphp

<div class="space-y-6">
    <div class="rounded-2xl border border-gray-800 bg-gray-900/60 p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h2 class="text-xl font-semibold text-white">Voice Agent</h2>
                <p class="mt-1 text-sm text-gray-400">Configure transport, STT, streaming LLM, and TTS without affecting the stable messaging stack.</p>
            </div>
            <div class="rounded-xl border px-4 py-3 text-sm {{ $voiceState['ready'] ? 'border-emerald-500/30 bg-emerald-500/10 text-emerald-300' : 'border-amber-500/30 bg-amber-500/10 text-amber-300' }}">
                {{ $voiceState['ready'] ? 'Voice configuration is ready for controlled rollout.' : 'Voice still has configuration gaps.' }}
            </div>
        </div>
    </div>

    @if($voiceState['issues'] !== [])
        <div class="rounded-2xl border border-amber-500/30 bg-amber-500/10 p-6">
            <h3 class="text-sm font-semibold text-amber-200">Readiness blockers</h3>
            <div class="mt-3 space-y-2 text-sm text-amber-100/90">
                @foreach($voiceState['issues'] as $issue)
                    <div>{{ $issue }}</div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="grid gap-4 md:grid-cols-4">
        <div class="rounded-2xl border border-gray-800 bg-gray-900/60 p-5">
            <div class="text-xs uppercase tracking-wide text-gray-500">Active Channels</div>
            <div class="mt-2 text-3xl font-semibold text-white">{{ $voiceState['summary']['active_channels'] }}</div>
        </div>
        <div class="rounded-2xl border border-gray-800 bg-gray-900/60 p-5">
            <div class="text-xs uppercase tracking-wide text-gray-500">Configured Channels</div>
            <div class="mt-2 text-3xl font-semibold text-white">{{ $voiceState['summary']['total_channels'] }}</div>
        </div>
        <div class="rounded-2xl border border-gray-800 bg-gray-900/60 p-5">
            <div class="text-xs uppercase tracking-wide text-gray-500">Recent Calls</div>
            <div class="mt-2 text-3xl font-semibold text-white">{{ $voiceState['summary']['recent_calls'] }}</div>
        </div>
        <div class="rounded-2xl border border-gray-800 bg-gray-900/60 p-5">
            <div class="text-xs uppercase tracking-wide text-gray-500">Voice Minutes This Period</div>
            <div class="mt-2 text-3xl font-semibold text-white">{{ number_format($voiceState['summary']['voice_minutes_used'], 1) }}</div>
        </div>
    </div>

    <div class="grid gap-6 xl:grid-cols-2">
        <form method="POST" action="{{ route('settings.voice.update') }}" class="rounded-2xl border border-gray-800 bg-gray-900/60 p-6 space-y-5">
            @csrf
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h3 class="text-sm font-semibold text-white">Business Voice Settings</h3>
                    <p class="mt-1 text-xs text-gray-500">Feature flag, provider routing, and operator-facing call copy.</p>
                </div>
                <label class="inline-flex items-center gap-2 text-sm text-gray-300">
                    <input type="checkbox" name="voice[enabled]" value="1" @checked(old('voice.enabled', $settings['enabled'] ?? false)) class="rounded border-gray-700 bg-gray-950 text-indigo-500">
                    Enable voice
                </label>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                @foreach(['transport' => 'Transport', 'stt' => 'Speech-to-Text', 'llm' => 'Streaming LLM', 'tts' => 'Text-to-Speech'] as $kind => $label)
                    <div>
                        <label class="mb-1 block text-xs text-gray-500">{{ $label }}</label>
                        <select name="voice[{{ $kind }}_provider]" class="w-full rounded-lg border border-gray-700 bg-gray-950 px-3 py-2 text-sm text-white">
                            <option value="">Select {{ strtolower($label) }}</option>
                            @foreach($voiceState['options'][$kind] as $provider => $providerLabel)
                                <option value="{{ $provider }}" @selected(old("voice.$kind"."_provider", $settings[$kind . '_provider'] ?? null) === $provider)>{{ $providerLabel }}</option>
                            @endforeach
                        </select>
                        <div class="mt-1 text-xs {{ data_get($platform, "providers.$kind." . (old("voice.$kind"."_provider", $settings[$kind . '_provider'] ?? null)) . ".ready") ? 'text-emerald-400' : 'text-gray-500' }}">
                            Platform default: {{ ucfirst($platform['defaults'][$kind] ?? 'not set') }}
                        </div>
                    </div>
                @endforeach
            </div>

            <div>
                <label class="mb-1 block text-xs text-gray-500">Greeting message</label>
                <textarea name="voice[greeting_message]" rows="3" class="w-full rounded-lg border border-gray-700 bg-gray-950 px-3 py-2 text-sm text-white">{{ old('voice.greeting_message', $settings['greeting_message'] ?? '') }}</textarea>
            </div>

            <div>
                <label class="mb-1 block text-xs text-gray-500">Escalation / handoff message</label>
                <textarea name="voice[handoff_message]" rows="3" class="w-full rounded-lg border border-gray-700 bg-gray-950 px-3 py-2 text-sm text-white">{{ old('voice.handoff_message', $settings['handoff_message'] ?? '') }}</textarea>
            </div>

            <div>
                <label class="mb-1 block text-xs text-gray-500">Operator notes</label>
                <textarea name="voice[notes]" rows="3" class="w-full rounded-lg border border-gray-700 bg-gray-950 px-3 py-2 text-sm text-white">{{ old('voice.notes', $settings['notes'] ?? '') }}</textarea>
            </div>

            <button class="rounded-lg bg-indigo-500 px-4 py-2 text-sm font-medium text-white">Save Voice Settings</button>
        </form>

        <div class="rounded-2xl border border-gray-800 bg-gray-900/60 p-6">
            <h3 class="text-sm font-semibold text-white">Platform Provider Readiness</h3>
            <div class="mt-4 grid gap-3">
                @foreach($platform['providers'] as $kind => $providers)
                    <div class="rounded-xl border border-gray-800 px-4 py-3">
                        <div class="text-xs uppercase tracking-wide text-gray-500">{{ strtoupper($kind) }}</div>
                        <div class="mt-2 space-y-2">
                            @foreach($providers as $provider)
                                <div class="flex items-center justify-between text-sm">
                                    <span class="text-white">{{ $provider['label'] }}</span>
                                    <span class="{{ $provider['ready'] ? 'text-emerald-400' : 'text-amber-400' }}">{{ $provider['ready'] ? 'Configured' : 'Missing env' }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="grid gap-6 xl:grid-cols-2">
        <form method="POST" action="{{ route('settings.voice.channels.store') }}" class="rounded-2xl border border-gray-800 bg-gray-900/60 p-6 space-y-4">
            @csrf
            <h3 class="text-sm font-semibold text-white">Add Voice Channel</h3>
            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label class="mb-1 block text-xs text-gray-500">Provider</label>
                    <select name="provider" class="w-full rounded-lg border border-gray-700 bg-gray-950 px-3 py-2 text-sm text-white">
                        @foreach($voiceState['options']['transport'] as $provider => $providerLabel)
                            <option value="{{ $provider }}">{{ $providerLabel }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs text-gray-500">Public phone number / SIP DID</label>
                    <input name="phone_number" class="w-full rounded-lg border border-gray-700 bg-gray-950 px-3 py-2 text-sm text-white" placeholder="+1 555 0100">
                </div>
                <div>
                    <label class="mb-1 block text-xs text-gray-500">Channel label</label>
                    <input name="config[label]" class="w-full rounded-lg border border-gray-700 bg-gray-950 px-3 py-2 text-sm text-white" placeholder="Main front desk">
                </div>
                <div>
                    <label class="mb-1 block text-xs text-gray-500">Inbound flow tag</label>
                    <input name="config[inbound_flow]" class="w-full rounded-lg border border-gray-700 bg-gray-950 px-3 py-2 text-sm text-white" placeholder="after-hours">
                </div>
            </div>
            <label class="inline-flex items-center gap-2 text-sm text-gray-300">
                <input type="checkbox" name="is_enabled" value="1" class="rounded border-gray-700 bg-gray-950 text-indigo-500">
                Activate immediately
            </label>
            <button class="rounded-lg bg-indigo-500 px-4 py-2 text-sm font-medium text-white">Save Voice Channel</button>
        </form>

        <div class="rounded-2xl border border-gray-800 bg-gray-900/60 p-6">
            <h3 class="text-sm font-semibold text-white">Configured Voice Channels</h3>
            <div class="mt-4 space-y-3">
                @forelse($voiceChannels as $channel)
                    <div class="rounded-xl border border-gray-800 px-4 py-3">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <div class="text-sm font-medium text-white">{{ $channel->config['label'] ?? strtoupper($channel->provider) }}</div>
                                <div class="mt-1 text-xs text-gray-500">{{ $channel->phone_number ?: 'No number assigned yet' }}</div>
                                <div class="mt-1 text-xs text-gray-500">Flow: {{ $channel->config['inbound_flow'] ?? 'default' }}</div>
                            </div>
                            <form method="POST" action="{{ route('settings.voice.channels.toggle', $channel) }}">
                                @csrf
                                @method('PATCH')
                                <button class="rounded-lg px-3 py-2 text-sm font-medium {{ $channel->is_enabled ? 'bg-emerald-500/20 text-emerald-300' : 'bg-gray-800 text-gray-300' }}">
                                    {{ $channel->is_enabled ? 'Disable' : 'Enable' }}
                                </button>
                            </form>
                        </div>
                    </div>
                @empty
                    <div class="rounded-xl border border-dashed border-gray-800 px-4 py-6 text-sm text-gray-500">No voice channels configured yet.</div>
                @endforelse
            </div>
        </div>
    </div>

    <div class="grid gap-6 xl:grid-cols-2">
        <div class="rounded-2xl border border-gray-800 bg-gray-900/60 p-6 space-y-4">
            <h3 class="text-sm font-semibold text-white">Provider Test Actions</h3>

            <form method="POST" action="{{ route('settings.voice.test', 'transport') }}" class="grid gap-3 rounded-xl border border-gray-800 p-4">
                @csrf
                <div class="text-sm text-white">Test transport</div>
                <div class="grid gap-3 md:grid-cols-2">
                    <input name="to" class="rounded-lg border border-gray-700 bg-gray-950 px-3 py-2 text-sm text-white" placeholder="To number or SIP URI">
                    <input name="from" class="rounded-lg border border-gray-700 bg-gray-950 px-3 py-2 text-sm text-white" placeholder="From number">
                </div>
                <button class="w-fit rounded-lg bg-indigo-500 px-3 py-2 text-sm font-medium text-white">Run Transport Test</button>
            </form>

            <form method="POST" action="{{ route('settings.voice.test', 'stt') }}" class="grid gap-3 rounded-xl border border-gray-800 p-4">
                @csrf
                <div class="text-sm text-white">Test speech-to-text</div>
                <input name="audio_reference" class="rounded-lg border border-gray-700 bg-gray-950 px-3 py-2 text-sm text-white" placeholder="https://example.com/audio.wav">
                <button class="w-fit rounded-lg bg-indigo-500 px-3 py-2 text-sm font-medium text-white">Run STT Test</button>
            </form>

            <form method="POST" action="{{ route('settings.voice.test', 'llm') }}" class="grid gap-3 rounded-xl border border-gray-800 p-4">
                @csrf
                <div class="text-sm text-white">Test streaming LLM</div>
                <textarea name="prompt" rows="3" class="rounded-lg border border-gray-700 bg-gray-950 px-3 py-2 text-sm text-white" placeholder="Ask the voice agent to book a checkup tomorrow morning."></textarea>
                <button class="w-fit rounded-lg bg-indigo-500 px-3 py-2 text-sm font-medium text-white">Run LLM Test</button>
            </form>

            <form method="POST" action="{{ route('settings.voice.test', 'tts') }}" class="grid gap-3 rounded-xl border border-gray-800 p-4">
                @csrf
                <div class="text-sm text-white">Test text-to-speech</div>
                <textarea name="text" rows="3" class="rounded-lg border border-gray-700 bg-gray-950 px-3 py-2 text-sm text-white" placeholder="Thank you for calling. Your appointment is confirmed."></textarea>
                <button class="w-fit rounded-lg bg-indigo-500 px-3 py-2 text-sm font-medium text-white">Run TTS Test</button>
            </form>
        </div>

        <div class="rounded-2xl border border-gray-800 bg-gray-900/60 p-6">
            <h3 class="text-sm font-semibold text-white">Provider Notes</h3>
            <div class="mt-4 space-y-4 text-sm text-gray-300">
                <div class="rounded-xl border border-gray-800 px-4 py-3">
                    <div class="font-medium text-white">Telnyx / SIP</div>
                    <div class="mt-1 text-gray-500">Transport tests can initiate real outbound traffic. Use safe test numbers only.</div>
                </div>
                <div class="rounded-xl border border-gray-800 px-4 py-3">
                    <div class="font-medium text-white">Deepgram</div>
                    <div class="mt-1 text-gray-500">STT tests expect a public audio URL reachable by Deepgram.</div>
                </div>
                <div class="rounded-xl border border-gray-800 px-4 py-3">
                    <div class="font-medium text-white">OpenRouter</div>
                    <div class="mt-1 text-gray-500">The LLM test runs through the currently selected voice LLM provider and records estimated token usage.</div>
                </div>
                <div class="rounded-xl border border-gray-800 px-4 py-3">
                    <div class="font-medium text-white">ElevenLabs</div>
                    <div class="mt-1 text-gray-500">TTS tests return success if audio bytes are generated. Audio playback UI can be added later without changing the provider layer.</div>
                </div>
            </div>
        </div>
    </div>

    <div class="rounded-2xl border border-gray-800 bg-gray-900/60 p-6">
        <h3 class="text-sm font-semibold text-white">Recent Call Logs</h3>
        <div class="mt-4 overflow-x-auto">
            <table class="min-w-full text-left text-sm text-gray-300">
                <thead class="text-xs uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="pb-3 pr-4">Status</th>
                        <th class="pb-3 pr-4">Direction</th>
                        <th class="pb-3 pr-4">Patient</th>
                        <th class="pb-3 pr-4">Duration</th>
                        <th class="pb-3 pr-4">Started</th>
                        <th class="pb-3">Excerpt</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-800">
                    @forelse($recentCalls as $call)
                        <tr>
                            <td class="py-3 pr-4">{{ $call->status }}</td>
                            <td class="py-3 pr-4">{{ ucfirst($call->direction) }}</td>
                            <td class="py-3 pr-4">{{ $call->patient?->name ?? 'Unknown' }}</td>
                            <td class="py-3 pr-4">{{ gmdate('i:s', (int) $call->duration_seconds) }}</td>
                            <td class="py-3 pr-4">{{ optional($call->started_at)->diffForHumans() ?? 'n/a' }}</td>
                            <td class="py-3 text-gray-400">{{ \Illuminate\Support\Str::limit($call->transcript_excerpt, 100) ?: 'No transcript excerpt yet' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-5 text-gray-500">No call logs recorded yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
</x-layouts.app>
