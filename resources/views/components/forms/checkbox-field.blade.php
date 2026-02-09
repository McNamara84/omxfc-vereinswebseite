@props([
    'name',
    'label',
    'id' => null,
    'help' => null,
    'checked' => false,
])

@php
    $fieldId = $id ?? $name;
    $isChecked = old($name, $checked);
@endphp

<div {{ $attributes->class(['flex items-start']) }}>
    <div class="flex items-center h-5">
        <input
            type="checkbox"
            id="{{ $fieldId }}"
            name="{{ $name }}"
            value="1"
            @checked($isChecked)
            class="h-4 w-4 rounded border-base-content/30
                   text-primary
                   focus:ring-primary"
        >
    </div>
    <div class="ml-3">
        <label for="{{ $fieldId }}" class="text-sm font-medium text-base-content/70">
            {{ $label }}
        </label>
        @if($help)
            <p class="text-sm text-base-content/50">{{ $help }}</p>
        @endif
    </div>
</div>

@error($name)
    <p class="mt-1 text-sm text-error">{{ $message }}</p>
@enderror
