@props([
    'name',
    'label',
    'id' => null,
    'help' => null,
    'value' => null,
    'required' => false,
    'min' => null,
    'max' => null,
    'step' => null,
    'placeholder' => null,
    'inputClass' => null,
])

@php
    $fieldId = $id ?? $name;
    $errorId = $fieldId . '-error';
    $hintId = $help ? $fieldId . '-hint' : null;
    $describedBy = collect([$hintId, $errorId])->filter()->implode(' ');
    $inputClasses = collect(['mt-1', 'block', 'w-full', $inputClass])->filter()->implode(' ');
    $baseControlClasses = collect(config('forms.base_control_classes', []))->implode(' ');
@endphp

<x-field-group :name="$name" :label="$label" :id="$fieldId" :errorId="$errorId" {{ $attributes->class(['w-full space-y-1']) }}>
    <input
        id="{{ $fieldId }}"
        name="{{ $name }}"
        type="number"
        aria-describedby="{{ $describedBy }}"
        @if($required) required @endif
        @if(! is_null($min)) min="{{ $min }}" @endif
        @if(! is_null($max)) max="{{ $max }}" @endif
        @if(! is_null($step)) step="{{ $step }}" @endif
        @if($placeholder) placeholder="{{ $placeholder }}" @endif
        value="{{ old($name, $value) }}"
        class="{{ trim($baseControlClasses . ' ' . $inputClasses) }}"
    >

    @if($help)
        <p id="{{ $hintId }}" class="text-sm text-gray-600 dark:text-gray-300">{{ $help }}</p>
    @endif
</x-field-group>
