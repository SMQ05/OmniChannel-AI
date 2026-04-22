<x-layouts.app title="Add Patient">

<div class="max-w-xl mx-auto">
    <div class="panel p-6">
        <h2 class="text-sm font-semibold text-white mb-5">New Patient</h2>

        <form method="POST" action="{{ route('patients.store') }}" class="space-y-4">
            @csrf

            <div>
                <label class="block text-xs text-gray-500 mb-1">Full Name *</label>
                <input type="text" name="name" value="{{ old('name') }}" required autofocus
                       class="w-full field
                              focus:ring-indigo-500 focus:border-indigo-500"
                       placeholder="e.g. Ahmed Khan">
                @error('name') <p class="text-xs text-red-400 mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Phone</label>
                    <input type="text" name="phone" value="{{ old('phone') }}"
                           class="w-full field
                                  focus:ring-indigo-500 focus:border-indigo-500"
                           placeholder="+92 300 0000000">
                    @error('phone') <p class="text-xs text-red-400 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Email</label>
                    <input type="email" name="email" value="{{ old('email') }}"
                           class="w-full field
                                  focus:ring-indigo-500 focus:border-indigo-500"
                           placeholder="patient@example.com">
                    @error('email') <p class="text-xs text-red-400 mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label class="block text-xs text-gray-500 mb-1">Notes</label>
                <textarea name="notes" rows="3"
                          class="w-full field
                                 focus:ring-indigo-500 focus:border-indigo-500"
                          placeholder="Allergies, preferences, anything relevant…">{{ old('notes') }}</textarea>
                @error('notes') <p class="text-xs text-red-400 mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="flex items-center justify-between pt-2">
                <a href="{{ route('patients.index') }}"
                   class="text-sm text-[var(--text-muted)] hover:text-[var(--text-strong)] transition-colors">
                    ← Back
                </a>
                <button type="submit"
                        class="px-5 py-2 btn-primary">
                    Add Patient
                </button>
            </div>
        </form>
    </div>
</div>

</x-layouts.app>
