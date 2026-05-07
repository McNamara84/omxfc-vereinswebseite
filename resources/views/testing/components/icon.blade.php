@props([
    'name' => null,
])

<span
    aria-hidden="true"
    data-icon-name="{{ $name }}"
    {{ $attributes->merge(['class' => 'inline-flex items-center justify-center']) }}
></span>