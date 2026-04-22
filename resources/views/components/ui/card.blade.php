@props([
    'tone' => 'default',
    'padding' => 'p-6',
])

@php
    $classes = $tone === 'subtle' ? 'panel-subtle' : 'panel';
@endphp

<div {{ $attributes->merge(['class' => $classes . ' ' . $padding]) }}>
    {{ $slot }}
</div>
