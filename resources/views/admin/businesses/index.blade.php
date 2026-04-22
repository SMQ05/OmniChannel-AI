<x-admin.layouts.admin title="Businesses">

{{-- Search + filter --}}
<form method="GET" action="{{ route('admin.businesses.index') }}" class="flex flex-wrap gap-2 mb-6">
    <input type="text" name="search" value="{{ $filters['search'] ?? '' }}"
           placeholder="Search by name or slug…"
           class="flex-1 min-w-48 field
                  placeholder-gray-600 focus:ring-indigo-500 focus:border-indigo-500">

    <select name="plan"
            class="field">
        <option value="">All Plans</option>
        @foreach($plans as $plan)
            <option value="{{ $plan->code }}" {{ ($filters['plan'] ?? '') === $plan->code ? 'selected' : '' }}>
                {{ $plan->name }}
            </option>
        @endforeach
    </select>

    <select name="status"
            class="field">
        <option value="">All Statuses</option>
        <option value="active"   {{ ($filters['status'] ?? '') === 'active'   ? 'selected' : '' }}>Active</option>
        <option value="inactive" {{ ($filters['status'] ?? '') === 'inactive' ? 'selected' : '' }}>Inactive</option>
    </select>

    <button type="submit"
            class="px-4 py-2 btn-primary">
        Filter
    </button>
</form>

{{-- Businesses table --}}
<div class="panel overflow-visible">
    <div class="overflow-x-auto overflow-y-visible">
    <table class="w-full text-sm">
        <thead>
            <tr class="border-b border-gray-800">
                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Business</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Plan</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Status</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Appts This Month</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Last Active</th>
                <th class="px-5 py-3"></th>
            </tr>
        </thead>
        @forelse($businesses as $business)
            <tbody x-data="{ openControls: false }" class="divide-y divide-gray-800">
                <tr class="hover:bg-gray-800/30 transition-colors">

                    {{-- Business name + type --}}
                    <td class="px-5 py-3.5">
                        <p class="font-semibold text-white">{{ $business->name }}</p>
                        <p class="text-xs text-gray-500">{{ $business->slug }} · {{ $business->business_type }}</p>
                    </td>

                    {{-- Plan --}}
                    <td class="px-5 py-3.5">
                        @php
                            $activePlan = $business->subscription?->plan;
                            $planCode = $activePlan?->code ?? $business->plan;
                            $planLabel = $activePlan?->name ?? ucfirst($business->plan);
                        @endphp
                        <div x-data="{ open: false }" class="relative z-20">
                            <button @click="open = !open"
                                    class="px-2.5 py-1 rounded-full text-xs font-medium border transition-colors
                                           {{ match($planCode) {
                                               'enterprise' => 'bg-purple-500/20 text-purple-400 border-purple-500/30',
                                               'pro'        => 'bg-indigo-500/20 text-indigo-400 border-indigo-500/30',
                                               'starter'    => 'bg-blue-500/20 text-blue-400 border-blue-500/30',
                                               default      => 'bg-gray-700 text-gray-400 border-gray-600',
                                           } }}">
                                {{ $planLabel }} ▾
                            </button>

                            <div x-show="open" @click.outside="open = false"
                                 class="absolute z-30 top-full mt-1 left-0 bg-gray-800 border border-gray-700 rounded-lg shadow-xl overflow-hidden min-w-40"
                                 style="display:none">
                                @foreach($plans as $plan)
                                    <form method="POST" action="{{ route('admin.businesses.update-plan', $business) }}">
                                        @csrf @method('PATCH')
                                        <input type="hidden" name="plan" value="{{ $plan->code }}">
                                        <button type="submit"
                                                class="w-full px-4 py-2 text-left text-sm text-gray-300 hover:bg-gray-700 hover:text-white transition-colors
                                                       {{ $planCode === $plan->code ? 'text-indigo-400' : '' }}">
                                            {{ $plan->name }}
                                        </button>
                                    </form>
                                @endforeach
                            </div>
                        </div>
                    </td>

                    {{-- Status toggle --}}
                    <td class="px-5 py-3.5">
                        <button
                            x-data="{ active: {{ $business->is_active ? 'true' : 'false' }} }"
                            @click="
                                fetch('{{ route('admin.businesses.toggle', $business) }}', {
                                    method: 'PATCH',
                                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }
                                }).then(r => r.json()).then(d => active = d.is_active)
                            "
                            class="flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium border transition-colors"
                            :class="active
                                ? 'bg-emerald-500/20 text-emerald-400 border-emerald-500/30 hover:bg-emerald-500/30'
                                : 'bg-gray-700 text-gray-500 border-gray-600 hover:bg-gray-600'">
                            <span class="w-1.5 h-1.5 rounded-full" :class="active ? 'bg-emerald-400' : 'bg-gray-500'"></span>
                            <span x-text="active ? 'Active' : 'Inactive'"></span>
                        </button>
                    </td>

                    {{-- Appointments this month --}}
                    <td class="px-5 py-3.5 text-gray-400 tabular-nums">
                        {{ number_format($business->appointments_this_month ?? 0) }}
                    </td>

                    {{-- Last active --}}
                    <td class="px-5 py-3.5 text-gray-500 text-xs">
                        {{ $business->last_active
                            ? \Carbon\Carbon::parse($business->last_active)->diffForHumans()
                            : '—' }}
                    </td>

                    {{-- Actions --}}
                    <td class="px-5 py-3.5">
                        <div class="flex items-center gap-2 justify-end">
                            <a href="{{ route('admin.billing.show', $business) }}"
                               class="px-3 py-1.5 bg-indigo-500/20 text-indigo-300 border border-indigo-500/30 rounded-lg text-xs font-medium hover:bg-indigo-500/30 transition-colors">
                                Billing
                            </a>
                            <button type="button"
                                    @click="openControls = !openControls"
                                    class="px-3 py-1.5 bg-gray-800 text-gray-300 border border-gray-700 rounded-lg text-xs font-medium hover:bg-gray-700 transition-colors">
                                Controls
                            </button>
                            <form method="POST" action="{{ route('admin.businesses.impersonate', $business) }}">
                                @csrf
                                <button type="submit"
                                        class="px-3 py-1.5 bg-amber-500/20 text-amber-400 border border-amber-500/30 rounded-lg text-xs font-medium
                                               hover:bg-amber-500/30 transition-colors">
                                    Impersonate
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                <tr x-show="openControls" x-cloak class="bg-gray-950/50">
                    <td colspan="6" class="px-5 py-5">
                        @php
                            $subscription = $business->subscription;
                            $messagingStates = $messagingStatesByBusiness[$business->id] ?? [];
                        @endphp
                        <form method="POST" action="{{ route('admin.businesses.update-subscription', $business) }}" class="grid gap-4 panel p-5">
                            @csrf
                            @method('PATCH')

                            <div class="flex items-center justify-between gap-4">
                                <div>
                                    <div class="text-sm font-semibold text-white">Subscription Controls</div>
                                    <div class="text-xs text-gray-500">Override quotas, feature flags, and enforcement behavior for {{ $business->name }}.</div>
                                </div>
                                <div class="text-xs text-gray-500">
                                    Current status: {{ $subscription?->status ?? $business->plan }}
                                </div>
                            </div>

                            <div class="grid gap-4 md:grid-cols-4">
                                <div>
                                    <label class="mb-1 block text-xs text-gray-500">Status</label>
                                    <input type="text" name="status" value="{{ $subscription?->status ?? $business->plan }}" class="w-full field">
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs text-gray-500">Warn Ratio</label>
                                    <input type="number" step="0.01" min="0" max="1" name="warn_at_ratio" value="{{ $subscription?->warn_at_ratio ?? 0.80 }}" class="w-full field">
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs text-gray-500">Period Start</label>
                                    <input type="date" name="current_period_start" value="{{ optional($subscription?->current_period_start)->toDateString() }}" class="w-full field">
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs text-gray-500">Period End</label>
                                    <input type="date" name="current_period_end" value="{{ optional($subscription?->current_period_end)->toDateString() }}" class="w-full field">
                                </div>
                            </div>

                            <div class="grid gap-4 md:grid-cols-3">
                                <label class="flex items-center gap-2 text-sm text-gray-300">
                                    <input type="checkbox" name="enforce_limits" value="1" {{ $subscription?->enforce_limits ? 'checked' : '' }}>
                                    Enforce limits
                                </label>
                                <label class="flex items-center gap-2 text-sm text-gray-300">
                                    <input type="checkbox" name="admin_override" value="1" {{ $subscription?->admin_override ? 'checked' : '' }}>
                                    Admin override
                                </label>
                            </div>

                            <div class="grid gap-4 xl:grid-cols-3">
                                <div>
                                    <label class="mb-1 block text-xs text-gray-500">Quota Overrides JSON</label>
                                    <textarea name="included_quotas" rows="6" class="w-full rounded-lg border border-gray-700 bg-gray-950 px-3 py-2 font-mono text-xs text-white">{{ json_encode($subscription?->included_quotas ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</textarea>
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs text-gray-500">Feature Flags JSON</label>
                                    <textarea name="feature_flags" rows="6" class="w-full rounded-lg border border-gray-700 bg-gray-950 px-3 py-2 font-mono text-xs text-white">{{ json_encode($subscription?->feature_flags ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</textarea>
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs text-gray-500">Overage Counters JSON</label>
                                    <textarea name="overage_counters" rows="6" class="w-full rounded-lg border border-gray-700 bg-gray-950 px-3 py-2 font-mono text-xs text-white">{{ json_encode($subscription?->overage_counters ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</textarea>
                                </div>
                            </div>

                            <div class="flex justify-end">
                                <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500 transition-colors">
                                    Save Subscription Controls
                                </button>
                            </div>
                        </form>

                        <form method="POST" action="{{ route('admin.businesses.update-owner-password', $business) }}" class="mt-4 grid gap-4 panel p-5">
                            @csrf
                            @method('PATCH')

                            <div>
                                <div class="text-sm font-semibold text-white">Reset Business Login Password</div>
                                <div class="text-xs text-gray-500">Updates the primary business owner password. Use this if the clinic cannot change its login password from the profile page.</div>
                            </div>

                            <div class="grid gap-4 md:grid-cols-2">
                                <div>
                                    <label class="mb-1 block text-xs text-gray-500">New Password</label>
                                    <input type="password" name="password" class="w-full field">
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs text-gray-500">Confirm Password</label>
                                    <input type="password" name="password_confirmation" class="w-full field">
                                </div>
                            </div>

                            <div class="flex justify-end">
                                <button type="submit" class="rounded-lg bg-amber-600 px-4 py-2 text-sm font-medium text-white hover:bg-amber-500 transition-colors">
                                    Reset Owner Password
                                </button>
                            </div>
                        </form>

                        <div class="mt-4 grid gap-4 panel p-5">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <div class="text-sm font-semibold text-white">Messaging Control Plane</div>
                                    <div class="text-xs text-gray-500">Admin-only credentials, provider internals, routing details, live tests, and explicit disconnect actions. Twilio WhatsApp remains legacy-only here, not the default onboarding path.</div>
                                </div>
                                <div class="text-xs text-gray-500">First-class records replace new writes to <code>businesses.channel_config</code>.</div>
                            </div>

                            <div class="grid gap-4 xl:grid-cols-2">
                                @foreach(['whatsapp' => 'WhatsApp', 'messenger' => 'Messenger'] as $channelKey => $channelLabel)
                                    @php($state = $messagingStates[$channelKey] ?? null)
                                    <div class="rounded-xl border border-gray-800 p-4 space-y-4">
                                        <div class="flex items-start justify-between gap-4">
                                            <div>
                                                <div class="text-sm font-semibold text-white">{{ $channelLabel }}</div>
                                                <div class="mt-1 text-xs {{ ($state['connected'] ?? false) ? 'text-emerald-400' : 'text-amber-300' }}">
                                                    {{ ($state['connected'] ?? false) ? 'Connection ready' : 'Connection incomplete' }}
                                                </div>
                                                <div class="mt-1 text-xs text-gray-500">
                                                    Business status: {{ ucfirst($state['status'] ?? 'inactive') }}
                                                    @if(($state['connection_source'] ?? null) === 'legacy_channel_config')
                                                        · legacy fallback read active
                                                    @endif
                                                </div>
                                            </div>
                                            <div class="text-right text-xs text-gray-500">
                                                <div>{{ $state['provider_label'] ?? 'Unknown provider' }}</div>
                                                @if(!empty($state['summary']['identifier']))
                                                    <div class="mt-1">{{ $state['summary']['identifier'] }}</div>
                                                @endif
                                            </div>
                                        </div>

                                        <form method="POST" action="{{ route('admin.businesses.messaging.update', [$business, $channelKey]) }}" class="space-y-3">
                                            @csrf
                                            @method('PATCH')

                                            @if($channelKey === 'whatsapp')
                                                <div>
                                                    <label class="mb-1 block text-xs text-gray-500">Provider</label>
                                                    <select name="provider" class="w-full field">
                                                        <option value="meta_cloud" @selected(old('provider', $state['provider'] ?? 'meta_cloud') === 'meta_cloud')>Meta WhatsApp Cloud</option>
                                                        <option value="twilio" @selected(old('provider', $state['provider'] ?? 'meta_cloud') === 'twilio')>Twilio WhatsApp (Legacy)</option>
                                                    </select>
                                                </div>
                                                <input type="text" name="phone_number_id" value="{{ old('phone_number_id', $state['runtime_config']['phone_number_id'] ?? '') }}" class="w-full field" placeholder="Phone Number ID">
                                                <input type="password" name="access_token" class="w-full field" placeholder="Access Token">
                                                <input type="password" name="verify_token" class="w-full field" placeholder="Verify Token">
                                                <input type="password" name="app_secret" class="w-full field" placeholder="App Secret">
                                                <input type="text" name="twilio_account_sid" class="w-full field" placeholder="Twilio Account SID">
                                                <input type="password" name="twilio_auth_token" class="w-full field" placeholder="Twilio Auth Token">
                                                <input type="text" name="twilio_from_number" value="{{ old('twilio_from_number', $state['runtime_config']['twilio_from_number'] ?? '') }}" class="w-full field" placeholder="Twilio From Number">
                                            @else
                                                <input type="text" name="page_id" value="{{ old('page_id', $state['runtime_config']['page_id'] ?? '') }}" class="w-full field" placeholder="Page ID">
                                                <input type="password" name="access_token" class="w-full field" placeholder="Access Token">
                                                <input type="password" name="verify_token" class="w-full field" placeholder="Verify Token">
                                                <input type="password" name="app_secret" class="w-full field" placeholder="App Secret">
                                            @endif

                                            <div class="flex flex-wrap gap-2">
                                                <button type="submit" class="rounded-lg bg-indigo-600 px-4 py-2 text-xs font-medium text-white hover:bg-indigo-500">Save Connection</button>
                                                <button type="button"
                                                        x-data="{ result: '' }"
                                                        @click="
                                                            fetch('{{ route('admin.businesses.messaging.test', [$business, $channelKey]) }}', {
                                                                method: 'POST',
                                                                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }
                                                            }).then(r => r.json()).then(d => { result = d.message; $el.nextElementSibling.textContent = d.message; $el.nextElementSibling.className = d.success ? 'text-xs text-emerald-400' : 'text-xs text-red-400'; });
                                                        "
                                                        class="rounded-lg border border-gray-700 px-4 py-2 text-xs font-medium text-gray-200 hover:bg-gray-800">
                                                    Test Connection
                                                </button>
                                                <span class="self-center text-xs text-gray-500">
                                                    {{ $state['last_test_message'] ?? 'No connection test yet.' }}
                                                </span>
                                            </div>
                                        </form>

                                        <form method="POST" action="{{ route('admin.businesses.messaging.disconnect', [$business, $channelKey]) }}" class="rounded-lg border border-red-500/20 bg-red-500/5 p-3 space-y-3">
                                            @csrf
                                            @method('PATCH')
                                            <label class="flex items-center gap-2 text-xs text-red-200">
                                                <input type="checkbox" name="confirm_disconnect" value="1">
                                                Confirm disconnect and secret removal.
                                            </label>
                                            <input type="text" name="disconnect_reason" class="w-full field" placeholder="Optional disconnect reason for audit log">
                                            <button type="submit" class="rounded-lg border border-red-500/30 bg-red-500/10 px-4 py-2 text-xs font-medium text-red-200 hover:bg-red-500/20">
                                                Disconnect {{ $channelLabel }}
                                            </button>
                                        </form>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <div class="mt-4 grid gap-4 panel p-5">
                            <div>
                                <div class="text-sm font-semibold text-white">Managed Setup Shortcuts</div>
                                <div class="text-xs text-gray-500">Jump into the tenant setup pages as SaaS admin to handle one-time onboarding, AI training, and channel configuration.</div>
                            </div>

                            <div class="flex flex-wrap gap-2">
                                @foreach ([
                                    ['/settings/ai', 'AI Training'],
                                    ['/settings/channels', 'Channels'],
                                    ['/settings/integrations', 'Integrations'],
                                    ['/settings/reminders', 'Reminders'],
                                    ['/settings/voice', 'Voice'],
                                ] as [$targetPath, $label])
                                    <form method="POST" action="{{ route('admin.businesses.impersonate', $business) }}">
                                        @csrf
                                        <input type="hidden" name="redirect_to" value="{{ $targetPath }}">
                                        <button type="submit" class="rounded-lg border border-indigo-500/30 bg-indigo-500/10 px-3 py-2 text-xs font-medium text-indigo-300 hover:bg-indigo-500/20">
                                            Open {{ $label }}
                                        </button>
                                    </form>
                                @endforeach
                            </div>
                        </div>
                    </td>
                </tr>
            </tbody>
        @empty
            <tbody class="divide-y divide-gray-800">
                <tr>
                    <td colspan="6" class="px-5 py-16 text-center text-gray-600">No businesses found.</td>
                </tr>
            </tbody>
        @endforelse
    </table>
    </div>

    @if($businesses->hasPages())
        <div class="px-5 py-4 border-t border-gray-800">
            {{ $businesses->links() }}
        </div>
    @endif
</div>

</x-admin.layouts.admin>
