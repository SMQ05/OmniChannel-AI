<x-admin.layouts.admin title="All Businesses">

{{-- Search + filter --}}
<form method="GET" action="{{ route('admin.businesses.index') }}" class="flex flex-wrap gap-2 mb-6">
    <input type="text" name="search" value="{{ $filters['search'] ?? '' }}"
           placeholder="Search by name or slug…"
           class="flex-1 min-w-48 bg-gray-800 border border-gray-700 text-gray-300 text-sm rounded-lg px-3 py-2
                  placeholder-gray-600 focus:ring-indigo-500 focus:border-indigo-500">

    <select name="plan"
            class="bg-gray-800 border border-gray-700 text-gray-300 text-sm rounded-lg px-3 py-2">
        <option value="">All Plans</option>
        @foreach($plans as $plan)
            <option value="{{ $plan->code }}" {{ ($filters['plan'] ?? '') === $plan->code ? 'selected' : '' }}>
                {{ $plan->name }}
            </option>
        @endforeach
    </select>

    <select name="status"
            class="bg-gray-800 border border-gray-700 text-gray-300 text-sm rounded-lg px-3 py-2">
        <option value="">All Statuses</option>
        <option value="active"   {{ ($filters['status'] ?? '') === 'active'   ? 'selected' : '' }}>Active</option>
        <option value="inactive" {{ ($filters['status'] ?? '') === 'inactive' ? 'selected' : '' }}>Inactive</option>
    </select>

    <button type="submit"
            class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-medium rounded-lg transition-colors">
        Filter
    </button>
</form>

{{-- Businesses table --}}
<div class="rounded-2xl bg-gray-900/60 backdrop-blur border border-gray-800 overflow-hidden">
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
                        <div x-data="{ open: false }" class="relative">
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
                                 class="absolute z-10 top-full mt-1 left-0 bg-gray-800 border border-gray-700 rounded-lg shadow-xl overflow-hidden min-w-28"
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
                        @endphp
                        <form method="POST" action="{{ route('admin.businesses.update-subscription', $business) }}" class="grid gap-4 rounded-2xl border border-gray-800 bg-gray-900/60 p-5">
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
                                    <input type="text" name="status" value="{{ $subscription?->status ?? $business->plan }}" class="w-full rounded-lg border border-gray-700 bg-gray-950 px-3 py-2 text-sm text-white">
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs text-gray-500">Warn Ratio</label>
                                    <input type="number" step="0.01" min="0" max="1" name="warn_at_ratio" value="{{ $subscription?->warn_at_ratio ?? 0.80 }}" class="w-full rounded-lg border border-gray-700 bg-gray-950 px-3 py-2 text-sm text-white">
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs text-gray-500">Period Start</label>
                                    <input type="date" name="current_period_start" value="{{ optional($subscription?->current_period_start)->toDateString() }}" class="w-full rounded-lg border border-gray-700 bg-gray-950 px-3 py-2 text-sm text-white">
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs text-gray-500">Period End</label>
                                    <input type="date" name="current_period_end" value="{{ optional($subscription?->current_period_end)->toDateString() }}" class="w-full rounded-lg border border-gray-700 bg-gray-950 px-3 py-2 text-sm text-white">
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

    @if($businesses->hasPages())
        <div class="px-5 py-4 border-t border-gray-800">
            {{ $businesses->links() }}
        </div>
    @endif
</div>

</x-admin.layouts.admin>
