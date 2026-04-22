@props(['title' => 'Admin Overview'])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Admin — {{ $title }} — {{ config('app.name') }}</title>
    <script>
        (() => {
            const theme = localStorage.getItem('kynex-theme') || 'light';
            document.documentElement.classList.toggle('dark', theme === 'dark');
            document.documentElement.dataset.theme = theme === 'dark' ? 'dark' : 'light';
        })();
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="kynex-no-motion h-full antialiased" x-data="appShell" @keydown.escape.window="closeMobileMenu()">
<a href="#main-content" class="skip-link">Skip to main content</a>

@php
    $navItems = [
        ['route' => 'admin.dashboard',        'label' => 'Overview'],
        ['route' => 'admin.businesses.index', 'label' => 'Businesses'],
        ['route' => 'admin.plans.index',      'label' => 'Plans'],
        ['route' => 'admin.voice.index',      'label' => 'Voice'],
        ['route' => 'admin.llm-keys.index',   'label' => 'LLM Keys'],
    ];

    if (config('queue.default') === 'redis') {
        $navItems[] = ['route' => 'admin.horizon', 'label' => 'Horizon'];
    }
@endphp

<div class="app-shell">

    {{-- =====================================================================
         ADMIN SIDEBAR
         ===================================================================== --}}
    <aside class="app-sidebar hidden w-72 flex-shrink-0 flex-col lg:flex">

        {{-- Logo + super-admin badge --}}
        <div class="flex h-20 items-center gap-3 border-b border-[var(--border-subtle)] px-6">
            <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-red-500/12 text-sm font-bold text-red-500 shadow-[var(--shadow-soft)]">K</div>
            <div class="min-w-0 flex-1">
                <div class="font-display text-sm font-semibold uppercase tracking-[0.22em] text-[var(--text-soft)]">Kynex Platform</div>
                <div class="mt-1 text-lg font-semibold text-[var(--text-strong)]">{{ config('app.name') }}</div>
            </div>
            <span class="rounded-full border border-red-500/25 bg-red-500/12 px-2 py-0.5 text-xs font-semibold text-red-500">
                SUPER ADMIN
            </span>
        </div>

        <nav class="flex-1 space-y-6 px-4 py-6">
            <div>
                <p class="mb-2 px-3 text-xs font-semibold uppercase tracking-[0.22em] text-[var(--text-soft)]">Platform</p>
                <div class="space-y-1">
                    @foreach($navItems as $item)
                        @php
                            $isActive = request()->routeIs($item['route']);
                        @endphp
                        <a href="{{ route($item['route']) }}"
                           class="app-nav-link {{ $isActive ? 'app-nav-link-active !bg-red-500/10 !text-red-500' : '' }} flex items-center rounded-2xl px-3 py-2.5 text-sm font-medium transition-colors"
                           @if($isActive) aria-current="page" @endif>
                            {{ $item['label'] }}
                        </a>
                    @endforeach
                </div>
            </div>
        </nav>

        {{-- User / logout --}}
        <div class="space-y-4 border-t border-[var(--border-subtle)] p-4">
            @if(session('info'))
                <div class="rounded-xl border border-amber-500/25 bg-amber-500/12 px-3 py-2 text-xs text-amber-600 dark:text-amber-300">
                    {{ session('info') }}
                </div>
            @endif

            <x-theme-toggle />
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-red-500/12 text-sm font-bold text-red-500">
                    {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                </div>
                <div class="flex-1 min-w-0">
                    <p class="truncate text-sm font-medium text-[var(--text-strong)]">{{ auth()->user()->name }}</p>
                    <p class="text-xs uppercase tracking-[0.18em] text-red-500">super_admin</p>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="btn-secondary px-3 py-2 text-xs" title="Sign out">
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
    <div class="flex min-h-0 flex-1 flex-col overflow-hidden">
        <div id="mobile-admin-nav"
             x-cloak
             x-show="mobileMenuOpen"
             class="fixed inset-0 z-50 flex lg:hidden"
             aria-hidden="true">
            <button type="button"
                    class="mobile-nav-overlay absolute inset-0"
                    @click="closeMobileMenu()"
                    tabindex="-1">
                <span class="sr-only">Close navigation overlay</span>
            </button>

            <aside x-ref="mobileDrawer"
                   role="dialog"
                   aria-modal="true"
                   aria-label="Admin navigation menu"
                   @keydown="handleKeydown($event)"
                   class="mobile-nav-drawer relative ml-auto flex h-full w-full max-w-[22rem] flex-col border-l border-[var(--border-subtle)]">
                <div class="flex items-start justify-between gap-4 border-b border-[var(--border-subtle)] px-5 py-5">
                    <div class="min-w-0">
                        <p class="text-xs font-semibold uppercase tracking-[0.22em] text-[var(--text-soft)]">Platform navigation</p>
                        <h2 class="mt-2 font-display text-lg font-semibold text-[var(--text-strong)]">{{ config('app.name') }}</h2>
                        <p class="mt-2 text-sm text-[var(--text-muted)]">The shared platform brain stays visible here so rollout, policy, and tenant operations remain understandable.</p>
                    </div>
                    <button x-ref="mobileCloseButton"
                            type="button"
                            class="mobile-nav-trigger shrink-0 rounded-2xl"
                            @click="closeMobileMenu()"
                            aria-label="Close navigation menu">
                        <svg class="mx-auto h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 18 18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <nav class="flex-1 space-y-6 overflow-y-auto px-4 py-5" aria-label="Mobile admin navigation">
                    <div>
                        <p class="mb-2 px-3 text-xs font-semibold uppercase tracking-[0.22em] text-[var(--text-soft)]">Platform</p>
                        <div class="space-y-1">
                            @foreach($navItems as $item)
                                @php
                                    $isActive = request()->routeIs($item['route']);
                                @endphp
                                <a href="{{ route($item['route']) }}"
                                   class="app-nav-link {{ $isActive ? 'app-nav-link-active !bg-red-500/10 !text-red-500' : '' }} flex items-center rounded-2xl px-3 py-3 text-sm font-medium transition-colors"
                                   @click="closeMobileMenu()"
                                   @if($isActive) aria-current="page" @endif>
                                    {{ $item['label'] }}
                                </a>
                            @endforeach
                        </div>
                    </div>
                </nav>

                <div class="space-y-4 border-t border-[var(--border-subtle)] p-4">
                    @if(session('info'))
                        <div class="rounded-xl border border-amber-500/25 bg-amber-500/12 px-3 py-2 text-xs text-amber-600 dark:text-amber-300">
                            {{ session('info') }}
                        </div>
                    @endif

                    <x-theme-toggle />
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-red-500/12 text-sm font-bold text-red-500">
                            {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="truncate text-sm font-medium text-[var(--text-strong)]">{{ auth()->user()->name }}</p>
                            <p class="text-xs uppercase tracking-[0.18em] text-red-500">super_admin</p>
                        </div>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="btn-secondary min-h-11 px-3 py-2 text-xs" title="Sign out">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                                </svg>
                            </button>
                        </form>
                    </div>
                </div>
            </aside>
        </div>

        <header class="app-topbar flex flex-shrink-0 items-center justify-between gap-4 px-4 py-4 sm:px-6 lg:h-20 lg:px-6">
            <div class="flex min-w-0 items-start gap-3">
                <button type="button"
                        class="mobile-nav-trigger rounded-2xl lg:hidden"
                        @click="openMobileMenu()"
                        :aria-expanded="mobileMenuOpen.toString()"
                        aria-controls="mobile-admin-nav"
                        aria-label="Open navigation menu">
                    <svg class="mx-auto h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 7h16M4 12h16M4 17h16"/>
                    </svg>
                </button>

                <div class="min-w-0">
                    <div class="page-eyebrow text-red-500">Admin Side</div>
                    <h1 class="mt-1 text-2xl font-semibold text-[var(--text-strong)]">{{ $title }}</h1>
                    <p class="shell-context mt-1 hidden sm:block">The shared platform brain is the control plane here, so business-facing surfaces stay easier to reason about during rollout.</p>
                </div>
            </div>
            <div class="lg:hidden">
                <x-theme-toggle />
            </div>
        </header>

        {{-- Flash messages --}}
        @if(session('success'))
            <div class="flash-success mx-6 mt-4 px-4 py-3 text-sm">
                {{ session('success') }}
            </div>
        @endif

        @if($errors->any())
            <div class="flash-error mx-6 mt-4 px-4 py-3 text-sm">
                <ul class="list-disc list-inside space-y-1">
                    @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        @endif

        <main id="main-content" class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8">
            {{ $slot }}
        </main>
    </div>
</div>

</body>
</html>
