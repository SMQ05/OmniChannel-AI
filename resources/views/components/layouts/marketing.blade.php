<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? config('app.name') }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full bg-[radial-gradient(circle_at_top,_rgba(79,70,229,0.18),_transparent_35%),linear-gradient(180deg,_#050816_0%,_#0b1220_50%,_#0f172a_100%)] text-white antialiased">
    <div class="min-h-screen">
        <header class="sticky top-0 z-30 border-b border-white/10 bg-slate-950/80 backdrop-blur">
            <div class="mx-auto flex max-w-7xl items-center justify-between px-6 py-4 lg:px-8">
                <a href="{{ route('marketing.home') }}" class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-indigo-500/20 text-sm font-bold text-indigo-300">K</div>
                    <div>
                        <div class="text-sm font-semibold tracking-wide text-white">Kynex AI Booking</div>
                        <div class="text-xs text-slate-400">AI Front Desk Platform</div>
                    </div>
                </a>

                <nav class="hidden items-center gap-6 text-sm text-slate-300 md:flex">
                    <a href="{{ route('marketing.features') }}" class="hover:text-white">Features</a>
                    <a href="{{ route('marketing.pricing') }}" class="hover:text-white">Pricing</a>
                    <a href="{{ route('privacy.policy') }}" class="hover:text-white">Privacy</a>
                </nav>

                <div class="flex items-center gap-3">
                    @auth
                        <a href="{{ route('dashboard') }}" class="rounded-full border border-white/15 px-4 py-2 text-sm font-medium text-slate-200 hover:border-indigo-400/50 hover:text-white">Open App</a>
                    @else
                        <a href="{{ route('login') }}" class="rounded-full border border-white/15 px-4 py-2 text-sm font-medium text-slate-200 hover:border-indigo-400/50 hover:text-white">Login</a>
                        <a href="https://kynexsolutions.com" target="_blank" rel="noreferrer" class="rounded-full bg-indigo-500 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-400">Book a Demo</a>
                    @endauth
                </div>
            </div>
        </header>

        <main>
            {{ $slot }}
        </main>

        <footer class="border-t border-white/10 bg-slate-950/70">
            <div class="mx-auto flex max-w-7xl flex-col gap-4 px-6 py-8 text-sm text-slate-400 lg:flex-row lg:items-center lg:justify-between lg:px-8">
                <div>
                    <div class="font-medium text-slate-200">Kynex AI Booking</div>
                    <div>AI front desk automation for clinics and service businesses.</div>
                </div>
                <div class="flex flex-wrap items-center gap-4">
                    <a href="{{ route('marketing.features') }}" class="hover:text-white">Features</a>
                    <a href="{{ route('marketing.pricing') }}" class="hover:text-white">Pricing</a>
                    <a href="https://kynexsolutions.com" target="_blank" rel="noreferrer" class="hover:text-white">Kynex Solutions</a>
                </div>
            </div>
        </footer>
    </div>
</body>
</html>
