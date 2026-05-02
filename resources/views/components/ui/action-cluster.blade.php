@props([
    'align' => 'start',
    'gap' => '2',
    'stacked' => false,
])

@php
    $alignmentClasses = match ($align) {
        'center' => 'items-center justify-center',
        'end' => 'items-start justify-start lg:items-end lg:justify-end',
        'between' => 'items-center justify-between',
        default => 'items-start justify-start',
    };

    $gapClasses = match ((string) $gap) {
        '3' => 'gap-3',
        '4' => 'gap-4',
        default => 'gap-2',
    };

    $directionClasses = $stacked
        ? 'flex flex-col'
        : 'flex flex-wrap';
@endphp

<div {{ $attributes->class([$directionClasses, $alignmentClasses, $gapClasses]) }}>
    {{ $slot }}
</div>