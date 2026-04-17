<x-layouts.app title="Add Patient">

<div class="max-w-xl mx-auto">
    <div class="rounded-2xl bg-gray-900/60 backdrop-blur border border-gray-800 p-6">
        <h2 class="text-sm font-semibold text-white mb-5">New Patient</h2>

        <form method="POST" action="{{ route('patients.store') }}" class="space-y-4">
            @csrf

            <div>
                <label class="block text-xs text-gray-500 mb-1">Full Name *</label>
                <input type="text" name="name" value="{{ old('name') }}" required autofocus
                       class="w-full bg-gray-800 border border-gray-700 text-gray-100 text-sm rounded-lg px-3 py-2
                              focus:ring-indigo-500 focus:border-indigo-500"
                       placeholder="e.g. Ahmed Khan">
                @error('name') <p class="text-xs text-red-400 mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Phone</label>
                    <input type="text" name="phone" value="{{ old('phone') }}"
                           class="w-full bg-gray-800 border border-gray-700 text-gray-100 text-sm rounded-lg px-3 py-2
                                  focus:ring-indigo-500 focus:border-indigo-500"
                           placeholder="+92 300 0000000">
                    @error('phone') <p class="text-xs text-red-400 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Email</label>
                    <input type="email" name="email" value="{{ old('email') }}"
                           class="w-full bg-gray-800 border border-gray-700 text-gray-100 text-sm rounded-lg px-3 py-2
                                  focus:ring-indigo-500 focus:border-indigo-500"
                           placeholder="patient@example.com">
                    @error('email') <p class="text-xs text-red-400 mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label class="block text-xs text-gray-500 mb-1">Notes</label>
                <textarea name="notes" rows="3"
                          class="w-full bg-gray-800 border border-gray-700 text-gray-100 text-sm rounded-lg px-3 py-2
                                 focus:ring-indigo-500 focus:border-indigo-500"
                          placeholder="Allergies, preferences, anything relevant…">{{ old('notes') }}</textarea>
                @error('notes') <p class="text-xs text-red-400 mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="flex items-center justify-between pt-2">
                <a href="{{ route('patients.index') }}"
                   class="text-sm text-gray-500 hover:text-white transition-colors">
                    ← Back
                </a>
                <button type="submit"
                        class="px-5 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-medium rounded-lg transition-colors">
                    Add Patient
                </button>
            </div>
        </form>
    </div>
</div>

</x-layouts.app>
