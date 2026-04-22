<button {{ $attributes->merge(['type' => 'button', 'class' => 'btn-secondary px-4 py-2 text-xs uppercase tracking-widest shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-25']) }}>
    {{ $slot }}
</button>
