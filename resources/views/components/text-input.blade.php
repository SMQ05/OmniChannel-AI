@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'rounded-md border border-gray-700 bg-gray-950 text-white placeholder-gray-500 shadow-sm focus:border-indigo-500 focus:ring-indigo-500']) }}>
