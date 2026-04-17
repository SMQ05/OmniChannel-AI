<x-admin.layouts.admin title="LLM API Keys">

<div class="max-w-3xl mx-auto space-y-6">

    {{-- Info banner --}}
    <div class="px-4 py-3 bg-indigo-500/10 border border-indigo-500/30 rounded-xl text-sm text-indigo-300">
        Platform-level keys are used as fallback when a tenant has not configured their own API key.
        Keys are stored encrypted at rest. Only the last 4 characters are shown below.
    </div>

    {{-- =====================================================================
         ADD KEY FORM
         ===================================================================== --}}
    <div class="rounded-2xl bg-gray-900/60 backdrop-blur border border-gray-800 p-6">
        <h2 class="text-sm font-semibold text-white mb-4">Add / Replace Key</h2>

        <form method="POST" action="{{ route('admin.llm-keys.store') }}" class="space-y-4">
            @csrf

            <div class="grid grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Provider *</label>
                    <select name="provider" required
                            class="w-full bg-gray-800 border border-gray-700 text-gray-300 text-sm rounded-lg px-3 py-2
                                   focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="">Select…</option>
                        @foreach(['claude' => 'Claude (Anthropic)', 'gpt4o' => 'GPT-4o (OpenAI)', 'minimax' => 'MiniMax'] as $val => $label)
                            <option value="{{ $val }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Label *</label>
                    <input type="text" name="label" required
                           placeholder="e.g. Production Anthropic Key"
                           class="w-full bg-gray-800 border border-gray-700 text-gray-100 text-sm rounded-lg px-3 py-2
                                  focus:ring-indigo-500 focus:border-indigo-500">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">API Key *</label>
                    <input type="password" name="key_value" required
                           autocomplete="off"
                           placeholder="sk-ant-… / sk-… / …"
                           class="w-full bg-gray-800 border border-gray-700 text-gray-100 text-sm rounded-lg px-3 py-2
                                  focus:ring-indigo-500 focus:border-indigo-500">
                </div>
            </div>

            <p class="text-xs text-gray-600">
                Saving a new key for a provider will automatically deactivate the previous active key for that provider.
            </p>

            <div class="flex justify-end">
                <button type="submit"
                        class="px-5 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-medium rounded-lg transition-colors">
                    Save Key
                </button>
            </div>
        </form>
    </div>

    {{-- =====================================================================
         EXISTING KEYS TABLE
         ===================================================================== --}}
    <div class="rounded-2xl bg-gray-900/60 backdrop-blur border border-gray-800 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-800">
            <h2 class="text-sm font-semibold text-white">Stored Keys</h2>
        </div>

        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-800">
                    <th class="px-5 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Provider</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Label</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Key (masked)</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Status</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Added</th>
                    <th class="px-5 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-800">
                @forelse($keys as $key)
                    <tr class="hover:bg-gray-800/30 transition-colors {{ !$key->is_active ? 'opacity-50' : '' }}">
                        <td class="px-5 py-3.5">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                {{ match($key->provider) {
                                    'claude'  => 'bg-orange-500/20 text-orange-400',
                                    'gpt4o'   => 'bg-emerald-500/20 text-emerald-400',
                                    'minimax' => 'bg-blue-500/20 text-blue-400',
                                    default   => 'bg-gray-700 text-gray-400',
                                } }}">
                                {{ $key->provider }}
                            </span>
                        </td>
                        <td class="px-5 py-3.5 text-gray-300">{{ $key->label }}</td>
                        <td class="px-5 py-3.5">
                            <code class="text-xs text-gray-500 font-mono">
                                ••••••••{{ substr($key->key_value, -4) }}
                            </code>
                        </td>
                        <td class="px-5 py-3.5">
                            @if($key->is_active)
                                <span class="flex items-center gap-1.5 text-xs text-emerald-400">
                                    <span class="w-1.5 h-1.5 bg-emerald-400 rounded-full"></span>
                                    Active
                                </span>
                            @else
                                <span class="text-xs text-gray-600">Inactive</span>
                            @endif
                        </td>
                        <td class="px-5 py-3.5 text-xs text-gray-600">
                            {{ $key->created_at->format('M j, Y') }}
                        </td>
                        <td class="px-5 py-3.5">
                            <div class="flex items-center justify-end gap-3">
                                @unless($key->is_active)
                                    <form method="POST" action="{{ route('admin.llm-keys.activate', $key) }}">
                                        @csrf @method('PATCH')
                                        <button type="submit"
                                                class="text-xs text-indigo-400 hover:text-indigo-300 transition-colors">
                                            Set Active
                                        </button>
                                    </form>
                                @endunless

                                <form method="POST" action="{{ route('admin.llm-keys.destroy', $key) }}"
                                      onsubmit="return confirm('Delete this key? This cannot be undone.')">
                                    @csrf @method('DELETE')
                                    <button type="submit"
                                            class="text-xs text-red-500 hover:text-red-400 transition-colors">
                                        Delete
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-5 py-12 text-center text-gray-600">
                            No platform keys configured yet. Add a key above.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

</div>

</x-admin.layouts.admin>
