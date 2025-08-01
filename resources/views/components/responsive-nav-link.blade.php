@props(['active'])

@php
    $classes = ($active ?? false)
            ? 'block w-full ps-3 pe-4 py-2 border-l-4 border-maddrax-red text-start text-base font-medium text-maddrax-red bg-maddrax-red/20 focus:outline-none focus:text-maddrax-red focus:bg-maddrax-red/20 focus:border-maddrax-red transition duration-150 ease-in-out'
            : 'block w-full ps-3 pe-4 py-2 border-l-4 border-transparent text-start text-base font-medium text-maddrax-sand hover:text-maddrax-red hover:bg-maddrax-black hover:border-maddrax-red focus:outline-none focus:text-maddrax-red focus:bg-maddrax-black focus:border-maddrax-red transition duration-150 ease-in-out';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
