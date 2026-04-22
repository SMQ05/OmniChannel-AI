<x-layouts.app title="Voice">
@php
    $settings = $voiceState['settings'];
    $voiceChannels = $voiceState['channels'];
    $recentCalls = $voiceState['recent_calls'];
@endphp

<div class="space-y-6">
    <div class="panel p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="max-w-3xl">
                <div class="page-eyebrow">Voice Ownership</div>
                <h2 class="mt-2 text-xl font-semibold text-white">Business teams own voice behavior. Platform still owns provider routing and live tests.</h2>
                <p class="mt-2 text-sm text-gray-400">Your team can control enablement, greeting copy, and handoff wording here. Transport, STT, LLM, TTS routing, and provider-level tests remain centralized.</p>
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
        <div class="panel p-5">
            <div class="text-xs uppercase tracking-wide text-gray-500">Active Channels</div>
            <div class="mt-2 text-3xl font-semibold text-white">{{ $voiceState['summary']['active_channels'] }}</div>
        </div>
        <div class="panel p-5">
            <div class="text-xs uppercase tracking-wide text-gray-500">Configured Channels</div>
            <div class="mt-2 text-3xl font-semibold text-white">{{ $voiceState['summary']['total_channels'] }}</div>
        </div>
        <div class="panel p-5">
            <div class="text-xs uppercase tracking-wide text-gray-500">Recent Calls</div>
            <div class="mt-2 text-3xl font-semibold text-white">{{ $voiceState['summary']['recent_calls'] }}</div>
        </div>
        <div class="panel p-5">
            <div class="text-xs uppercase tracking-wide text-gray-500">Voice Minutes This Period</div>
            <div class="mt-2 text-3xl font-semibold text-white">{{ number_format($voiceState['summary']['voice_minutes_used'], 1) }}</div>
        </div>
    </div>

    <div class="grid gap-6 xl:grid-cols-[1fr,1fr]">
        <form method="POST" action="{{ route('settings.voice.update') }}" class="panel p-6 space-y-5">
            @csrf
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h3 class="text-sm font-semibold text-white">Business-owned voice behavior</h3>
                    <p class="mt-1 text-xs text-gray-500">These settings affect copy and local business rollout, not platform provider topology.</p>
                </div>
                <label class="inline-flex items-center gap-2 text-sm text-gray-300">
                    <input type="checkbox" name="voice[enabled]" value="1" @checked(old('voice.enabled', $settings['enabled'] ?? false)) class="rounded border-gray-700 bg-gray-950 text-indigo-500">
                    Enable voice
                </label>
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

            <button class="rounded-lg bg-indigo-500 px-4 py-2 text-sm font-medium text-white">Save Voice Settings</button>
        </form>

        <div class="space-y-6">
            <form method="POST" action="{{ route('settings.voice.routing.update') }}" class="panel p-6 space-y-5 {{ !$managedByAdmin ? 'pointer-events-none opacity-70' : '' }}">
                @csrf
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <h3 class="text-sm font-semibold text-white">Platform-managed routing</h3>
                        <p class="mt-1 text-xs text-gray-500">Provider selection and runtime routing stay under centralized control.</p>
                    </div>
                    @unless($managedByAdmin)
                        <span class="rounded-full border border-amber-500/30 bg-amber-500/10 px-3 py-1 text-xs text-amber-200">Read-only</span>
                    @endunless
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    @foreach(['transport' => 'Transport', 'stt' => 'Speech-to-Text', 'llm' => 'Streaming LLM', 'tts' => 'Text-to-Speech'] as $kind => $label)
                        <div>
                            <label class="mb-1 block text-xs text-gray-500">{{ $label }}</label>
                            <select name="voice[{{ $kind }}_provider]" class="w-full field">
                                <option value="">Select {{ strtolower($label) }}</option>
                                @foreach($voiceState['options'][$kind] as $provider => $providerLabel)
                                    <option value="{{ $provider }}" @selected(old("voice.$kind"."_provider", $settings[$kind . '_provider'] ?? null) === $provider)>{{ $providerLabel }}</option>
                                @endforeach
                            </select>
                            <div class="mt-1 text-xs text-gray-500">Platform default: {{ ucfirst($voiceState['platform']['defaults'][$kind] ?? 'not set') }}</div>
                        </div>
                    @endforeach
                </div>

                @if($managedByAdmin)
                    <button class="rounded-lg bg-indigo-500 px-4 py-2 text-sm font-medium text-white">Save Routing</button>
                @endif
            </form>

            <div class="panel p-6">
                <h3 class="text-sm font-semibold text-white">Configured Voice Channels</h3>
                <div class="mt-4 space-y-3">
                    @forelse($voiceChannels as $channel)
                        <div class="panel-subtle px-4 py-3">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <div class="text-sm font-medium text-white">{{ $channel->config['label'] ?? strtoupper($channel->provider) }}</div>
                                    <div class="mt-1 text-xs text-gray-500">{{ $channel->phone_number ?: 'No number assigned yet' }}</div>
                                    <div class="mt-1 text-xs text-gray-500">Flow: {{ $channel->config['inbound_flow'] ?? 'default' }}</div>
                                </div>
                                <div class="text-xs {{ $channel->is_enabled ? 'text-emerald-400' : 'text-gray-500' }}">{{ $channel->is_enabled ? 'Enabled' : 'Disabled' }}</div>
                            </div>
                        </div>
                    @empty
                        <div class="rounded-xl border border-dashed border-gray-800 px-4 py-6 text-sm text-gray-500">No voice channels configured yet.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <div class="grid gap-6 xl:grid-cols-2">
        <div class="panel p-6">
            <h3 class="text-sm font-semibold text-white">Platform-owned live operations</h3>
            <div class="mt-4 space-y-4 text-sm text-gray-300">
                <div class="panel-subtle px-4 py-3">
                    <div class="font-medium text-white">Provider readiness and test actions</div>
                    <div class="mt-1 text-gray-500">Live transport, STT, LLM, and TTS tests stay under the admin console so platform credentials and side effects remain centralized.</div>
                </div>
                <div class="panel-subtle px-4 py-3">
                    <div class="font-medium text-white">What the business can still see</div>
                    <div class="mt-1 text-gray-500">Current routing choices, rollout blockers, call history, and active channels remain visible so drift and partial-state issues are not hidden.</div>
                </div>
                <div class="panel-subtle px-4 py-3">
                    <div class="font-medium text-white">Architectural note</div>
                    <div class="mt-1 text-gray-500">Current architectural weakness: the shared brain is not visually or structurally centralized enough.</div>
                </div>
            </div>
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
</div>
</x-layouts.app>
