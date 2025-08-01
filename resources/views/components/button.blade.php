<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center px-4 py-2 bg-maddrax-red border border-transparent rounded-md font-semibold text-xs text-maddrax-sand uppercase tracking-widest hover:bg-maddrax-black focus:bg-maddrax-black active:bg-maddrax-red focus:outline-none focus:ring-2 focus:ring-maddrax-red focus:ring-offset-2 disabled:opacity-50 transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
