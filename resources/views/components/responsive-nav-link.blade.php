@props(['active'])

@php
$classes = ($active ?? false)
            ? 'block w-full ps-3 pe-4 py-2 border-l-4 border-maddrax text-start text-base font-medium text-maddrax dark:text-maddrax bg-maddrax/20 dark:bg-gray-800 focus:outline-none focus:text-maddrax dark:focus:text-maddrax focus:bg-maddrax/30 dark:focus:bg-gray-800 focus:border-maddrax dark:focus:border-maddrax transition duration-150 ease-in-out'
            : 'block w-full ps-3 pe-4 py-2 border-l-4 border-transparent text-start text-base font-medium text-black dark:text-gray-400 hover:text-maddrax dark:hover:text-gray-200 hover:bg-maddrax/20 dark:hover:bg-gray-700 hover:border-maddrax dark:hover:border-gray-600 focus:outline-none focus:text-maddrax dark:focus:text-gray-200 focus:bg-maddrax/20 dark:focus:bg-gray-700 focus:border-maddrax dark:focus:border-gray-600 transition duration-150 ease-in-out';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
