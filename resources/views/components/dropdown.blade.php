@props([
    'align' => 'right',
    'width' => '48',
    'contentClasses' => 'bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600',
    'dropdownClasses' => '',
    'label' => null,
    'right' => false,
])

@php
$align = $right ? 'right' : $align;

$alignmentClasses = match ($align) {
    'left' => 'ltr:origin-top-left rtl:origin-top-right start-0',
    'top' => 'origin-top',
    'none', 'false' => '',
    default => 'ltr:origin-top-right rtl:origin-top-left end-0',
};

$width = match ($width) {
    '48' => 'w-48',
    '60' => 'w-60',
    default => 'w-48',
};

$hasTriggerSlot = isset($trigger) && trim((string) $trigger) !== '';
$dropdownContent = isset($content) && trim((string) $content) !== '' ? $content : $slot;
$rootClasses = $hasTriggerSlot ? trim('relative '.$attributes->get('class')) : 'relative';
$buttonClasses = trim('btn '.$attributes->get('class'));
@endphp

<div {{ $hasTriggerSlot ? $attributes->except('class')->merge(['class' => $rootClasses]) : $attributes->except('class')->merge(['class' => $rootClasses]) }} x-data="{ open: false }" @click.away="open = false" @close.stop="open = false">
    <div @click="open = ! open">
        @if($hasTriggerSlot)
            {{ $trigger }}
        @else
            <button type="button" class="{{ $buttonClasses }}">
                <span>{{ $label }}</span>
                <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.168l3.71-3.938a.75.75 0 1 1 1.08 1.04l-4.25 4.5a.75.75 0 0 1-1.08 0l-4.25-4.5a.75.75 0 0 1 .02-1.06Z" clip-rule="evenodd" />
                </svg>
            </button>
        @endif
    </div>

    <div x-show="open"
            x-cloak
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="transform opacity-0 scale-95"
            x-transition:enter-end="transform opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-75"
            x-transition:leave-start="transform opacity-100 scale-100"
            x-transition:leave-end="transform opacity-0 scale-95"
            class="absolute z-50 mt-2 {{ $width }} rounded-md shadow-lg {{ $alignmentClasses }} {{ $dropdownClasses }}"
            @click="open = false">
        <div class="rounded-md {{ $contentClasses }}">
            <ul class="menu p-2">
                {{ $dropdownContent }}
            </ul>
        </div>
    </div>
</div>
