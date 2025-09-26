@props(['for'])

@php
    $errorBag = $errors ?? session()->get('errors');
    $message = $errorBag?->first($for);
@endphp

<p
    {{ $attributes->merge([
        'class' => 'mt-2 text-sm text-red-600 dark:text-red-400 min-h-[1.25rem]',
    ]) }}
    data-error-for="{{ $for }}"
    role="status"
    aria-live="polite"
>
    {{ $message ?? '' }}
</p>
