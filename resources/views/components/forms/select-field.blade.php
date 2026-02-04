@props([
    'name',
    'label',
    'options' => [],
    'id' => null,
    'help' => null,
    'placeholder' => null,
    'required' => false,
    'value' => null,
    'autocomplete' => null,
    'selectClass' => null,
])

@php
    $fieldId = $id ?? $name;
    $errorId = $fieldId . '-error';
    $hintId = $help ? $fieldId . '-hint' : null;
    $describedBy = collect([$hintId, $errorId])->filter()->implode(' ');
    $selectClasses = collect(['mt-1', 'block', 'w-full', $selectClass])->filter()->implode(' ');
    $selectedValue = old($name, $value);
    $baseControlClasses = collect(config('forms.base_control_classes', []))->implode(' ');
@endphp

<x-field-group :name="$name" :label="$label" :id="$fieldId" :errorId="$errorId" {{ $attributes->class(['w-full space-y-1']) }}>
    <select
        id="{{ $fieldId }}"
        name="{{ $name }}"
        aria-describedby="{{ $describedBy }}"
        @if($required) required @endif
        @if($autocomplete) autocomplete="{{ $autocomplete }}" @endif
        class="{{ trim($baseControlClasses . ' ' . $selectClasses) }}"
    >
        @if(! is_null($placeholder))
            <option value="" @selected($selectedValue === null || $selectedValue === '')>{{ $placeholder }}</option>
        @endif

        @foreach($options as $optionValue => $optionLabel)
            <option value="{{ $optionValue }}" @selected((string) $selectedValue === (string) $optionValue)>{{ $optionLabel }}</option>
        @endforeach
    </select>

    @if($help)
        <p id="{{ $hintId }}" class="text-sm text-gray-600 dark:text-gray-300">{{ $help }}</p>
    @endif
</x-field-group>
