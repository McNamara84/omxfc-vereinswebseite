@props([
    'name',
    'label',
    'id' => null,
    'min' => 0,
    'max' => 100,
    'step' => 1,
    'value' => null,
    'help' => null,
    'outputId' => null,
    'outputPrefix' => '',
    'outputSuffix' => '',
    'inputClass' => null,
])

@php
    $fieldId = $id ?? $name;
    $errorId = 'error-' . $fieldId;
    $hintId = $help ? $fieldId . '-hint' : null;
    $outputElementId = $outputId ?? $fieldId . '-output';
    $valueAttribute = old($name, $value ?? $min);
    $displayValue = $outputPrefix . $valueAttribute . $outputSuffix;
    $describedBy = collect([$hintId, $outputElementId, $errorId])->filter()->implode(' ');
    $inputClasses = collect(['mt-1', 'block', 'w-full', $inputClass])->filter()->implode(' ');
    $baseControlClasses = collect(config('forms.base_control_classes', []))->implode(' ');
    $labelHtml = new \Illuminate\Support\HtmlString(e($label) . ': ' . '<span id="' . e($outputElementId) . '" class="font-semibold text-[#8B0116] dark:text-[#ff4b63]" aria-live="polite">' . e($displayValue) . '</span>');
@endphp

<x-form
    :name="$name"
    :label="$label"
    :label-html="$labelHtml"
    :id="$fieldId"
    {{ $attributes->class(['w-full space-y-2']) }}
>
    <input
        type="range"
        id="{{ $fieldId }}"
        name="{{ $name }}"
        min="{{ $min }}"
        max="{{ $max }}"
        step="{{ $step }}"
        value="{{ $valueAttribute }}"
        aria-describedby="{{ $describedBy }}"
        class="{{ trim($baseControlClasses . ' ' . $inputClasses) }}"
        data-output-target="{{ $outputElementId }}"
        data-output-prefix="{{ $outputPrefix }}"
        data-output-suffix="{{ $outputSuffix }}"
    >

    @if($help)
        <p id="{{ $hintId }}" class="text-sm text-gray-600 dark:text-gray-300">{{ $help }}</p>
    @endif
</x-form>
