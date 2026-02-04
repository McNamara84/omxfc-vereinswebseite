@props([
    'name',
    'label',
    'id' => null,
    'help' => null,
    'value' => null,
    'required' => false,
    'rows' => 4,
    'placeholder' => null,
])

@php
    $fieldId = $id ?? $name;
    $errorId = $fieldId . '-error';
    $hintId = $help ? $fieldId . '-hint' : null;
    $describedBy = collect([$hintId, $errorId])->filter()->implode(' ');
    $baseControlClasses = collect(config('forms.base_control_classes', []))->implode(' ');
@endphp

<x-field-group :name="$name" :label="$label" :id="$fieldId" :errorId="$errorId" {{ $attributes->class(['w-full space-y-1']) }}>
    <textarea
        id="{{ $fieldId }}"
        name="{{ $name }}"
        rows="{{ $rows }}"
        aria-describedby="{{ $describedBy }}"
        @if($required) required @endif
        @if($placeholder) placeholder="{{ $placeholder }}" @endif
        class="{{ trim($baseControlClasses . ' mt-1 block w-full') }}"
    >{{ old($name, $value) }}</textarea>

    @if($help)
        <p id="{{ $hintId }}" class="text-sm text-gray-600 dark:text-gray-300">{{ $help }}</p>
    @endif
</x-field-group>
