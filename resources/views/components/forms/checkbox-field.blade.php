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
            class="h-4 w-4 rounded border-gray-300 dark:border-gray-600
                   text-[#8B0116] dark:text-[#FF6B81]
                   focus:ring-[#8B0116] dark:focus:ring-[#FF6B81]
                   dark:bg-gray-700"
        >
    </div>
    <div class="ml-3">
        <label for="{{ $fieldId }}" class="text-sm font-medium text-gray-700 dark:text-gray-200">
            {{ $label }}
        </label>
        @if($help)
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $help }}</p>
        @endif
    </div>
</div>

@error($name)
    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
@enderror
