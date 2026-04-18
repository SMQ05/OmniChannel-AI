<x-layouts.app title="Diagnostics">
<div class="space-y-6">
    <div class="rounded-2xl border border-gray-800 bg-gray-900/60 p-6">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h1 class="text-xl font-semibold text-white">Diagnostics</h1>
                <p class="mt-1 text-sm text-gray-400">Operational status for webhooks, queues, workers, integrations, and replay controls.</p>
            </div>
            <div class="text-right text-xs text-gray-500">
                <div>Version: {{ $diagnostics['version']['app_version'] ?? 'unknown' }}</div>
                <div>Commit: {{ $diagnostics['version']['git_commit'] ?? 'snapshot' }}</div>
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-300">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="rounded-xl border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-300">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        <div class="rounded-2xl border border-gray-800 bg-gray-900/60 p-5">
            <div class="text-xs uppercase tracking-wide text-gray-500">Queue</div>
            <div class="mt-2 text-sm text-white">{{ $diagnostics['queue']['connection'] }} / {{ $diagnostics['queue']['queue_name'] }}</div>
            <div class="mt-2 text-sm {{ $diagnostics['queue']['status']['ok'] ? 'text-emerald-400' : 'text-red-400' }}">
                {{ $diagnostics['queue']['status']['detail'] }}
            </div>
            @if($diagnostics['queue']['depths'] !== [])
                <div class="mt-3 space-y-1 text-xs text-gray-500">
                    @foreach($diagnostics['queue']['depths'] as $queue => $depth)
                        <div>{{ $queue }}: {{ $depth ?? 'n/a' }}</div>
                    @endforeach
                </div>
            @endif
            <div class="mt-3 text-xs text-gray-500">
                Worker heartbeat:
                {{ optional($diagnostics['queue']['worker_last_heartbeat'])->diffForHumans() ?? 'none' }}
            </div>
            <div class="mt-1 text-xs text-gray-500">Failed jobs: {{ $diagnostics['queue']['failed_jobs_count'] ?? 'n/a' }}</div>
        </div>

        <div class="rounded-2xl border border-gray-800 bg-gray-900/60 p-5">
            <div class="text-xs uppercase tracking-wide text-gray-500">Redis</div>
            <div class="mt-2 text-sm {{ $diagnostics['redis']['ok'] ? 'text-emerald-400' : 'text-red-400' }}">
                {{ $diagnostics['redis']['detail'] }}
            </div>
            <div class="mt-4 text-xs uppercase tracking-wide text-gray-500">Database Queue</div>
            <div class="mt-2 text-sm {{ $diagnostics['database_queue']['ok'] ? 'text-emerald-400' : 'text-red-400' }}">
                {{ $diagnostics['database_queue']['detail'] }}
            </div>
        </div>

        <div class="rounded-2xl border border-gray-800 bg-gray-900/60 p-5">
            <div class="text-xs uppercase tracking-wide text-gray-500">Scheduler</div>
            <div class="mt-2 text-sm text-white">
                Last heartbeat:
                {{ $diagnostics['scheduler']['last_seen'] ? \Carbon\Carbon::parse($diagnostics['scheduler']['last_seen'])->diffForHumans() : 'none' }}
            </div>
            <div class="mt-4 text-xs uppercase tracking-wide text-gray-500">Latest Sync</div>
            <div class="mt-2 text-sm text-white">
                @if($diagnostics['latest_sync'])
                    Appointment #{{ $diagnostics['latest_sync']->id }} updated {{ $diagnostics['latest_sync']->updated_at->diffForHumans() }}
                @else
                    No sync activity yet
                @endif
            </div>
        </div>

        <div class="rounded-2xl border border-gray-800 bg-gray-900/60 p-5">
            <div class="text-xs uppercase tracking-wide text-gray-500">Voice Readiness</div>
            <div class="mt-2 text-sm {{ $diagnostics['voice']['ready'] ? 'text-emerald-400' : 'text-amber-300' }}">
                {{ $diagnostics['voice']['ready'] ? 'Voice is ready for controlled rollout.' : 'Voice still has blockers.' }}
            </div>
            <div class="mt-3 text-xs text-gray-500">
                Active channels: {{ $diagnostics['voice']['summary']['active_channels'] }}
                · Recent calls: {{ $diagnostics['voice']['summary']['recent_calls'] }}
            </div>
            <div class="mt-2 text-xs text-gray-500">
                @if($diagnostics['voice']['issues'] === [])
                    No voice issues detected.
                @else
                    {{ \Illuminate\Support\Str::limit(implode(' ', $diagnostics['voice']['issues']), 140) }}
                @endif
            </div>
        </div>
    </div>

    <div class="grid gap-6 xl:grid-cols-2">
        <div class="rounded-2xl border border-gray-800 bg-gray-900/60 p-6">
            <h2 class="text-sm font-semibold text-white">Webhook Endpoints</h2>
            <div class="mt-4 space-y-3 text-sm text-gray-300">
                <div>
                    <div class="text-xs uppercase tracking-wide text-gray-500">WhatsApp</div>
                    <div>{{ $diagnostics['webhooks']['whatsapp'] }}</div>
                </div>
                <div>
                    <div class="text-xs uppercase tracking-wide text-gray-500">Messenger</div>
                    <div>{{ $diagnostics['webhooks']['messenger'] }}</div>
                </div>
                <div>
                    <div class="text-xs uppercase tracking-wide text-gray-500">Latest Inbound</div>
                    <div>
                        @if($diagnostics['latest_inbound'])
                            #{{ $diagnostics['latest_inbound']->id }} · {{ $diagnostics['latest_inbound']->status }} · {{ $diagnostics['latest_inbound']->created_at->diffForHumans() }}
                        @else
                            No inbound webhooks yet
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="rounded-2xl border border-gray-800 bg-gray-900/60 p-6">
            <h2 class="text-sm font-semibold text-white">Actions</h2>
            <div class="mt-4 grid gap-4">
                <form method="POST" action="{{ route('settings.diagnostics.failed-jobs.retry') }}" class="flex items-center justify-between gap-3 rounded-xl border border-gray-800 px-4 py-3">
                    @csrf
                    <div>
                        <div class="text-sm text-white">Retry failed jobs</div>
                        <div class="text-xs text-gray-500">Requeue everything in `failed_jobs`.</div>
                    </div>
                    <button class="rounded-lg bg-indigo-500 px-3 py-2 text-sm font-medium text-white">Retry</button>
                </form>

                <form method="POST" action="{{ route('settings.diagnostics.inbound.replay') }}" class="flex items-center justify-between gap-3 rounded-xl border border-gray-800 px-4 py-3">
                    @csrf
                    <div>
                        <div class="text-sm text-white">Replay last inbound webhook</div>
                        <div class="text-xs text-gray-500">Redispatch the most recent stored inbound payload.</div>
                    </div>
                    <button class="rounded-lg bg-emerald-500 px-3 py-2 text-sm font-medium text-white">Replay</button>
                </form>
            </div>
        </div>
    </div>

    <div class="grid gap-6 xl:grid-cols-2">
        <div class="rounded-2xl border border-gray-800 bg-gray-900/60 p-6">
            <h2 class="text-sm font-semibold text-white">Channel Tests</h2>
            <div class="mt-4 space-y-4">
                @foreach (['whatsapp', 'messenger'] as $channel)
                    <form method="POST" action="{{ route('settings.diagnostics.channels.test-send', $channel) }}" class="grid gap-3 rounded-xl border border-gray-800 p-4">
                        @csrf
                        <div class="text-sm text-white">Send test {{ ucfirst($channel) }}</div>
                        <input name="recipient" class="rounded-lg border border-gray-700 bg-gray-950 px-3 py-2 text-sm text-white" placeholder="Recipient ID / phone number">
                        <input name="message" class="rounded-lg border border-gray-700 bg-gray-950 px-3 py-2 text-sm text-white" placeholder="Optional message">
                        <button class="rounded-lg bg-indigo-500 px-3 py-2 text-sm font-medium text-white">Send Test</button>
                    </form>
                @endforeach
            </div>
        </div>

        <div class="rounded-2xl border border-gray-800 bg-gray-900/60 p-6">
            <h2 class="text-sm font-semibold text-white">Integration Tests</h2>
            <div class="mt-4 space-y-4">
                @foreach (['google_calendar' => 'Google Calendar', 'google_sheets' => 'Google Sheets'] as $service => $label)
                    <form method="POST" action="{{ route('settings.diagnostics.integrations.test', $service) }}" class="flex items-center justify-between rounded-xl border border-gray-800 p-4">
                        @csrf
                        <div>
                            <div class="text-sm text-white">{{ $label }}</div>
                            <div class="text-xs text-gray-500">Run a live connectivity check using current business credentials.</div>
                        </div>
                        <button class="rounded-lg bg-indigo-500 px-3 py-2 text-sm font-medium text-white">Test</button>
                    </form>
                @endforeach
            </div>
        </div>
    </div>

    <div class="rounded-2xl border border-gray-800 bg-gray-900/60 p-6">
        <h2 class="text-sm font-semibold text-white">Open Issues</h2>
        <div class="mt-4 space-y-3">
            @forelse($diagnostics['issues'] as $issue)
                <div class="rounded-xl border px-4 py-3 {{ $issue['severity'] === 'critical' ? 'border-red-500/30 bg-red-500/10' : 'border-amber-500/30 bg-amber-500/10' }}">
                    <div class="text-sm font-medium {{ $issue['severity'] === 'critical' ? 'text-red-300' : 'text-amber-300' }}">{{ $issue['title'] }}</div>
                    <div class="mt-1 text-sm text-gray-300">{{ $issue['detail'] }}</div>
                </div>
            @empty
                <div class="rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-300">No startup issues detected.</div>
            @endforelse
        </div>
    </div>

    <div class="grid gap-6 xl:grid-cols-2">
        <div class="rounded-2xl border border-gray-800 bg-gray-900/60 p-6">
            <h2 class="text-sm font-semibold text-white">Usage Snapshot</h2>
            <div class="mt-4 space-y-4">
                @foreach(collect($diagnostics['usage']['metrics'])->take(4) as $metric)
                    <div>
                        <div class="flex items-center justify-between gap-3 text-sm">
                            <span class="text-white">{{ $metric['label'] }}</span>
                            <span class="{{ $metric['warning'] ? 'text-amber-300' : 'text-gray-400' }}">
                                {{ number_format($metric['used'], $metric['metric'] === 'llm_tokens_estimated' ? 0 : 1) }}
                                @if($metric['limit'] !== null)
                                    / {{ number_format($metric['limit'], $metric['metric'] === 'llm_tokens_estimated' ? 0 : 1) }}
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

        <div class="rounded-2xl border border-gray-800 bg-gray-900/60 p-6">
            <h2 class="text-sm font-semibold text-white">Latest Outbound Failures</h2>
            <div class="mt-4 space-y-3">
                @forelse($diagnostics['latest_outbound_failures'] as $failure)
                    <div class="rounded-xl border border-gray-800 px-4 py-3">
                        <div class="flex items-center justify-between gap-3">
                            <div class="text-sm text-white">{{ ucfirst($failure->channel) }} → {{ $failure->recipient_platform_id }}</div>
                            <div class="text-xs text-gray-500">{{ $failure->updated_at->diffForHumans() }}</div>
                        </div>
                        <div class="mt-1 text-xs text-red-300">{{ $failure->last_error ?: 'Provider reported a failure.' }}</div>
                    </div>
                @empty
                    <div class="rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-300">No recent outbound delivery failures.</div>
                @endforelse
            </div>
        </div>
    </div>

    <div class="rounded-2xl border border-gray-800 bg-gray-900/60 p-6">
        <h2 class="text-sm font-semibold text-white">Recent Failed Jobs</h2>
        <div class="mt-4 overflow-x-auto">
            <table class="min-w-full text-left text-sm text-gray-300">
                <thead class="text-xs uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="pb-2 pr-4">Queue</th>
                        <th class="pb-2 pr-4">Connection</th>
                        <th class="pb-2 pr-4">Failed</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-800">
                    @forelse($diagnostics['queue']['recent_failed_jobs'] as $job)
                        <tr>
                            <td class="py-2 pr-4">{{ $job->queue }}</td>
                            <td class="py-2 pr-4">{{ $job->connection }}</td>
                            <td class="py-2 pr-4">{{ \Carbon\Carbon::parse($job->failed_at)->diffForHumans() }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="py-4 text-gray-500">No failed jobs recorded.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
</x-layouts.app>
