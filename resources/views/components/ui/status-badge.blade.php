@props([
    'tone' => 'neutral',
])

@php
    $classes = match ($tone) {
        'success' => 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300',
        'warning' => 'bg-amber-500/15 text-amber-700 dark:text-amber-300',
        'danger' => 'bg-red-500/15 text-red-700 dark:text-red-300',
        'brand' => 'bg-blue-500/15 text-blue-700 dark:text-blue-300',
        default => 'bg-slate-500/12 text-slate-700 dark:text-slate-300',
    };
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold ' . $classes]) }}>
    {{ $slot }}
</span>
