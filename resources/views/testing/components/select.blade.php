@props([
    'label' => null,
    'options' => [],
    'placeholder' => null,
    'icon' => null,
])

<label class="block space-y-1">
    @if($label)
        <span class="block text-sm font-medium text-base-content">{{ $label }}</span>
    @endif

    <div class="relative">
        @if($icon)
            <span aria-hidden="true" class="pointer-events-none absolute inset-y-0 left-3 inline-flex items-center justify-center"></span>
        @endif

        <select {{ $attributes->merge(['class' => trim('select select-bordered w-full '.($icon ? 'pl-10' : ''))]) }}>
            @if($placeholder)
                <option value="">{{ $placeholder }}</option>
            @endif

            @foreach($options as $option)
                @php
                    $optionValue = is_array($option) ? ($option['id'] ?? $option['value'] ?? $option['name'] ?? '') : $option;
                    $optionLabel = is_array($option) ? ($option['name'] ?? $option['label'] ?? $optionValue) : $option;
                @endphp
                <option value="{{ $optionValue }}">{{ $optionLabel }}</option>
            @endforeach
        </select>
    </div>
</label>