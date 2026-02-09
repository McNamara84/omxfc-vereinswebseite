<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center px-4 py-2 bg-primary border border-transparent rounded-md font-semibold text-xs text-primary-content uppercase tracking-widest hover:bg-primary/80 focus:bg-primary/80 active:bg-primary/90 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 focus:ring-offset-base-100 disabled:opacity-50 transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
