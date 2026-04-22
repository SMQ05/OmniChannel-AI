<button {{ $attributes->merge(['type' => 'submit', 'class' => 'btn-primary px-4 py-2 text-xs uppercase tracking-widest focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2']) }}>
    {{ $slot }}
</button>
