@props([
    'value' => null,
    'icon' => null,
])

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1 rounded-full border border-base-content/10 px-3 py-1 text-xs font-medium text-base-content']) }}>
    @if($icon)
        <span aria-hidden="true" class="text-[0.65rem]">•</span>
    @endif
    <span>{{ trim((string) $slot) !== '' ? $slot : $value }}</span>
</span>