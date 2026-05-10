@props([
    'label' => null,
    'hint' => null,
    'rows' => 4,
    'value' => null,
])

@php
    $textareaValue = $slot->isNotEmpty() ? trim((string) $slot) : $value;
@endphp

<label class="block space-y-1">
    @if($label)
        <span class="block text-sm font-medium text-base-content">{{ $label }}</span>
    @endif

    <textarea rows="{{ $rows }}" {{ $attributes->merge(['class' => 'textarea textarea-bordered w-full']) }}>{{ $textareaValue }}</textarea>

    @if($hint)
        <span class="block text-xs text-base-content/60">{{ $hint }}</span>
    @endif
</label>