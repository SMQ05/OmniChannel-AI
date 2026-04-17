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
        @foreach(['trial','starter','pro','enterprise'] as $p)
            <option value="{{ $p }}" {{ ($filters['plan'] ?? '') === $p ? 'selected' : '' }}>
                {{ ucfirst($p) }}
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
        <tbody class="divide-y divide-gray-800">
            @forelse($businesses as $business)
                <tr class="hover:bg-gray-800/30 transition-colors">

                    {{-- Business name + type --}}
                    <td class="px-5 py-3.5">
                        <p class="font-semibold text-white">{{ $business->name }}</p>
                        <p class="text-xs text-gray-500">{{ $business->slug }} · {{ $business->business_type }}</p>
                    </td>

                    {{-- Plan --}}
                    <td class="px-5 py-3.5">
                        <div x-data="{ open: false }" class="relative">
                            <button @click="open = !open"
                                    class="px-2.5 py-1 rounded-full text-xs font-medium border transition-colors
                                           {{ match($business->plan) {
                                               'enterprise' => 'bg-purple-500/20 text-purple-400 border-purple-500/30',
                                               'pro'        => 'bg-indigo-500/20 text-indigo-400 border-indigo-500/30',
                                               'starter'    => 'bg-blue-500/20 text-blue-400 border-blue-500/30',
                                               default      => 'bg-gray-700 text-gray-400 border-gray-600',
                                           } }}">
                                {{ $business->plan }} ▾
                            </button>

                            <div x-show="open" @click.outside="open = false"
                                 class="absolute z-10 top-full mt-1 left-0 bg-gray-800 border border-gray-700 rounded-lg shadow-xl overflow-hidden min-w-28"
                                 style="display:none">
                                @foreach(['trial','starter','pro','enterprise'] as $plan)
                                    <form method="POST" action="{{ route('admin.businesses.update-plan', $business) }}">
                                        @csrf @method('PATCH')
                                        <input type="hidden" name="plan" value="{{ $plan }}">
                                        <button type="submit"
                                                class="w-full px-4 py-2 text-left text-sm text-gray-300 hover:bg-gray-700 hover:text-white transition-colors
                                                       {{ $business->plan === $plan ? 'text-indigo-400' : '' }}">
                                            {{ ucfirst($plan) }}
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
                        <form method="POST" action="{{ route('admin.businesses.impersonate', $business) }}">
                            @csrf
                            <button type="submit"
                                    class="px-3 py-1.5 bg-amber-500/20 text-amber-400 border border-amber-500/30 rounded-lg text-xs font-medium
                                           hover:bg-amber-500/30 transition-colors">
                                Impersonate
                            </button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="px-5 py-16 text-center text-gray-600">No businesses found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    @if($businesses->hasPages())
        <div class="px-5 py-4 border-t border-gray-800">
            {{ $businesses->links() }}
        </div>
    @endif
</div>

</x-admin.layouts.admin>
