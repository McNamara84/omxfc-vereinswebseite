@props(['name', 'label', 'id' => null, 'labelHtml' => null])
@php
    $id = $id ?? $name;
    $errorId = 'error-' . $id;
@endphp
<div {{ $attributes }}>
    <x-label for="{{ $id }}">
        @if(! is_null($labelHtml))
            {!! $labelHtml !!}
        @else
            {{ $label }}
        @endif
    </x-label>
    {{ $slot }}
    <x-input-error :for="$name" id="{{ $errorId }}" />
</div>
