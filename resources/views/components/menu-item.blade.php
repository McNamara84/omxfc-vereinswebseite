@props([
    'title',
    'link' => null,
    'icon' => null,
])

<li>
    <a
        @if ($link)
            href="{{ $link }}"
        @endif
        {{ $attributes->class('my-0.5 flex w-full items-center gap-3 rounded-xl px-4 py-2 text-sm leading-5 text-base-content transition hover:bg-base-200/80') }}
    >
        @if ($icon)
            <x-icon :name="$icon" class="h-4 w-4 shrink-0" />
        @endif
        <span>{{ $title }}</span>
    </a>
</li>