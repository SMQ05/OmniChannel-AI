@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'field shadow-sm focus:border-indigo-500 focus:ring-indigo-500']) }}>
