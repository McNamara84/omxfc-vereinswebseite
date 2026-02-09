@props(['value'])

<label {{ $attributes->merge(['class' => 'block font-medium text-sm text-base-content/70']) }}>
    {{ $value ?? $slot }}
</label>
