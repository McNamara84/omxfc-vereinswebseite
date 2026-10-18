@props([
    'eyebrow' => null,
    'title' => null,
    'description' => null,
    'density' => 'compact',
])

<section {{ $attributes->class(['omxfc-panel relative min-w-0 rounded-2xl border border-base-content/10 bg-base-100', 'p-4 sm:p-5' => $density === 'compact', 'p-6' => $density !== 'compact']) }}>
    <div class="absolute inset-x-0 top-0 h-px bg-linear-to-r from-primary/35 via-accent/25 to-transparent"></div>

    @if(isset($header) || $title || $description || $eyebrow)
        <div class="mb-4 flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
            @isset($header)
                <div class="flex-1">
                    {{ $header }}
                </div>
            @else
                <div class="space-y-2">
                    @if($eyebrow)
                        <p class="text-[0.68rem] font-semibold uppercase tracking-[0.24em] text-base-content/75">{{ $eyebrow }}</p>
                    @endif

                    @if($title)
                        <h2 class="font-display text-xl font-semibold tracking-tight text-base-content">{{ $title }}</h2>
                    @endif

                    @if($description)
                        <p class="max-w-3xl text-sm leading-relaxed text-base-content/72">{{ $description }}</p>
                    @endif
                </div>
            @endisset

            @isset($actions)
                <div class="shrink-0">
                    {{ $actions }}
                </div>
            @endisset
        </div>
    @endif

    {{ $slot }}
</section>
