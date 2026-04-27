@props([
    'eyebrow' => null,
    'title',
    'description' => null,
])

<section {{ $attributes->merge(['class' => 'relative overflow-hidden rounded-[2rem] border border-base-content/10 bg-base-100/90 px-6 py-6 shadow-xl shadow-base-content/5 backdrop-blur sm:px-8 sm:py-8']) }}>
    <div class="absolute inset-x-0 top-0 h-1 bg-primary"></div>

    <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
        <div class="max-w-3xl space-y-3">
            @if($eyebrow)
                <p class="text-[0.7rem] font-semibold uppercase tracking-[0.28em] text-base-content/45">{{ $eyebrow }}</p>
            @endif

            <h1 class="font-display text-3xl font-semibold tracking-tight text-base-content sm:text-4xl">{{ $title }}</h1>

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