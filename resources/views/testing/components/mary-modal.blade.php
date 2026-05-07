@props([
    'id' => null,
    'title' => null,
    'separator' => false,
    'withoutTrapFocus' => false,
])

<div
    @if($id) id="{{ $id }}" @endif
    {{ $attributes->merge(['class' => 'hidden']) }}
    aria-hidden="true"
>
    @if($title)
        <h2>{{ $title }}</h2>
    @endif

    {{ $slot }}

    @isset($actions)
        <div>{{ $actions }}</div>
    @endisset
</div>