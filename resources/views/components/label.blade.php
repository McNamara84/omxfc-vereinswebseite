@props(['value'])

<label {{ $attributes->merge(['class' => 'block font-medium text-sm text-maddrax-sand']) }}>
    {{ $value ?? $slot }}
</label>
