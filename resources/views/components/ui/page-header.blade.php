@props([
    'eyebrow' => null,
    'title',
    'description' => null,
    'density' => 'compact',
])

<section {{ $attributes->class(['omxfc-page-header relative overflow-hidden rounded-2xl border border-base-content/10 bg-base-100', 'p-4 sm:p-6' => $density === 'compact', 'px-6 py-8 sm:px-8 sm:py-10' => $density !== 'compact']) }}>
    <div class="absolute inset-x-0 top-0 h-1 bg-primary"></div>

    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div class="min-w-0 max-w-3xl space-y-2">
            @if($eyebrow)
                <p class="text-xs font-semibold text-primary">{{ $eyebrow }}</p>
            @endif

            <h1 class="break-words font-display text-2xl font-semibold tracking-tight text-base-content sm:text-3xl">{{ $title }}</h1>

            @if($description)
                <p class="text-sm leading-relaxed text-base-content/72 sm:text-base">{{ $description }}</p>
            @endif
        </div>

        @isset($actions)
            <div class="flex flex-col items-start gap-3 lg:items-end">
                {{ $actions }}
            </div>
        @endisset
    </div>
</section>
