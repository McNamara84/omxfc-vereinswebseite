<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center px-4 py-2 bg-maddrax dark:bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-apocalypse dark:text-gray-800 uppercase tracking-widest hover:bg-maddrax/80 dark:hover:bg-white focus:bg-maddrax/80 dark:focus:bg-white active:bg-maddrax/90 dark:active:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-maddrax focus:ring-offset-2 dark:focus:ring-offset-gray-800 disabled:opacity-50 transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
