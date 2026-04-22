@props(['value'])

<label {{ $attributes->merge(['class' => 'block text-sm font-medium text-[var(--text-muted)]']) }}>
    {{ $value ?? $slot }}
</label>
