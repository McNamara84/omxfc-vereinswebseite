<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center px-4 py-2 bg-rust text-dust border border-transparent rounded-md font-semibold text-xs uppercase tracking-widest hover:bg-rust-light focus:bg-rust-light active:bg-rust focus:outline-none focus:ring-2 focus:ring-rust focus:ring-offset-2 disabled:opacity-50 transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
