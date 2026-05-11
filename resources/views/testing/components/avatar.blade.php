@props([
    'image' => null,
    'alt' => 'Avatar',
])

@php($fallback = trim((string) $alt) !== '' ? mb_substr((string) $alt, 0, 1) : '?')

@if($image)
    <img src="{{ $image }}" alt="{{ $alt }}" {{ $attributes->merge(['class' => 'size-10 rounded-full object-cover']) }}>
@else
    <span aria-hidden="true" {{ $attributes->merge(['class' => 'inline-flex size-10 items-center justify-center rounded-full bg-base-300 text-sm font-semibold text-base-content']) }}>
        {{ mb_strtoupper($fallback) }}
    </span>
@endif
