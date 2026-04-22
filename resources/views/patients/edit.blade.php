<x-layouts.app title="Edit Patient">

<div class="max-w-xl mx-auto">
    <div class="panel p-6">
        <h2 class="text-sm font-semibold text-white mb-5">Edit Patient</h2>

        <form method="POST" action="{{ route('patients.update', $patient) }}" class="space-y-4">
            @csrf
            @method('PATCH')

            <div>
                <label class="block text-xs text-gray-500 mb-1">Full Name *</label>
                <input type="text" name="name" value="{{ old('name', $patient->name) }}" required autofocus
                       class="w-full field
                              focus:ring-indigo-500 focus:border-indigo-500">
                @error('name') <p class="text-xs text-red-400 mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Phone</label>
                    <input type="text" name="phone" value="{{ old('phone', $patient->phone) }}"
                           class="w-full field
                                  focus:ring-indigo-500 focus:border-indigo-500">
                    @error('phone') <p class="text-xs text-red-400 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Email</label>
                    <input type="email" name="email" value="{{ old('email', $patient->email) }}"
                           class="w-full field
                                  focus:ring-indigo-500 focus:border-indigo-500">
                    @error('email') <p class="text-xs text-red-400 mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label class="block text-xs text-gray-500 mb-1">Notes</label>
                <textarea name="notes" rows="3"
                          class="w-full field
                                 focus:ring-indigo-500 focus:border-indigo-500">{{ old('notes', $patient->notes) }}</textarea>
                @error('notes') <p class="text-xs text-red-400 mt-1">{{ $message }}</p> @enderror
            </div>

            @if($patient->platform)
                <p class="text-xs text-gray-600 pt-1">
                    Platform: {{ ucfirst($patient->platform) }} · ID {{ $patient->platform_user_id }}
                    (auto-detected, not editable)
                </p>
            @endif

            <div class="flex items-center justify-between pt-2">
                <a href="{{ route('patients.show', $patient) }}"
                   class="text-sm text-[var(--text-muted)] hover:text-[var(--text-strong)] transition-colors">
                    ← Back
                </a>
                <button type="submit"
                        class="px-5 py-2 btn-primary">
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

</x-layouts.app>
