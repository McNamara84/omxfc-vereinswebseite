@props(['active'])

@php
    $classes = ($active ?? false)
            ? 'inline-flex items-center px-1 pt-1 border-b-2 border-maddrax-red text-sm font-medium leading-5 text-maddrax-sand focus:outline-none focus:border-maddrax-red transition duration-150 ease-in-out'
            : 'inline-flex items-center px-1 pt-1 border-b-2 border-transparent text-sm font-medium leading-5 text-maddrax-sand hover:text-maddrax-red hover:border-maddrax-red focus:outline-none focus:text-maddrax-red focus:border-maddrax-red transition duration-150 ease-in-out';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
