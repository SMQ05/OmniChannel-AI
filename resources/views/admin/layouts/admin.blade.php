<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Admin — {{ $title ?? 'Super Admin' }} — {{ config('app.name') }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-gray-950 text-gray-100 antialiased" x-data>

<div class="flex h-full">

    {{-- =====================================================================
         ADMIN SIDEBAR
         ===================================================================== --}}
    <aside class="w-64 flex-shrink-0 flex flex-col bg-gray-900/80 backdrop-blur border-r border-red-900/40">

        {{-- Logo + super-admin badge --}}
        <div class="h-16 flex items-center px-6 border-b border-red-900/40 gap-3">
            <span class="text-sm font-bold text-white">{{ config('app.name') }}</span>
            <span class="px-2 py-0.5 bg-red-600/30 text-red-400 border border-red-500/40 rounded-full text-xs font-semibold">
                SUPER ADMIN
            </span>
        </div>

        <nav class="flex-1 py-6 px-3 space-y-1">
            @php
                $navItems = [
                    ['route' => 'admin.dashboard',        'label' => 'Overview'],
                    ['route' => 'admin.businesses.index', 'label' => 'All Businesses'],
                    ['route' => 'admin.plans.index',      'label' => 'Plans'],
                    ['route' => 'admin.llm-keys.index',   'label' => 'LLM API Keys'],
                ];

                if (config('queue.default') === 'redis') {
                    $navItems[] = ['route' => 'admin.horizon', 'label' => 'Horizon (Queues)'];
                }
            @endphp

            @foreach($navItems as $item)
                <a href="{{ route($item['route']) }}"
                   class="flex items-center px-3 py-2.5 rounded-lg text-sm font-medium transition-colors
                          {{ request()->routeIs($item['route'])
                             ? 'bg-red-600/20 text-red-400 ring-1 ring-red-500/30'
                             : 'text-gray-400 hover:text-white hover:bg-gray-800' }}">
                    {{ $item['label'] }}
                </a>
            @endforeach
        </nav>

        {{-- Back to tenant dashboard --}}
        <div class="p-4 border-t border-red-900/40 space-y-2">
            @if(session('info'))
                {{-- Impersonation active --}}
                <div class="px-3 py-2 bg-amber-500/20 text-amber-400 border border-amber-500/30 rounded-lg text-xs">
                    {{ session('info') }}
                </div>
            @endif

            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-full bg-red-600/30 flex items-center justify-center text-sm font-bold text-red-300">
                    {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-white truncate">{{ auth()->user()->name }}</p>
                    <p class="text-xs text-red-400">super_admin</p>
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

        <header class="h-16 flex items-center justify-between px-6 bg-gray-900/60 backdrop-blur border-b border-red-900/30 flex-shrink-0">
            <h1 class="text-lg font-semibold text-white">{{ $title ?? 'Admin Overview' }}</h1>
        </header>

        {{-- Flash messages --}}
        @if(session('success'))
            <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)"
                 class="mx-6 mt-4 px-4 py-3 bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 rounded-lg text-sm">
                {{ session('success') }}
            </div>
        @endif

        @if($errors->any())
            <div class="mx-6 mt-4 px-4 py-3 bg-red-500/20 text-red-400 border border-red-500/30 rounded-lg text-sm">
                <ul class="list-disc list-inside space-y-1">
                    @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        @endif

        <main class="flex-1 overflow-y-auto p-6">
            {{ $slot }}
        </main>
    </div>
</div>

</body>
</html>
