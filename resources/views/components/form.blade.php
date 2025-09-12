@props(['name', 'label'])
@php
    $errorId = $name . '-error';
@endphp
<div {{ $attributes }}>
    <x-label for="{{ $name }}" :value="$label" />
    {{ $slot }}
    <x-input-error :for="$name" id="{{ $errorId }}" />
</div>
