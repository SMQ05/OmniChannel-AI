@props([
    'eyebrow' => null,
    'title',
    'description' => null,
])

<div {{ $attributes->merge(['class' => 'mb-6 flex flex-wrap items-start justify-between gap-4']) }}>
    <div class="max-w-3xl">
        @if($eyebrow)
            <div class="page-eyebrow">{{ $eyebrow }}</div>
        @endif
        <h1 class="mt-2 text-3xl font-semibold text-[var(--text-strong)]">{{ $title }}</h1>
        @if($description)
            <p class="mt-2 text-sm text-[var(--text-muted)]">{{ $description }}</p>
        @endif
    </div>

    @if(trim($slot) !== '')
        <div class="flex items-center gap-3">
            {{ $slot }}
        </div>
    @endif
</div>
