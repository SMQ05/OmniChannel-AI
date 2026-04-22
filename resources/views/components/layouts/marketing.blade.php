<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? config('app.name') }}</title>
    <script>
        (() => {
            const theme = localStorage.getItem('kynex-theme') || 'light';
            document.documentElement.classList.toggle('dark', theme === 'dark');
            document.documentElement.dataset.theme = theme === 'dark' ? 'dark' : 'light';
        })();
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full antialiased">
    <div class="min-h-screen">
        <header class="sticky top-0 z-30 border-b border-[var(--border-subtle)] bg-[var(--topbar-bg)] backdrop-blur">
            <div class="mx-auto flex max-w-7xl items-center justify-between px-6 py-4 lg:px-8">
                <a href="{{ route('marketing.home') }}" class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-[var(--brand-soft)] text-sm font-bold text-[var(--brand-strong)] shadow-[var(--shadow-soft)]">K</div>
                    <div>
                        <div class="font-display text-sm font-semibold tracking-wide text-[var(--text-strong)]">Kynex AI Booking</div>
                        <div class="text-xs text-[var(--text-muted)]">AI Front Desk Platform</div>
                    </div>
                </a>

                <nav class="hidden items-center gap-6 text-sm text-[var(--text-muted)] md:flex">
                    <a href="{{ route('marketing.features') }}" class="hover:text-[var(--text-strong)]">Features</a>
                    <a href="{{ route('marketing.pricing') }}" class="hover:text-[var(--text-strong)]">Pricing</a>
                    <a href="{{ route('privacy.policy') }}" class="hover:text-[var(--text-strong)]">Privacy</a>
                </nav>

                <div class="flex items-center gap-3">
                    <x-theme-toggle />
                    @auth
                        <a href="{{ route('dashboard') }}" class="btn-secondary rounded-full px-4 py-2 text-sm font-medium">Open App</a>
                    @else
                        <a href="{{ route('login') }}" class="btn-secondary rounded-full px-4 py-2 text-sm font-medium">Login</a>
                        <a href="https://kynexsolutions.com" target="_blank" rel="noreferrer" class="btn-primary rounded-full px-4 py-2 text-sm font-semibold">Book a Demo</a>
                    @endauth
                </div>
            </div>
        </header>

        <main>
            {{ $slot }}
        </main>

        <footer class="border-t border-[var(--border-subtle)] bg-[var(--surface-1)]/90 backdrop-blur">
            <div class="mx-auto flex max-w-7xl flex-col gap-4 px-6 py-8 text-sm text-[var(--text-muted)] lg:flex-row lg:items-center lg:justify-between lg:px-8">
                <div>
                    <div class="font-medium text-[var(--text-strong)]">Kynex AI Booking</div>
                    <div>AI front desk automation for clinics and service businesses.</div>
                </div>
                <div class="flex flex-wrap items-center gap-4">
                    <a href="{{ route('marketing.features') }}" class="hover:text-[var(--text-strong)]">Features</a>
                    <a href="{{ route('marketing.pricing') }}" class="hover:text-[var(--text-strong)]">Pricing</a>
                    <a href="https://kynexsolutions.com" target="_blank" rel="noreferrer" class="hover:text-[var(--text-strong)]">Kynex Solutions</a>
                </div>
            </div>
        </footer>
    </div>
</body>
</html>
