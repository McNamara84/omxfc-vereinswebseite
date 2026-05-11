@props([
    'title',
    'icon' => null,
])

<li {{ $attributes }}>
    <details>
        <summary class="flex cursor-pointer items-center gap-3 rounded-xl px-4 py-2 text-sm font-medium text-base-content transition hover:bg-base-200/80">
            @if ($icon)
                <x-icon :name="$icon" class="h-4 w-4 shrink-0" />
            @endif
            <span>{{ $title }}</span>
        </summary>
        <ul class="mt-1 space-y-1 pl-3">
            {{ $slot }}
        </ul>
    </details>
</li>