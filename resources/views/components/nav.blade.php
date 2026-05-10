@props([
    'sticky' => false,
])

<header {{ $attributes->class([
    'border-b border-base-content/10 bg-base-100/95 backdrop-blur',
    'sticky top-0 z-40' => $sticky,
]) }}>
    <div class="mx-auto flex max-w-[88rem] items-center justify-between gap-4 px-4 py-3 sm:px-6 lg:px-8">
        <div class="flex min-w-0 flex-1 items-center gap-4">
            {{ $brand ?? '' }}
        </div>

        <div class="flex shrink-0 items-center gap-3">
            {{ $actions ?? '' }}
        </div>
    </div>
</header>