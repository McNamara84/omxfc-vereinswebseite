@props([
    'text' => null,
])

<span
    {{ $attributes->merge(['class' => 'loading']) }}
    @if($text)
        role="status"
        aria-label="{{ $text }}"
    @else
        aria-hidden="true"
    @endif
></span>