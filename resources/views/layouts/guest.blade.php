<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>
        <script>
            (() => {
                const theme = localStorage.getItem('kynex-theme') || 'light';
                document.documentElement.classList.toggle('dark', theme === 'dark');
                document.documentElement.dataset.theme = theme === 'dark' ? 'dark' : 'light';
            })();
        </script>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="antialiased">
        <div class="min-h-screen bg-[radial-gradient(circle_at_top,_rgba(37,99,235,0.10),_transparent_28%),linear-gradient(180deg,_var(--bg-app)_0%,_var(--bg-elevated)_100%)] px-6 py-12">
            <div class="mx-auto flex max-w-5xl items-center justify-between">
                <a href="/">
                    <x-application-logo class="h-16 w-16 fill-current text-[var(--brand-strong)]" />
                </a>
                <x-theme-toggle />
            </div>

            <div class="mx-auto mt-10 grid max-w-5xl gap-10 lg:grid-cols-[0.9fr_1.1fr]">
                <div class="hidden flex-col justify-between rounded-[2rem] border border-[var(--border-subtle)] bg-[var(--surface-1)] p-10 shadow-[var(--shadow-card)] lg:flex">
                    <div>
                        <div class="page-eyebrow">Kynex Platform</div>
                        <h1 class="mt-4 text-4xl font-semibold text-[var(--text-strong)]">AI front desk operations without the visual clutter.</h1>
                        <p class="mt-4 text-base leading-7 text-[var(--text-muted)]">
                            Manage conversations, appointments, reminders, and rollout controls in one interface that stays readable in light mode and calm in dark mode.
                        </p>
                    </div>
                    <div class="grid gap-3 text-sm text-[var(--text-muted)]">
                        <div class="panel-subtle px-4 py-3">Business-owned operations stay on the business side.</div>
                        <div class="panel-subtle px-4 py-3">Platform truth stays controlled from the admin side.</div>
                        <div class="panel-subtle px-4 py-3">The shared brain remains a known architectural weakness and will be clarified later without hiding it behind UI noise.</div>
                    </div>
                </div>

                <div class="panel w-full overflow-hidden px-6 py-8 sm:px-8">
                    {{ $slot }}
                </div>
            </div>
        </div>
    </body>
</html>
