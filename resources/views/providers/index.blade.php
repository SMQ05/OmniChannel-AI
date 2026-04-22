<x-layouts.app title="Providers">

<div class="flex items-center justify-between mb-6">
    <p class="text-sm text-gray-500">{{ $providers->count() }} provider{{ $providers->count() !== 1 ? 's' : '' }}</p>
    <a href="{{ route('providers.create') }}"
       class="flex items-center gap-2 px-4 py-2 btn-primary">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
        Add Provider
    </a>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
    @forelse($providers as $provider)
        <div class="relative panel overflow-hidden
                    {{ !$provider->is_active ? 'opacity-60' : '' }}">

            {{-- Active indicator --}}
            <div class="absolute top-4 right-4">
                <button
                    x-data
                    @click="
                        fetch('{{ route('providers.toggle', $provider) }}', {
                            method: 'PATCH',
                            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }
                        }).then(() => location.reload())
                    "
                    class="flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium border transition-colors
                           {{ $provider->is_active
                               ? 'bg-emerald-500/20 text-emerald-400 border-emerald-500/30 hover:bg-emerald-500/30'
                               : 'bg-gray-700 text-gray-500 border-gray-600 hover:bg-gray-600' }}">
                    <span class="w-1.5 h-1.5 rounded-full {{ $provider->is_active ? 'bg-emerald-400' : 'bg-gray-500' }}"></span>
                    {{ $provider->is_active ? 'Active' : 'Inactive' }}
                </button>
            </div>

            <div class="p-5">
                <div class="flex items-start gap-3">
                    <div class="w-10 h-10 rounded-full bg-indigo-600/20 border border-indigo-500/30 flex items-center justify-center text-sm font-bold text-indigo-300 flex-shrink-0">
                        {{ strtoupper(substr($provider->name, 0, 1)) }}
                    </div>
                    <div class="flex-1 min-w-0 pr-16">
                        <p class="text-sm font-bold text-white">{{ $provider->displayName() }}</p>
                        @if($provider->specialization)
                            <p class="text-xs text-gray-500 mt-0.5">{{ $provider->specialization }}</p>
                        @endif
                    </div>
                </div>

                <div class="mt-4 flex items-center justify-between">
                    <span class="text-xs text-gray-600">{{ $provider->slot_duration_minutes }}min slots</span>
                    <span class="text-xs text-gray-600">{{ $provider->appointments_count ?? 0 }} appointments</span>
                </div>
                <div class="mt-2 flex items-center justify-between">
                    <span class="text-xs {{ ($provider->services_count ?? 0) > 0 ? 'text-emerald-400' : 'text-amber-300' }}">
                        {{ $provider->services_count ?? 0 }} mapped service{{ ($provider->services_count ?? 0) === 1 ? '' : 's' }}
                    </span>
                    @if(($provider->services_count ?? 0) === 0)
                        <span class="text-xs text-amber-300">Needs mapping</span>
                    @endif
                </div>

                {{-- Working days chips --}}
                <div class="mt-3 flex flex-wrap gap-1">
                    @foreach(['mon','tue','wed','thu','fri','sat','sun'] as $day)
                        @php $active = $provider->working_hours[$day]['active'] ?? ($provider->working_hours[match($day) {
                            'mon' => 'monday', 'tue' => 'tuesday', 'wed' => 'wednesday',
                            'thu' => 'thursday', 'fri' => 'friday', 'sat' => 'saturday', 'sun' => 'sunday'
                        }]['active'] ?? false); @endphp
                        <span class="px-1.5 py-0.5 rounded text-xs uppercase font-semibold
                            {{ $active ? 'bg-indigo-500/20 text-indigo-400' : 'bg-gray-800 text-gray-600' }}">
                            {{ $day }}
                        </span>
                    @endforeach
                </div>
            </div>

            <div class="px-5 py-3 border-t border-gray-800">
                <a href="{{ route('providers.show', $provider) }}"
                   class="text-xs text-[var(--brand)] hover:text-[var(--brand-strong)] transition-colors">
                    Edit provider →
                </a>
            </div>
        </div>
    @empty
        <div class="col-span-3 rounded-2xl bg-gray-900/60 border border-gray-800 p-16 text-center">
            <p class="text-gray-600 text-sm">No providers yet. Add your first provider to get started.</p>
        </div>
    @endforelse
</div>

</x-layouts.app>
