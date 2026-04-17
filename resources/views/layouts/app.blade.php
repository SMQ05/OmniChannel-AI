<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? config('app.name') }} — {{ auth()->user()->business->name ?? config('app.name') }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-gray-950 text-gray-100 antialiased" x-data>

{{-- =====================================================================
     IMPERSONATION BANNER — visible when super_admin is impersonating
     ===================================================================== --}}
@if(session()->has('impersonating_as'))
    <div class="fixed top-0 inset-x-0 z-50 flex items-center justify-between px-6 py-2
                bg-amber-500 text-amber-950 text-sm font-medium">
        <span>
            ⚠ You are impersonating <strong>{{ auth()->user()->name }}</strong>
            ({{ auth()->user()->business?->name }})
        </span>
        <form method="POST" action="{{ route('admin.impersonate.stop') }}">
            @csrf
            <button type="submit"
                    class="px-3 py-1 bg-amber-950/20 hover:bg-amber-950/30 rounded text-xs font-bold transition-colors">
                Stop Impersonating → Return to Admin
            </button>
        </form>
    </div>
    <div class="h-10"></div>
@endif

{{-- =====================================================================
     SIDEBAR
     ===================================================================== --}}
<div class="flex h-full">
    <aside class="w-64 flex-shrink-0 flex flex-col bg-gray-900/80 backdrop-blur border-r border-gray-800">

        {{-- Logo / Business name --}}
        <div class="h-16 flex items-center px-6 border-b border-gray-800">
            <span class="text-lg font-bold text-white tracking-tight">
                {{ auth()->user()->business->name ?? config('app.name') }}
            </span>
        </div>

        {{-- Primary nav --}}
        <nav class="flex-1 py-6 px-3 space-y-1 overflow-y-auto">
            @php
                $navItems = [
                    ['route' => 'dashboard',           'label' => 'Dashboard',      'icon' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6'],
                    ['route' => 'appointments.index',  'label' => 'Appointments',   'icon' => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z'],
                    ['route' => 'conversations.index', 'label' => 'Conversations',  'icon' => 'M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z'],
                    ['route' => 'providers.index',     'label' => 'Providers',      'icon' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z'],
                    ['route' => 'patients.index',      'label' => 'Patients',       'icon' => 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z'],
                ];
            @endphp

            @foreach($navItems as $item)
                <a href="{{ route($item['route']) }}"
                   class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors
                          {{ request()->routeIs(explode('.', $item['route'])[0] . '.*') || request()->routeIs($item['route'])
                             ? 'bg-indigo-600/20 text-indigo-400 ring-1 ring-indigo-500/30'
                             : 'text-gray-400 hover:text-white hover:bg-gray-800' }}">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="{{ $item['icon'] }}"/>
                    </svg>
                    {{ $item['label'] }}
                </a>
            @endforeach

            {{-- Settings group --}}
            <div class="pt-4 mt-4 border-t border-gray-800">
                <p class="px-3 mb-2 text-xs font-semibold text-gray-600 uppercase tracking-wider">Settings</p>
                @php
                    $settingsItems = [
                        ['route' => 'settings.ai',           'label' => 'AI Training'],
                        ['route' => 'settings.channels',     'label' => 'Channels'],
                        ['route' => 'settings.integrations', 'label' => 'Integrations'],
                        ['route' => 'settings.reminders',    'label' => 'Reminders'],
                    ];
                @endphp
                @foreach($settingsItems as $item)
                    <a href="{{ route($item['route']) }}"
                       class="flex items-center px-3 py-2 rounded-lg text-sm transition-colors
                              {{ request()->routeIs($item['route'])
                                 ? 'bg-indigo-600/20 text-indigo-400'
                                 : 'text-gray-400 hover:text-white hover:bg-gray-800' }}">
                        {{ $item['label'] }}
                    </a>
                @endforeach
            </div>
        </nav>

        {{-- User / logout --}}
        <div class="p-4 border-t border-gray-800">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-full bg-indigo-600 flex items-center justify-center text-sm font-bold">
                    {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-white truncate">{{ auth()->user()->name }}</p>
                    <p class="text-xs text-gray-500 truncate">{{ auth()->user()->role }}</p>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="text-gray-500 hover:text-white transition-colors" title="Sign out">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                        </svg>
                    </button>
                </form>
            </div>
        </div>
    </aside>

    {{-- =====================================================================
         MAIN CONTENT
         ===================================================================== --}}
    <div class="flex-1 flex flex-col min-h-0 overflow-hidden">

        {{-- Top bar --}}
        <header class="h-16 flex items-center justify-between px-6 bg-gray-900/60 backdrop-blur border-b border-gray-800 flex-shrink-0">
            <h1 class="text-lg font-semibold text-white">{{ $title ?? 'Dashboard' }}</h1>
        </header>

        {{-- Flash messages --}}
        @if(session('success'))
            <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
                 class="mx-6 mt-4 px-4 py-3 bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 rounded-lg text-sm">
                {{ session('success') }}
            </div>
        @endif

        @if($errors->any())
            <div class="mx-6 mt-4 px-4 py-3 bg-red-500/20 text-red-400 border border-red-500/30 rounded-lg text-sm">
                <ul class="list-disc list-inside space-y-1">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Page content --}}
        <main class="flex-1 overflow-y-auto p-6">
            {{ $slot }}
        </main>
    </div>
</div>

</body>
</html>
