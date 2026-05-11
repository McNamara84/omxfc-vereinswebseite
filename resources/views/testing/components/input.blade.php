@props([
    'label' => null,
    'type' => 'text',
    'hint' => null,
    'icon' => null,
    'clearable' => false,
    'value' => null,
])

<label class="block space-y-1">
    @if($label)
        <span class="block text-sm font-medium text-base-content">{{ $label }}</span>
    @endif

    <div class="relative">
        @if($icon)
            <span aria-hidden="true" class="pointer-events-none absolute inset-y-0 left-3 inline-flex items-center justify-center"></span>
        @endif

        <input
            type="{{ $type }}"
            @if(! is_null($value))
                value="{{ $value }}"
            @endif
            {{ $attributes->merge(['class' => trim('input input-bordered w-full '.($icon ? 'pl-10' : ''))]) }}
        />
    </div>

    @if($hint)
        <span class="block text-xs text-base-content/60">{{ $hint }}</span>
    @endif
</label>