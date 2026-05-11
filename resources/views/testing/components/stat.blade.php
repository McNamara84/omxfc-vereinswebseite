@props([
    'title' => null,
    'value' => null,
    'description' => null,
    'icon' => null,
    'color' => null,
])

<div {{ $attributes->merge(['class' => 'rounded-box border border-base-content/10 bg-base-100 p-4']) }}>
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0 space-y-1">
            @if($title)
                <p class="text-sm text-base-content/60">{{ $title }}</p>
            @endif

            <p class="text-2xl font-semibold {{ $color }}">{{ $value }}</p>

            @if($description)
                <p class="text-xs text-base-content/60">{{ $description }}</p>
            @endif
        </div>

        @if($icon)
            <span aria-hidden="true" class="inline-flex size-8 shrink-0 items-center justify-center rounded-full bg-base-200"></span>
        @endif
    </div>
</div>