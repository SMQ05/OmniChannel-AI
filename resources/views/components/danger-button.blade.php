<button {{ $attributes->merge(['type' => 'submit', 'class' => 'btn-danger px-4 py-2 border border-transparent text-xs uppercase tracking-widest focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2']) }}>
    {{ $slot }}
</button>
