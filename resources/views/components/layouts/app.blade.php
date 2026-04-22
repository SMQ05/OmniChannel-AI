@props(['title' => 'Dashboard'])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }} — {{ auth()->user()->business->name ?? config('app.name') }}</title>
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

{{-- =====================================================================
     IMPERSONATION BANNER — visible when super_admin is impersonating
     ===================================================================== --}}
@if(session()->has('impersonating_as'))
    <div class="fixed top-0 inset-x-0 z-50 flex items-center justify-between px-6 py-2
                bg-amber-500 text-amber-950 text-sm font-medium">
        <span>
            You are impersonating <strong>{{ auth()->user()->name }}</strong>
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
@php
    $navGroups = [
        'Workspace' => [
            ['route' => 'dashboard',           'label' => 'Home',           'permission' => 'business.dashboard.view', 'icon' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6'],
            ['route' => 'conversations.index', 'label' => 'Conversations',  'permission' => 'business.conversations.view', 'icon' => 'M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z'],
            ['route' => 'appointments.index',  'label' => 'Appointments',   'permission' => 'business.appointments.view', 'icon' => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z'],
            ['route' => 'providers.index',     'label' => 'Providers',      'permission' => 'business.providers.view', 'icon' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z'],
            ['route' => 'services.index',      'label' => 'Services',       'permission' => 'business.services.view', 'icon' => 'M19.428 15.428a4 4 0 00-5.656 0L12 17.2l-1.772-1.772a4 4 0 10-5.656 5.656L12 28.514l7.428-7.43a4 4 0 000-5.656z'],
            ['route' => 'patients.index',      'label' => 'Patients',       'permission' => 'business.patients.view', 'icon' => 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z'],
        ],
        'Configure' => [
            ['route' => 'settings.ai',           'label' => 'AI Training', 'permission' => 'business.ai_content.view'],
            ['route' => 'settings.channels',     'label' => 'Channels', 'permission' => 'business.channels.view'],
            ['route' => 'settings.integrations', 'label' => 'Integrations', 'permission' => 'business.integrations.view'],
            ['route' => 'settings.reminders',    'label' => 'Reminders', 'permission' => 'business.reminders.view'],
            ['route' => 'settings.booking-rules', 'label' => 'Booking Rules', 'permission' => 'business.services.manage'],
            ['route' => 'settings.voice',        'label' => 'Voice', 'permission' => 'business.voice_preferences.view'],
            ['route' => 'settings.subscription', 'label' => 'Usage & Plan', 'permission' => 'business.usage.view'],
            ['route' => 'settings.billing',      'label' => 'Billing', 'permission' => 'business.billing.view'],
            ['route' => 'settings.diagnostics',  'label' => 'Diagnostics', 'permission' => 'business.diagnostics.view'],
            ['route' => 'settings.data-controls', 'label' => 'Data Controls', 'permission' => 'business.data_controls.view'],
            ['route' => 'settings.team',         'label' => 'Team', 'permission' => 'business.team.view'],
            ['route' => 'profile.edit',          'label' => 'Profile'],
        ],
    ];
@endphp

<div class="app-shell">
    <aside class="app-sidebar hidden w-72 flex-shrink-0 flex-col lg:flex">

        {{-- Logo / Business name --}}
        <div class="flex h-20 items-center gap-3 border-b border-[var(--border-subtle)] px-6">
            <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-[var(--brand-soft)] text-sm font-bold text-[var(--brand-strong)] shadow-[var(--shadow-soft)]">
                K
            </div>
            <div>
                <div class="font-display text-lg font-semibold text-[var(--text-strong)] tracking-tight">
                    {{ auth()->user()->business->name ?? config('app.name') }}
                </div>
                <div class="text-xs text-[var(--text-muted)]">Business workspace</div>
            </div>
        </div>

        <nav class="flex-1 space-y-6 overflow-y-auto px-4 py-6">
            @foreach($navGroups as $groupLabel => $items)
                <div>
                    <p class="mb-2 px-3 text-xs font-semibold uppercase tracking-[0.22em] text-[var(--text-soft)]">{{ $groupLabel }}</p>
                    <div class="space-y-1">
                        @foreach($items as $item)
                            @continue(isset($item['permission']) && !auth()->user()->canPermission($item['permission']))
                            @php
                                $isActive = request()->routeIs(explode('.', $item['route'])[0] . '.*') || request()->routeIs($item['route']);
                            @endphp
                            <a href="{{ route($item['route']) }}"
                               class="app-nav-link {{ $isActive ? 'app-nav-link-active' : '' }} flex items-center gap-3 rounded-2xl px-3 py-2.5 text-sm font-medium transition-colors"
                               @if($isActive) aria-current="page" @endif>
                                @if(isset($item['icon']))
                                    <svg class="h-5 w-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="{{ $item['icon'] }}"/>
                                    </svg>
                                @else
                                    <span class="app-nav-indicator h-2 w-2 rounded-full bg-current/35"></span>
                                @endif
                                {{ $item['label'] }}
                            </a>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </nav>

        {{-- User / logout --}}
        <div class="space-y-4 border-t border-[var(--border-subtle)] p-4">
            <x-theme-toggle />
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-[var(--brand-soft)] text-sm font-bold text-[var(--brand-strong)]">
                    {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                </div>
                <div class="flex-1 min-w-0">
                    <p class="truncate text-sm font-medium text-[var(--text-strong)]">{{ auth()->user()->name }}</p>
                    <p class="truncate text-xs uppercase tracking-[0.18em] text-[var(--text-soft)]">{{ auth()->user()->roleLabel() }}</p>
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
        <div id="mobile-business-nav"
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
                   aria-label="Business navigation menu"
                   @keydown="handleKeydown($event)"
                   class="mobile-nav-drawer relative ml-auto flex h-full w-full max-w-[22rem] flex-col border-l border-[var(--border-subtle)]">
                <div class="flex items-start justify-between gap-4 border-b border-[var(--border-subtle)] px-5 py-5">
                    <div class="min-w-0">
                        <p class="text-xs font-semibold uppercase tracking-[0.22em] text-[var(--text-soft)]">Business navigation</p>
                        <h2 class="mt-2 font-display text-lg font-semibold text-[var(--text-strong)]">{{ auth()->user()->business->name ?? config('app.name') }}</h2>
                        <p class="mt-2 text-sm text-[var(--text-muted)]">Local workflows connected to the shared platform brain.</p>
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

                <nav class="flex-1 space-y-6 overflow-y-auto px-4 py-5" aria-label="Mobile business navigation">
                    @foreach($navGroups as $groupLabel => $items)
                        <div>
                            <p class="mb-2 px-3 text-xs font-semibold uppercase tracking-[0.22em] text-[var(--text-soft)]">{{ $groupLabel }}</p>
                            <div class="space-y-1">
                                @foreach($items as $item)
                                    @continue(isset($item['permission']) && !auth()->user()->canPermission($item['permission']))
                                    @php
                                        $isActive = request()->routeIs(explode('.', $item['route'])[0] . '.*') || request()->routeIs($item['route']);
                                    @endphp
                                    <a href="{{ route($item['route']) }}"
                                       class="app-nav-link {{ $isActive ? 'app-nav-link-active' : '' }} flex items-center gap-3 rounded-2xl px-3 py-3 text-sm font-medium transition-colors"
                                       @click="closeMobileMenu()"
                                       @if($isActive) aria-current="page" @endif>
                                        @if(isset($item['icon']))
                                            <svg class="h-5 w-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="{{ $item['icon'] }}"/>
                                            </svg>
                                        @else
                                            <span class="app-nav-indicator h-2 w-2 rounded-full bg-current/35"></span>
                                        @endif
                                        {{ $item['label'] }}
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </nav>

                <div class="space-y-4 border-t border-[var(--border-subtle)] p-4">
                    <x-theme-toggle />
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-[var(--brand-soft)] text-sm font-bold text-[var(--brand-strong)]">
                            {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="truncate text-sm font-medium text-[var(--text-strong)]">{{ auth()->user()->name }}</p>
                            <p class="truncate text-xs uppercase tracking-[0.18em] text-[var(--text-soft)]">{{ auth()->user()->roleLabel() }}</p>
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

        {{-- Top bar --}}
        <header class="app-topbar flex flex-shrink-0 items-center justify-between gap-4 px-4 py-4 sm:px-6 lg:h-20 lg:px-6">
            <div class="flex min-w-0 items-start gap-3">
                <button type="button"
                        class="mobile-nav-trigger rounded-2xl lg:hidden"
                        @click="openMobileMenu()"
                        :aria-expanded="mobileMenuOpen.toString()"
                        aria-controls="mobile-business-nav"
                        aria-label="Open navigation menu">
                    <svg class="mx-auto h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 7h16M4 12h16M4 17h16"/>
                    </svg>
                </button>

                <div class="min-w-0">
                    <div class="page-eyebrow">Business Side</div>
                    <h1 class="mt-1 text-2xl font-semibold text-[var(--text-strong)]">{{ $title }}</h1>
                    <p class="shell-context mt-1 hidden sm:block">Business operations remain separate, but the shared platform brain still coordinates the core AI, policy, and rollout logic.</p>
                </div>
            </div>
            <div class="flex items-center gap-3 lg:hidden">
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
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Page content --}}
        <main id="main-content" class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8">
            {{ $slot }}
        </main>
    </div>
</div>

</body>
</html>
