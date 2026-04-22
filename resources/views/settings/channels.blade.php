<x-layouts.app title="Channels">

<div class="mx-auto max-w-6xl space-y-6">
    <div class="panel p-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div class="max-w-3xl">
                <div class="page-eyebrow">Messaging Ownership</div>
                <h2 class="mt-2 text-xl font-semibold text-white">Business approval and live enablement now sit on first-class channel records.</h2>
                <p class="mt-2 text-sm text-gray-400">This page is business-owned for readiness visibility, approval, enable, and explicit disable only. Credentials, webhook signing, provider tests, and routing internals stay admin-only.</p>
            </div>
            <div class="rounded-xl border border-gray-800 px-4 py-3 text-sm text-gray-300">
                <div class="font-medium text-white">Architectural note</div>
                <div class="mt-1 text-gray-500">Current architectural weakness: the shared brain is not visually or structurally centralized enough. Phase 5 improves ownership clarity without pretending the whole control-plane is unified already.</div>
            </div>
        </div>
    </div>

    <div class="grid gap-6 xl:grid-cols-[0.95fr,1.05fr]">
        <form method="POST" action="{{ route('settings.channels.update') }}" class="panel p-6 space-y-5">
            @csrf
            <div>
                <h2 class="text-sm font-semibold text-white">Business-owned activation</h2>
                <p class="mt-1 text-xs text-gray-500">A channel can only go live after the admin-managed connection record is complete.</p>
            </div>

            @foreach(['whatsapp' => 'WhatsApp', 'messenger' => 'Messenger'] as $key => $label)
                @php($state = $channels[$key])
                <div class="rounded-xl border border-gray-800 p-4 space-y-4">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <div class="text-sm font-medium text-white">{{ $label }}</div>
                            <div class="mt-1 text-xs {{ $state['connected'] ? 'text-emerald-400' : 'text-amber-300' }}">
                                {{ $state['connected'] ? 'Managed connection ready' : 'Waiting on admin-managed connection' }}
                            </div>
                            <div class="mt-2 text-xs text-gray-500">
                                Provider: {{ $state['provider_label'] }}
                                @if($state['summary']['identifier'])
                                    · {{ $state['summary']['identifier'] }}
                                @endif
                            </div>
                            <div class="mt-1 text-xs text-gray-500">
                                Status: {{ ucfirst($state['status']) }}
                                @if($state['enabled_at'])
                                    · enabled {{ $state['enabled_at']->diffForHumans() }}
                                @endif
                            </div>
                        </div>
                        <label class="inline-flex items-center gap-2 text-sm text-gray-300">
                            <input type="checkbox" name="{{ $key }}[enabled]" value="1" class="rounded border-gray-700 bg-gray-950 text-indigo-500" @checked(old("$key.enabled", $state['enabled']))>
                            Live
                        </label>
                    </div>

                    @if($state['enabled'])
                        <div class="rounded-lg border border-amber-500/20 bg-amber-500/10 px-4 py-3">
                            <label class="flex items-center gap-2 text-sm text-amber-100">
                                <input type="checkbox" name="confirm_disable[{{ $key }}]" value="1" class="rounded border-amber-400/40 bg-transparent text-amber-300">
                                Confirm any disable of this live channel.
                            </label>
                            <input type="text" name="disable_reason[{{ $key }}]" value="{{ old("disable_reason.$key") }}" class="mt-3 w-full field" placeholder="Optional disable reason for audit log">
                        </div>
                    @endif

                    @if($errors->has("$key.enabled") || $errors->has("confirm_disable.$key"))
                        <div class="text-xs text-red-400">
                            {{ $errors->first("$key.enabled") ?: $errors->first("confirm_disable.$key") }}
                        </div>
                    @endif

                    @if($state['errors'] !== [])
                        <div class="rounded-lg border border-red-500/20 bg-red-500/10 px-4 py-3 text-xs text-red-300">
                            {{ implode(' ', $state['errors']) }}
                        </div>
                    @endif
                </div>
            @endforeach

            <div class="flex justify-end">
                <button type="submit" class="btn-primary px-6 py-2.5">Save Messaging Ownership</button>
            </div>
        </form>

        <div class="space-y-6">
            <div class="panel p-6">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-sm font-semibold text-white">Admin-managed connection summary</h2>
                        <p class="mt-1 text-xs text-gray-500">Business users can see readiness, provider label, and webhook endpoint only. Secrets, live provider tests, webhook signing internals, and routing internals remain hidden.</p>
                    </div>
                    <span class="rounded-full border border-amber-500/30 bg-amber-500/10 px-3 py-1 text-xs text-amber-200">Read-only</span>
                </div>

                <div class="mt-5 space-y-5">
                    @foreach(['whatsapp' => 'WhatsApp', 'messenger' => 'Messenger'] as $key => $label)
                        @php($state = $channels[$key])
                        <div class="rounded-xl border border-gray-800 p-4">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <div class="text-sm font-medium text-white">{{ $label }}</div>
                                    <div class="mt-1 text-xs text-gray-500">Webhook: {{ $webhookBase }}/{{ $key }}/{{ $business->slug }}</div>
                                    <div class="mt-1 text-xs text-gray-500">{{ $state['summary']['webhook_mode'] }}</div>
                                </div>
                                <div class="text-right text-xs {{ $state['connected'] ? 'text-emerald-400' : 'text-amber-300' }}">
                                    <div>{{ $state['connected'] ? 'Connected' : 'Not ready' }}</div>
                                    @if($state['last_tested_at'])
                                        <div class="mt-1 text-gray-500">Last admin test {{ $state['last_tested_at']->diffForHumans() }}</div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>

</x-layouts.app>
