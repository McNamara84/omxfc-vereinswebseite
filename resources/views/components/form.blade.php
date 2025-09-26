@props(['name', 'label', 'id' => null])
@php
    $id = $id ?? $name;
    $errorId = 'error-' . $id;
@endphp
<div {{ $attributes }}>
    <x-label for="{{ $id }}" :value="$label" />
    {{ $slot }}
    <x-input-error :for="$name" id="{{ $errorId }}" />
</div>
