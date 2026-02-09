@props(['disabled' => false])

<input {{ $disabled ? 'disabled' : '' }} {!! $attributes->merge(['class' => 'px-3 py-2 leading-5 border border-base-content/30 bg-base-100 text-base-content focus:outline-none focus:border-primary focus:ring-2 focus:ring-offset-2 focus:ring-primary focus:ring-offset-base-100 rounded-md shadow-sm transition-colors duration-150 ease-in-out']) !!}>
