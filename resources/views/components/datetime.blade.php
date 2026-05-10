@props([
    'label' => null,
    'type' => 'datetime-local',
    'hint' => null,
    'value' => null,
])

<label class="block space-y-1">
    @if($label)
        <span class="block text-sm font-medium text-base-content">{{ $label }}</span>
    @endif

    <input
        type="{{ $type }}"
        @if(! is_null($value))
            value="{{ $value }}"
        @endif
        {{ $attributes->merge(['class' => 'input input-bordered w-full']) }}
    />

    @if($hint)
        <span class="block text-xs text-base-content/60">{{ $hint }}</span>
    @endif
</label>