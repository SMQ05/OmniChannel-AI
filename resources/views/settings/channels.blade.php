<x-layouts.app title="Channel Settings">

<div class="max-w-2xl mx-auto">
    <form method="POST" action="{{ route('settings.channels.update') }}" class="space-y-6">
        @csrf

        {{-- WhatsApp --}}
        <div class="rounded-2xl bg-gray-900/60 backdrop-blur border border-gray-800 overflow-hidden"
             x-data="{ enabled: {{ $channelConfig['whatsapp']['enabled'] ?? false ? 'true' : 'false' }} }">

            <div class="px-5 py-4 border-b border-gray-800 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg bg-green-500/20 flex items-center justify-center">
                        <svg class="w-4 h-4 text-green-400" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                        </svg>
                    </div>
                    <h2 class="text-sm font-semibold text-white">WhatsApp</h2>
                </div>

                <label class="flex items-center cursor-pointer">
                    <div class="relative">
                        <input type="checkbox" name="whatsapp[enabled]" value="1" x-model="enabled"
                               {{ $channelConfig['whatsapp']['enabled'] ?? false ? 'checked' : '' }}
                               class="sr-only">
                        <div :class="enabled ? 'bg-indigo-600' : 'bg-gray-700'"
                             class="w-10 h-5 rounded-full transition-colors"></div>
                        <div :class="enabled ? 'translate-x-5' : 'translate-x-0'"
                             class="absolute top-0.5 left-0.5 w-4 h-4 bg-white rounded-full transition-transform"></div>
                    </div>
                </label>
            </div>

            <div x-show="enabled" class="p-5 space-y-4">
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Phone Number ID</label>
                    <input type="text" name="whatsapp[phone_number_id]"
                           value="{{ old('whatsapp.phone_number_id', $channelConfig['whatsapp']['phone_number_id'] ?? '') }}"
                           class="w-full bg-gray-800 border border-gray-700 text-gray-100 text-sm rounded-lg px-3 py-2
                                  focus:ring-indigo-500 focus:border-indigo-500">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Access Token</label>
                    <input type="password" name="whatsapp[access_token]"
                           value="{{ old('whatsapp.access_token', $channelConfig['whatsapp']['access_token'] ?? '') }}"
                           class="w-full bg-gray-800 border border-gray-700 text-gray-100 text-sm rounded-lg px-3 py-2
                                  focus:ring-indigo-500 focus:border-indigo-500">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Verify Token</label>
                    <input type="text" name="whatsapp[verify_token]"
                           value="{{ old('whatsapp.verify_token', $channelConfig['whatsapp']['verify_token'] ?? '') }}"
                           class="w-full bg-gray-800 border border-gray-700 text-gray-100 text-sm rounded-lg px-3 py-2
                                  focus:ring-indigo-500 focus:border-indigo-500">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">App Secret</label>
                    <input type="password" name="whatsapp[app_secret]"
                           value="{{ old('whatsapp.app_secret', $channelConfig['whatsapp']['app_secret'] ?? '') }}"
                           class="w-full bg-gray-800 border border-gray-700 text-gray-100 text-sm rounded-lg px-3 py-2
                                  focus:ring-indigo-500 focus:border-indigo-500">
                </div>

                {{-- Webhook URL --}}
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Webhook URL (paste this in Meta Developer Console)</label>
                    <div class="flex gap-2">
                        <input type="text" readonly
                               value="{{ $webhookBase }}/whatsapp/{{ $business->slug }}"
                               class="flex-1 bg-gray-700/50 border border-gray-700 text-gray-400 text-xs rounded-lg px-3 py-2 font-mono">
                        <button type="button"
                                x-data
                                @click="navigator.clipboard.writeText('{{ $webhookBase }}/whatsapp/{{ $business->slug }}')"
                                class="px-3 py-2 bg-gray-700 hover:bg-gray-600 text-gray-300 text-xs rounded-lg transition-colors">
                            Copy
                        </button>
                    </div>
                </div>

                {{-- Test button --}}
                <div x-data="{ loading: false, result: null }">
                    <button type="button"
                            @click="
                                loading = true; result = null;
                                fetch('{{ route('settings.channels.test', 'whatsapp') }}', {
                                    method: 'POST',
                                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }
                                }).then(r => r.json()).then(d => { result = d; loading = false; })
                            "
                            :disabled="loading"
                            class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-gray-300 text-sm rounded-lg transition-colors disabled:opacity-50">
                        <span x-text="loading ? 'Testing…' : 'Test Connection'">Test Connection</span>
                    </button>
                    <template x-if="result">
                        <span class="ml-3 text-sm"
                              :class="result.success ? 'text-emerald-400' : 'text-red-400'"
                              x-text="result.message">
                        </span>
                    </template>
                </div>
            </div>
        </div>

        {{-- Messenger --}}
        <div class="rounded-2xl bg-gray-900/60 backdrop-blur border border-gray-800 overflow-hidden"
             x-data="{ enabled: {{ $channelConfig['messenger']['enabled'] ?? false ? 'true' : 'false' }} }">

            <div class="px-5 py-4 border-b border-gray-800 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg bg-blue-500/20 flex items-center justify-center">
                        <svg class="w-4 h-4 text-blue-400" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 0C5.374 0 0 4.975 0 11.111c0 3.497 1.745 6.616 4.472 8.652V24l4.086-2.242c1.09.301 2.246.464 3.442.464 6.626 0 12-4.975 12-11.111S18.626 0 12 0zm1.193 14.963l-3.056-3.259-5.963 3.259 6.559-6.963 3.13 3.259 5.889-3.259-6.559 6.963z"/>
                        </svg>
                    </div>
                    <h2 class="text-sm font-semibold text-white">Messenger</h2>
                </div>

                <label class="flex items-center cursor-pointer">
                    <div class="relative">
                        <input type="checkbox" name="messenger[enabled]" value="1" x-model="enabled"
                               {{ $channelConfig['messenger']['enabled'] ?? false ? 'checked' : '' }}
                               class="sr-only">
                        <div :class="enabled ? 'bg-indigo-600' : 'bg-gray-700'"
                             class="w-10 h-5 rounded-full transition-colors"></div>
                        <div :class="enabled ? 'translate-x-5' : 'translate-x-0'"
                             class="absolute top-0.5 left-0.5 w-4 h-4 bg-white rounded-full transition-transform"></div>
                    </div>
                </label>
            </div>

            <div x-show="enabled" class="p-5 space-y-4">
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Page ID</label>
                    <input type="text" name="messenger[page_id]"
                           value="{{ old('messenger.page_id', $channelConfig['messenger']['page_id'] ?? '') }}"
                           class="w-full bg-gray-800 border border-gray-700 text-gray-100 text-sm rounded-lg px-3 py-2
                                  focus:ring-indigo-500 focus:border-indigo-500">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Access Token</label>
                    <input type="password" name="messenger[access_token]"
                           value="{{ old('messenger.access_token', $channelConfig['messenger']['access_token'] ?? '') }}"
                           class="w-full bg-gray-800 border border-gray-700 text-gray-100 text-sm rounded-lg px-3 py-2
                                  focus:ring-indigo-500 focus:border-indigo-500">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Verify Token</label>
                    <input type="text" name="messenger[verify_token]"
                           value="{{ old('messenger.verify_token', $channelConfig['messenger']['verify_token'] ?? '') }}"
                           class="w-full bg-gray-800 border border-gray-700 text-gray-100 text-sm rounded-lg px-3 py-2
                                  focus:ring-indigo-500 focus:border-indigo-500">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">App Secret</label>
                    <input type="password" name="messenger[app_secret]"
                           value="{{ old('messenger.app_secret', $channelConfig['messenger']['app_secret'] ?? '') }}"
                           class="w-full bg-gray-800 border border-gray-700 text-gray-100 text-sm rounded-lg px-3 py-2
                                  focus:ring-indigo-500 focus:border-indigo-500">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Webhook URL</label>
                    <div class="flex gap-2">
                        <input type="text" readonly
                               value="{{ $webhookBase }}/messenger/{{ $business->slug }}"
                               class="flex-1 bg-gray-700/50 border border-gray-700 text-gray-400 text-xs rounded-lg px-3 py-2 font-mono">
                        <button type="button"
                                x-data
                                @click="navigator.clipboard.writeText('{{ $webhookBase }}/messenger/{{ $business->slug }}')"
                                class="px-3 py-2 bg-gray-700 hover:bg-gray-600 text-gray-300 text-xs rounded-lg transition-colors">
                            Copy
                        </button>
                    </div>
                </div>

                <div x-data="{ loading: false, result: null }">
                    <button type="button"
                            @click="
                                loading = true; result = null;
                                fetch('{{ route('settings.channels.test', 'messenger') }}', {
                                    method: 'POST',
                                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }
                                }).then(r => r.json()).then(d => { result = d; loading = false; })
                            "
                            :disabled="loading"
                            class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-gray-300 text-sm rounded-lg transition-colors disabled:opacity-50">
                        <span x-text="loading ? 'Testing…' : 'Test Connection'"></span>
                    </button>
                    <template x-if="result">
                        <span class="ml-3 text-sm"
                              :class="result.success ? 'text-emerald-400' : 'text-red-400'"
                              x-text="result.message"></span>
                    </template>
                </div>
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit"
                    class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-medium rounded-lg transition-colors">
                Save Channel Settings
            </button>
        </div>
    </form>
</div>

</x-layouts.app>
