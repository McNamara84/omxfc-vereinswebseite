@props([
    'label' => null,
    'hint' => null,
])

<div>
    <fieldset class="fieldset py-0">
        @if($label)
            <legend class="fieldset-legend mb-0.5">
                {{ $label }}

                @if($attributes->has('required'))
                    <span class="text-error">*</span>
                @endif
            </legend>
        @endif

        <div class="w-full">
            <input
                type="password"
                {{ $attributes->merge(['class' => 'input input-bordered w-full']) }}
            />
        </div>

        @if($hint)
            <span class="block text-xs text-base-content/60">{{ $hint }}</span>
        @endif
    </fieldset>
</div>