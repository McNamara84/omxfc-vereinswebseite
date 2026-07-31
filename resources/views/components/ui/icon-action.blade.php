@props([
    'icon',
    'tooltip',
    'tooltipPosition' => 'top',
    'tooltipAlign' => 'center',
    'wrapperClass' => null,
])

@php
    $supportedPositions = ['top', 'bottom', 'left', 'right'];
    $supportedAlignments = ['start', 'center', 'end'];

    if (! in_array($tooltipPosition, $supportedPositions, true)) {
        throw new \InvalidArgumentException("Unsupported tooltip position: {$tooltipPosition}");
    }

    if (! in_array($tooltipAlign, $supportedAlignments, true)) {
        throw new \InvalidArgumentException("Unsupported tooltip alignment: {$tooltipAlign}");
    }

    $wrapperClasses = implode(' ', array_filter([
        'tooltip',
        "tooltip-{$tooltipPosition}",
        "tooltip-{$tooltipAlign}",
        $wrapperClass,
    ]));
@endphp

<span class="{{ $wrapperClasses }}" data-tip="{{ $tooltip }}">
    <x-button
        :icon="$icon"
        {{ $attributes->merge(['aria-label' => $tooltip]) }}
    />
</span>
