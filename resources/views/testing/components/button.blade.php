@props([
    'label' => null,
    'link' => null,
    'href' => null,
    'type' => 'button',
    'icon' => null,
    'tooltip' => null,
])

@php($target = $link ?? $href)
@php($content = trim((string) $slot) !== '' ? $slot : $label)

@if($target)
    <a href="{{ $target }}" title="{{ $tooltip }}" {{ $attributes->merge(['class' => 'inline-flex items-center justify-center gap-2 rounded-lg border border-base-content/15 px-4 py-2 text-sm font-medium text-base-content transition hover:bg-base-200']) }}>
        @if($icon)
            <span aria-hidden="true" class="inline-flex size-4 items-center justify-center text-xs"></span>
        @endif
        <span>{{ $content }}</span>
    </a>
@else
    <button type="{{ $type }}" title="{{ $tooltip }}" {{ $attributes->merge(['class' => 'inline-flex items-center justify-center gap-2 rounded-lg border border-base-content/15 px-4 py-2 text-sm font-medium text-base-content transition hover:bg-base-200']) }}>
        @if($icon)
            <span aria-hidden="true" class="inline-flex size-4 items-center justify-center text-xs"></span>
        @endif
        <span>{{ $content }}</span>
    </button>
@endif