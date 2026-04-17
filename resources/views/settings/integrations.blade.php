<x-layouts.app title="Integrations">

<div class="max-w-2xl mx-auto space-y-6">

    {{-- =====================================================================
         GOOGLE OAUTH CREDENTIALS (shared by Calendar + Sheets)
         ===================================================================== --}}
    <div class="rounded-2xl bg-gray-900/60 backdrop-blur border border-gray-800 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-800 flex items-center gap-3">
            <div class="w-8 h-8 rounded-lg bg-gray-700 flex items-center justify-center">
                <svg class="w-4 h-4 text-gray-300" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                </svg>
            </div>
            <div>
                <h2 class="text-sm font-semibold text-white">Google OAuth Credentials</h2>
                <p class="text-xs text-gray-600 mt-0.5">Shared by both Google Calendar and Google Sheets</p>
            </div>
        </div>

        <form method="POST" action="{{ route('settings.integrations.update') }}" class="p-5 space-y-4">
            @csrf

            <div class="rounded-lg bg-indigo-500/10 border border-indigo-500/20 px-4 py-3 text-xs text-indigo-300 space-y-1">
                <p class="font-medium">How to get your credentials:</p>
                <ol class="list-decimal list-inside space-y-0.5 text-indigo-400">
                    <li>Go to <span class="font-mono">console.cloud.google.com</span> → APIs & Services → Credentials</li>
                    <li>Create an OAuth 2.0 Client ID (Web application)</li>
                    <li>Add <span class="font-mono">{{ route('settings.integrations.oauth.callback') }}</span> as an Authorized Redirect URI</li>
                    <li>Enable the Google Calendar API and Google Sheets API in your project</li>
                    <li>Paste the Client ID and Secret below, then click Save</li>
                </ol>
            </div>

            <div class="grid grid-cols-1 gap-3">
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Client ID</label>
                    <input type="text" name="google_credentials[client_id]"
                           value="{{ old('google_credentials.client_id', $integrationConfig['google_credentials']['client_id'] ?? '') }}"
                           placeholder="123456789-abc…apps.googleusercontent.com"
                           class="w-full bg-gray-800 border border-gray-700 text-gray-100 text-sm rounded-lg px-3 py-2
                                  focus:ring-indigo-500 focus:border-indigo-500 font-mono">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Client Secret</label>
                    <input type="password" name="google_credentials[client_secret]"
                           value="{{ old('google_credentials.client_secret', $integrationConfig['google_credentials']['client_secret'] ?? '') }}"
                           placeholder="GOCSPX-…"
                           autocomplete="off"
                           class="w-full bg-gray-800 border border-gray-700 text-gray-100 text-sm rounded-lg px-3 py-2
                                  focus:ring-indigo-500 focus:border-indigo-500">
                </div>
            </div>

            {{-- Preserve other fields so saving credentials doesn't wipe them --}}
            <input type="hidden" name="google_calendar[enabled]"
                   value="{{ $integrationConfig['google_calendar']['enabled'] ?? 0 ? 1 : 0 }}">
            <input type="hidden" name="google_sheets[enabled]"
                   value="{{ $integrationConfig['google_sheets']['enabled'] ?? 0 ? 1 : 0 }}">

            <div class="flex justify-end">
                <button type="submit"
                        class="px-5 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-medium rounded-lg transition-colors">
                    Save Credentials
                </button>
            </div>
        </form>
    </div>

    {{-- =====================================================================
         GOOGLE CALENDAR
         ===================================================================== --}}
    <div class="rounded-2xl bg-gray-900/60 backdrop-blur border border-gray-800 overflow-hidden"
         x-data="{ enabled: {{ $integrationConfig['google_calendar']['enabled'] ?? false ? 'true' : 'false' }} }">

        <div class="px-5 py-4 border-b border-gray-800 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-lg bg-red-500/20 flex items-center justify-center">
                    <svg class="w-4 h-4 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                </div>
                <div>
                    <h2 class="text-sm font-semibold text-white">Google Calendar</h2>
                    @if(!empty($integrationConfig['google_calendar']['token']['access_token']))
                        <p class="text-xs text-emerald-400 mt-0.5">✓ Connected</p>
                    @else
                        <p class="text-xs text-gray-600 mt-0.5">Not connected</p>
                    @endif
                </div>
            </div>

            <label class="flex items-center cursor-pointer">
                <div class="relative">
                    <input type="checkbox" x-model="enabled" class="sr-only">
                    <div :class="enabled ? 'bg-indigo-600' : 'bg-gray-700'" class="w-10 h-5 rounded-full transition-colors"></div>
                    <div :class="enabled ? 'translate-x-5' : 'translate-x-0'"
                         class="absolute top-0.5 left-0.5 w-4 h-4 bg-white rounded-full transition-transform"></div>
                </div>
            </label>
        </div>

        <div x-show="enabled" class="p-5 space-y-4">
            <form method="POST" action="{{ route('settings.integrations.update') }}" id="calendar-form">
                @csrf
                <input type="hidden" name="google_calendar[enabled]" :value="enabled ? 1 : 0" x-bind:value="enabled ? 1 : 0">
                <div>
                    <label class="block text-xs text-gray-500 mb-1">
                        Calendar ID
                        @if(!empty($integrationConfig['google_calendar']['token']['access_token']) && empty($integrationConfig['google_calendar']['calendar_id']))
                            <span class="text-yellow-400 ml-1">← required to activate sync</span>
                        @endif
                    </label>
                    <input type="text" name="google_calendar[calendar_id]"
                           value="{{ old('google_calendar.calendar_id', $integrationConfig['google_calendar']['calendar_id'] ?? '') }}"
                           placeholder="primary  or  your-calendar@group.calendar.google.com"
                           class="w-full bg-gray-800 border {{ (empty($integrationConfig['google_calendar']['calendar_id']) && !empty($integrationConfig['google_calendar']['token']['access_token'])) ? 'border-yellow-500/60' : 'border-gray-700' }} text-gray-100 text-sm rounded-lg px-3 py-2
                                  focus:ring-indigo-500 focus:border-indigo-500">
                    <p class="text-xs text-gray-600 mt-1">
                        Find it in Google Calendar → Settings → your calendar → Calendar ID.
                        Use <span class="font-mono">primary</span> for your main calendar.
                    </p>
                </div>
                <button type="submit" form="calendar-form"
                        class="mt-3 px-4 py-2 bg-gray-700 hover:bg-gray-600 text-gray-300 text-sm rounded-lg transition-colors">
                    Save
                </button>
            </form>

            {{-- OAuth connect --}}
            @php $hasCredentials = !empty($integrationConfig['google_credentials']['client_id']); @endphp
            <div class="pt-3 border-t border-gray-800 flex items-center gap-3">
                @if($hasCredentials)
                    <a href="{{ route('settings.integrations.oauth.redirect', 'google_calendar') }}"
                       class="flex items-center gap-2 px-4 py-2 bg-white/10 hover:bg-white/20 text-white text-sm rounded-lg transition-colors border border-gray-700">
                        <svg class="w-4 h-4" viewBox="0 0 24 24">
                            <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                            <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                            <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                            <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                        </svg>
                        {{ !empty($integrationConfig['google_calendar']['token']['access_token']) ? 'Reconnect Google Account' : 'Connect Google Account' }}
                    </a>
                @else
                    <p class="text-xs text-yellow-500">Save your Google Client ID above before connecting.</p>
                @endif
            </div>

            {{-- Test --}}
            <div x-data="{ loading: false, result: null }" class="flex items-center gap-3">
                <button type="button"
                        @click="
                            loading = true; result = null;
                            fetch('{{ route('settings.integrations.test', 'google_calendar') }}', {
                                method: 'POST',
                                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }
                            }).then(r => r.json()).then(d => { result = d; loading = false; })
                        "
                        :disabled="loading"
                        class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-gray-300 text-sm rounded-lg transition-colors disabled:opacity-50">
                    <span x-text="loading ? 'Testing…' : 'Test Sync'"></span>
                </button>
                <template x-if="result">
                    <span class="text-sm" :class="result.success ? 'text-emerald-400' : 'text-red-400'"
                          x-text="result.message"></span>
                </template>
            </div>
        </div>
    </div>

    {{-- =====================================================================
         GOOGLE SHEETS
         ===================================================================== --}}
    <div class="rounded-2xl bg-gray-900/60 backdrop-blur border border-gray-800 overflow-hidden"
         x-data="{ enabled: {{ $integrationConfig['google_sheets']['enabled'] ?? false ? 'true' : 'false' }} }">

        <div class="px-5 py-4 border-b border-gray-800 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-lg bg-emerald-500/20 flex items-center justify-center">
                    <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2"/>
                    </svg>
                </div>
                <div>
                    <h2 class="text-sm font-semibold text-white">Google Sheets</h2>
                    @if(!empty($integrationConfig['google_sheets']['token']['access_token']))
                        <p class="text-xs text-emerald-400 mt-0.5">✓ Connected</p>
                    @else
                        <p class="text-xs text-gray-600 mt-0.5">Not connected</p>
                    @endif
                </div>
            </div>

            <label class="flex items-center cursor-pointer">
                <div class="relative">
                    <input type="checkbox" x-model="enabled" class="sr-only">
                    <div :class="enabled ? 'bg-indigo-600' : 'bg-gray-700'" class="w-10 h-5 rounded-full transition-colors"></div>
                    <div :class="enabled ? 'translate-x-5' : 'translate-x-0'"
                         class="absolute top-0.5 left-0.5 w-4 h-4 bg-white rounded-full transition-transform"></div>
                </div>
            </label>
        </div>

        <div x-show="enabled" class="p-5 space-y-4">
            <form method="POST" action="{{ route('settings.integrations.update') }}" id="sheets-form">
                @csrf
                <input type="hidden" name="google_sheets[enabled]" :value="enabled ? 1 : 0" x-bind:value="enabled ? 1 : 0">
                <div class="grid grid-cols-2 gap-3">
                    <div class="col-span-2">
                        <label class="block text-xs text-gray-500 mb-1">
                            Spreadsheet ID
                            @if(!empty($integrationConfig['google_sheets']['token']['access_token']) && empty($integrationConfig['google_sheets']['spreadsheet_id']))
                                <span class="text-yellow-400 ml-1">← required to activate sync</span>
                            @endif
                        </label>
                        <input type="text" name="google_sheets[spreadsheet_id]"
                               value="{{ old('google_sheets.spreadsheet_id', $integrationConfig['google_sheets']['spreadsheet_id'] ?? '') }}"
                               placeholder="From spreadsheet URL: /d/{SPREADSHEET_ID}/edit"
                               class="w-full bg-gray-800 border {{ (empty($integrationConfig['google_sheets']['spreadsheet_id']) && !empty($integrationConfig['google_sheets']['token']['access_token'])) ? 'border-yellow-500/60' : 'border-gray-700' }} text-gray-100 text-sm rounded-lg px-3 py-2
                                      focus:ring-indigo-500 focus:border-indigo-500">
                        <p class="text-xs text-gray-600 mt-1">
                            Open your Google Sheet — the ID is in the URL between <span class="font-mono">/d/</span> and <span class="font-mono">/edit</span>.
                        </p>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Sheet Tab Name</label>
                        <input type="text" name="google_sheets[sheet_name]"
                               value="{{ old('google_sheets.sheet_name', $integrationConfig['google_sheets']['sheet_name'] ?? 'Appointments') }}"
                               class="w-full bg-gray-800 border border-gray-700 text-gray-100 text-sm rounded-lg px-3 py-2
                                      focus:ring-indigo-500 focus:border-indigo-500">
                        <p class="text-xs text-gray-600 mt-1">The tab name at the bottom of the sheet (e.g. Sheet1).</p>
                    </div>
                </div>
                <button type="submit" form="sheets-form"
                        class="mt-3 px-4 py-2 bg-gray-700 hover:bg-gray-600 text-gray-300 text-sm rounded-lg transition-colors">
                    Save
                </button>
            </form>

            <div class="pt-3 border-t border-gray-800 flex items-center gap-3">
                @if(!empty($integrationConfig['google_credentials']['client_id']))
                    <a href="{{ route('settings.integrations.oauth.redirect', 'google_sheets') }}"
                       class="flex items-center gap-2 px-4 py-2 bg-white/10 hover:bg-white/20 text-white text-sm rounded-lg transition-colors border border-gray-700">
                        <svg class="w-4 h-4" viewBox="0 0 24 24">
                            <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                            <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                            <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                            <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                        </svg>
                        {{ !empty($integrationConfig['google_sheets']['token']['access_token']) ? 'Reconnect Google Account' : 'Connect Google Account' }}
                    </a>
                @else
                    <p class="text-xs text-yellow-500">Save your Google Client ID above before connecting.</p>
                @endif
            </div>

            <div x-data="{ loading: false, result: null }" class="flex items-center gap-3">
                <button type="button"
                        @click="
                            loading = true; result = null;
                            fetch('{{ route('settings.integrations.test', 'google_sheets') }}', {
                                method: 'POST',
                                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }
                            }).then(r => r.json()).then(d => { result = d; loading = false; })
                        "
                        :disabled="loading"
                        class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-gray-300 text-sm rounded-lg transition-colors disabled:opacity-50">
                    <span x-text="loading ? 'Testing…' : 'Test Sync'"></span>
                </button>
                <template x-if="result">
                    <p class="text-sm" :class="result.success ? 'text-emerald-400' : 'text-red-400'"
                       x-text="result.message"></p>
                </template>
                @if(!empty($integrationConfig['google_sheets']['enabled']))
                    <p class="text-xs text-gray-600">Note: test appends a row labelled "TEST - PLEASE DELETE".</p>
                @endif
            </div>
        </div>
    </div>

</div>

</x-layouts.app>
