@props(['active'])

@php
$classes = ($active ?? false)
            ? 'inline-flex items-center px-1 pt-1 border-b-2 border-maddrax text-sm font-medium leading-5 text-maddrax dark:text-gray-100 focus:outline-none focus:border-maddrax transition duration-150 ease-in-out'
            : 'inline-flex items-center px-1 pt-1 border-b-2 border-transparent text-sm font-medium leading-5 text-black dark:text-gray-400 hover:text-maddrax dark:hover:text-gray-300 hover:border-maddrax dark:hover:border-gray-700 focus:outline-none focus:text-maddrax dark:focus:text-gray-300 focus:border-maddrax dark:focus:border-gray-700 transition duration-150 ease-in-out';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
