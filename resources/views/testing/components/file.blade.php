@props([
    'label' => null,
    'hint' => null,
])

<label class="block space-y-1">
    @if($label)
        <span class="block text-sm font-medium text-base-content">{{ $label }}</span>
    @endif

    <input type="file" {{ $attributes->merge(['class' => 'file-input file-input-bordered w-full']) }}>

    @if($hint)
        <span class="block text-xs text-base-content/60">{{ $hint }}</span>
    @endif
</label>
