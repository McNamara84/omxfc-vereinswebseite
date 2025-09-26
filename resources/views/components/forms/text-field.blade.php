@props([
    'name',
    'label',
    'type' => 'text',
    'id' => null,
    'help' => null,
    'value' => null,
    'required' => false,
    'autocomplete' => null,
    'placeholder' => null,
    'inputClass' => null,
])

@php
    $fieldId = $id ?? $name;
    $errorId = $fieldId . '-error';
    $hintId = $help ? $fieldId . '-hint' : null;
    $describedBy = collect([$hintId, $errorId])->filter()->implode(' ');
    $inputClasses = collect(['mt-1', 'block', 'w-full', $inputClass])->filter()->implode(' ');
    $valueAttribute = $type === 'password' ? null : old($name, $value);
@endphp

<x-form :name="$name" :label="$label" :id="$fieldId" {{ $attributes->class(['w-full space-y-1']) }}>
    <x-input
        id="{{ $fieldId }}"
        name="{{ $name }}"
        type="{{ $type }}"
        aria-describedby="{{ $describedBy }}"
        @if($required) required @endif
        @if($autocomplete) autocomplete="{{ $autocomplete }}" @endif
        @if($placeholder) placeholder="{{ $placeholder }}" @endif
        @if(! is_null($valueAttribute)) value="{{ $valueAttribute }}" @endif
        class="{{ $inputClasses }}"
    />

    @if($help)
        <p id="{{ $hintId }}" class="text-sm text-gray-600 dark:text-gray-300">{{ $help }}</p>
    @endif
</x-form>
