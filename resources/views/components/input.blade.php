@props(['disabled' => false])

<input {{ $disabled ? 'disabled' : '' }} {!! $attributes->merge(['class' => 'border-rust bg-charcoal text-dust focus:border-rust focus:ring-rust rounded-md shadow-sm']) !!}>
