@props([
    'label' => null,
])

<label {{ $attributes->except(['wire:model'])->class('inline-flex items-center gap-3') }}>
    <input
        type="checkbox"
        @if($attributes->has('wire:model'))
            wire:model="{{ $attributes->get('wire:model') }}"
        @endif
        @if($attributes->has('wire:model.live'))
            wire:model.live="{{ $attributes->get('wire:model.live') }}"
        @endif
        @if($attributes->has('wire:model.blur'))
            wire:model.blur="{{ $attributes->get('wire:model.blur') }}"
        @endif
        {{ $attributes->except(['class', 'wire:model', 'wire:model.live', 'wire:model.blur'])->merge(['class' => 'checkbox']) }}
    />

    <span>{{ trim((string) $slot) !== '' ? $slot : $label }}</span>
</label>