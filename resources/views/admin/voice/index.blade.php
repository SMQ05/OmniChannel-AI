<x-admin.layouts.admin title="Voice Overview">
<div class="space-y-6">
    <div class="rounded-2xl border border-gray-800 bg-gray-900/60 p-6">
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
            <div class="rounded-2xl border border-gray-800 bg-gray-900/60 p-6">
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

    <div class="rounded-2xl border border-gray-800 bg-gray-900/60 p-6">
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
</div>
</x-admin.layouts.admin>
