<x-admin.layouts.admin title="Voice Overview">
<div class="space-y-6">
    <div class="panel p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h2 class="text-xl font-semibold text-white">Platform Voice Overview</h2>
                <p class="mt-1 text-sm text-gray-400">Cross-business visibility into voice readiness, plan eligibility, and active channels.</p>
            </div>
            <div class="rounded-xl border px-4 py-3 text-sm {{ $platform['feature_enabled'] ? 'border-emerald-500/30 bg-emerald-500/10 text-emerald-300' : 'border-amber-500/30 bg-amber-500/10 text-amber-300' }}">
                {{ $platform['feature_enabled'] ? 'FEATURE_VOICE_AGENT is enabled' : 'FEATURE_VOICE_AGENT is disabled' }}
            </div>
        </div>
    </div>

    <div class="grid gap-6 xl:grid-cols-3">
        @foreach($platform['providers'] as $kind => $providers)
            <div class="panel p-6">
                <h3 class="text-sm font-semibold text-white">{{ strtoupper($kind) }}</h3>
                <div class="mt-4 space-y-3">
                    @foreach($providers as $provider)
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-white">{{ $provider['label'] }}</span>
                            <span class="{{ $provider['ready'] ? 'text-emerald-400' : 'text-gray-500' }}">{{ $provider['ready'] ? 'Configured' : 'Missing env' }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>

    <div class="panel p-6">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h3 class="text-sm font-semibold text-white">Manage Business Voice Configuration</h3>
                <p class="mt-1 text-xs text-gray-500">Select a business to manage its voice feature flag, provider routing, channels, and live provider tests from the SaaS admin side.</p>
            </div>
            <form method="GET" action="{{ route('admin.voice.index') }}" class="flex items-end gap-3">
                <div>
                    <label class="mb-1 block text-xs text-gray-500">Business</label>
                    <select name="business" class="min-w-64 field">
                        @foreach($businesses as $business)
                            <option value="{{ $business->id }}" @selected(optional($selectedBusiness)->id === $business->id)>{{ $business->name }}</option>
                        @endforeach
                    </select>
                </div>
                <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500">Load Business</button>
            </form>
        </div>
    </div>

    <div class="panel p-6">
        <h3 class="text-sm font-semibold text-white">Business Voice Readiness</h3>
        <div class="mt-4 overflow-x-auto">
            <table class="min-w-full text-left text-sm text-gray-300">
                <thead class="text-xs uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="pb-3 pr-4">Business</th>
                        <th class="pb-3 pr-4">Plan Voice</th>
                        <th class="pb-3 pr-4">Ready</th>
                        <th class="pb-3 pr-4">Active Channels</th>
                        <th class="pb-3 pr-4">Recent Calls</th>
                        <th class="pb-3">Notes</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-800">
                    @foreach($businessVoiceStates as $state)
                        <tr>
                            <td class="py-3 pr-4">
                                <div class="font-medium text-white">{{ $state['business']->name }}</div>
                                <div class="text-xs text-gray-500">{{ $state['business']->slug }}</div>
                            </td>
                            <td class="py-3 pr-4 {{ $state['plan_supports_voice'] ? 'text-emerald-400' : 'text-gray-500' }}">{{ $state['plan_supports_voice'] ? 'Included' : 'Not included' }}</td>
                            <td class="py-3 pr-4 {{ $state['voice_ready'] ? 'text-emerald-400' : 'text-amber-300' }}">{{ $state['voice_ready'] ? 'Ready' : 'Needs setup' }}</td>
                            <td class="py-3 pr-4">{{ $state['active_channels'] }}</td>
                            <td class="py-3 pr-4">{{ $state['recent_calls'] }}</td>
                            <td class="py-3 text-xs text-gray-500">{{ $state['issues'] === [] ? 'No blockers' : \Illuminate\Support\Str::limit(implode(' ', $state['issues']), 110) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    @if($selectedBusiness && $selectedVoiceState)
        @php
            $settings = $selectedVoiceState['settings'];
            $channels = $selectedVoiceState['channels'];
            $recentCalls = $selectedVoiceState['recent_calls'];
        @endphp

        @if($selectedVoiceState['issues'] !== [])
            <div class="rounded-2xl border border-amber-500/30 bg-amber-500/10 p-6">
                <h3 class="text-sm font-semibold text-amber-200">{{ $selectedBusiness->name }} readiness blockers</h3>
                <div class="mt-3 space-y-2 text-sm text-amber-100/90">
                    @foreach($selectedVoiceState['issues'] as $issue)
                        <div>{{ $issue }}</div>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="grid gap-6 xl:grid-cols-2">
            <form method="POST" action="{{ route('admin.voice.update', $selectedBusiness) }}" class="panel p-6 space-y-5">
                @csrf
                @method('PATCH')

                <div class="flex items-center justify-between gap-4">
                    <div>
                        <h3 class="text-sm font-semibold text-white">Business Voice Settings</h3>
                        <p class="mt-1 text-xs text-gray-500">Feature flag, provider routing, and operator-facing call copy for {{ $selectedBusiness->name }}.</p>
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
                            <select name="voice[{{ $kind }}_provider]" class="w-full field">
                                <option value="">Select {{ strtolower($label) }}</option>
                                @foreach($selectedVoiceState['options'][$kind] as $provider => $providerLabel)
                                    <option value="{{ $provider }}" @selected(old("voice.$kind"."_provider", $settings[$kind . '_provider'] ?? null) === $provider)>{{ $providerLabel }}</option>
                                @endforeach
                            </select>
                            <div class="mt-1 text-xs text-gray-500">
                                Platform default: {{ ucfirst($platform['defaults'][$kind] ?? 'not set') }}
                            </div>
                        </div>
                    @endforeach
                </div>

                <div>
                    <label class="mb-1 block text-xs text-gray-500">Greeting message</label>
                    <textarea name="voice[greeting_message]" rows="3" class="w-full field">{{ old('voice.greeting_message', $settings['greeting_message'] ?? '') }}</textarea>
                </div>

                <div>
                    <label class="mb-1 block text-xs text-gray-500">Escalation / handoff message</label>
                    <textarea name="voice[handoff_message]" rows="3" class="w-full field">{{ old('voice.handoff_message', $settings['handoff_message'] ?? '') }}</textarea>
                </div>

                <div>
                    <label class="mb-1 block text-xs text-gray-500">Operator notes</label>
                    <textarea name="voice[notes]" rows="3" class="w-full field">{{ old('voice.notes', $settings['notes'] ?? '') }}</textarea>
                </div>

                <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500">Save Voice Settings</button>
            </form>

            <div class="panel p-6">
                <h3 class="text-sm font-semibold text-white">Platform Provider Readiness</h3>
                <div class="mt-4 grid gap-3">
                    @foreach($platform['providers'] as $kind => $providers)
                        <div class="panel-subtle px-4 py-3">
                            <div class="text-xs uppercase tracking-wide text-gray-500">{{ strtoupper($kind) }}</div>
                            <div class="mt-2 space-y-2">
                                @foreach($providers as $provider)
                                    <div class="flex items-center justify-between text-sm">
                                        <span class="text-white">{{ $provider['label'] }}</span>
                                        <span class="{{ $provider['ready'] ? 'text-emerald-400' : 'text-gray-500' }}">{{ $provider['ready'] ? 'Configured' : 'Missing env' }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="grid gap-6 xl:grid-cols-2">
            <form method="POST" action="{{ route('admin.voice.channels.store', $selectedBusiness) }}" class="panel p-6 space-y-4">
                @csrf
                <h3 class="text-sm font-semibold text-white">Add Voice Channel</h3>
                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs text-gray-500">Provider</label>
                        <select name="provider" class="w-full field">
                            @foreach($selectedVoiceState['options']['transport'] as $provider => $providerLabel)
                                <option value="{{ $provider }}">{{ $providerLabel }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs text-gray-500">Public phone number / SIP DID</label>
                        <input name="phone_number" class="w-full field" placeholder="+1 555 0100">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs text-gray-500">Channel label</label>
                        <input name="config[label]" class="w-full field" placeholder="Main front desk">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs text-gray-500">Inbound flow tag</label>
                        <input name="config[inbound_flow]" class="w-full field" placeholder="after-hours">
                    </div>
                </div>
                <label class="inline-flex items-center gap-2 text-sm text-gray-300">
                    <input type="checkbox" name="is_enabled" value="1" class="rounded border-gray-700 bg-gray-950 text-indigo-500">
                    Activate immediately
                </label>
                <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500">Save Voice Channel</button>
            </form>

            <div class="panel p-6">
                <h3 class="text-sm font-semibold text-white">Configured Voice Channels</h3>
                <div class="mt-4 space-y-3">
                    @forelse($channels as $channel)
                        <div class="panel-subtle px-4 py-3">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <div class="text-sm font-medium text-white">{{ $channel->config['label'] ?? strtoupper($channel->provider) }}</div>
                                    <div class="mt-1 text-xs text-gray-500">{{ $channel->phone_number ?: 'No number assigned yet' }}</div>
                                    <div class="mt-1 text-xs text-gray-500">Flow: {{ $channel->config['inbound_flow'] ?? 'default' }}</div>
                                </div>
                                <form method="POST" action="{{ route('admin.voice.channels.toggle', [$selectedBusiness, $channel]) }}">
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
            <div class="panel p-6 space-y-4">
                <h3 class="text-sm font-semibold text-white">Provider Test Actions</h3>

                <form method="POST" action="{{ route('admin.voice.test', [$selectedBusiness, 'transport']) }}" class="grid gap-3 panel-subtle p-4">
                    @csrf
                    <div class="text-sm text-white">Test transport</div>
                    <div class="grid gap-3 md:grid-cols-2">
                        <input name="to" class="field" placeholder="To number or SIP URI">
                        <input name="from" class="field" placeholder="From number">
                    </div>
                    <button class="w-fit rounded-lg bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-500">Run Transport Test</button>
                </form>

                <form method="POST" action="{{ route('admin.voice.test', [$selectedBusiness, 'stt']) }}" class="grid gap-3 panel-subtle p-4">
                    @csrf
                    <div class="text-sm text-white">Test speech-to-text</div>
                    <input name="audio_reference" class="field" placeholder="https://example.com/audio.wav">
                    <button class="w-fit rounded-lg bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-500">Run STT Test</button>
                </form>

                <form method="POST" action="{{ route('admin.voice.test', [$selectedBusiness, 'llm']) }}" class="grid gap-3 panel-subtle p-4">
                    @csrf
                    <div class="text-sm text-white">Test streaming LLM</div>
                    <textarea name="prompt" rows="3" class="field" placeholder="Ask the voice agent to book a checkup tomorrow morning."></textarea>
                    <button class="w-fit rounded-lg bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-500">Run LLM Test</button>
                </form>

                <form method="POST" action="{{ route('admin.voice.test', [$selectedBusiness, 'tts']) }}" class="grid gap-3 panel-subtle p-4">
                    @csrf
                    <div class="text-sm text-white">Test text-to-speech</div>
                    <textarea name="text" rows="3" class="field" placeholder="Thank you for calling. Your appointment is confirmed."></textarea>
                    <button class="w-fit rounded-lg bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-500">Run TTS Test</button>
                </form>
            </div>

            <div class="panel p-6">
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
    @endif
</div>
</x-admin.layouts.admin>
