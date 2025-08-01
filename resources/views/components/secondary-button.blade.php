<button {{ $attributes->merge(['type' => 'button', 'class' => 'inline-flex items-center px-4 py-2 bg-maddrax-black border border-maddrax-red rounded-md font-semibold text-xs text-maddrax-sand uppercase tracking-widest shadow-sm hover:bg-maddrax-red focus:outline-none focus:ring-2 focus:ring-maddrax-red focus:ring-offset-2 disabled:opacity-25 transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
