<button {{ $attributes->merge(['type' => 'button', 'class' => 'inline-flex items-center px-4 py-2 bg-ash border border-rust rounded-md font-semibold text-xs text-dust uppercase tracking-widest shadow-sm hover:bg-charcoal focus:outline-none focus:ring-2 focus:ring-rust focus:ring-offset-2 disabled:opacity-25 transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
