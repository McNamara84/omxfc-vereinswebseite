@props(['active'])

@php
$classes = ($active ?? false)
            ? 'block w-full ps-3 pe-4 py-2 border-l-4 border-rust text-start text-base font-medium text-rust bg-ash focus:outline-none focus:text-rust focus:bg-charcoal focus:border-rust transition duration-150 ease-in-out'
            : 'block w-full ps-3 pe-4 py-2 border-l-4 border-transparent text-start text-base font-medium text-dust hover:text-rust hover:bg-ash hover:border-rust focus:outline-none focus:text-rust focus:bg-ash focus:border-rust transition duration-150 ease-in-out';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
