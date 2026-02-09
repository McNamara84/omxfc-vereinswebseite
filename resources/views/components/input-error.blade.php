@props(['for', 'id' => null])

@php
    $errorBag = $errors ?? session()->get('errors');
    $message = $errorBag?->first($for);
    $errorId = $id ?? $for . '-error';
@endphp

<p
    {{ $attributes->merge([
        'class' => 'mt-2 text-sm text-error min-h-[1.25rem]',
        'id' => $errorId,
    ]) }}
    data-error-for="{{ $for }}"
    role="status"
    aria-live="polite"
>
    {{ $message ?? '' }}
</p>
