@props([
    'label',
    'value',
    'tone' => 'brand',
])

@php
    $toneMap = [
        'brand' => 'from-blue-500/10 to-cyan-400/10',
        'success' => 'from-emerald-500/10 to-teal-400/10',
        'warning' => 'from-amber-500/12 to-orange-400/10',
        'danger' => 'from-red-500/10 to-rose-400/10',
    ];
@endphp

<div {{ $attributes->merge(['class' => 'panel relative overflow-hidden p-5']) }}>
    <div class="pointer-events-none absolute inset-0 bg-gradient-to-br {{ $toneMap[$tone] ?? $toneMap['brand'] }}"></div>
    <div class="relative">
        <div class="page-eyebrow">{{ $label }}</div>
        <div class="mt-3 text-4xl font-bold text-[var(--text-strong)]">{{ $value }}</div>
    </div>
</div>
