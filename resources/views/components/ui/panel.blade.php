@props([
    'eyebrow' => null,
    'title' => null,
    'description' => null,
])

<section {{ $attributes->merge(['class' => 'relative overflow-hidden rounded-[2rem] border border-base-content/10 bg-base-100/90 p-6 shadow-xl shadow-base-content/5 backdrop-blur']) }}>
    <div class="absolute inset-x-0 top-0 h-px bg-linear-to-r from-primary/35 via-accent/25 to-transparent"></div>

    @if(isset($header) || $title || $description || $eyebrow)
        <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            @isset($header)
                <div class="flex-1">
                    {{ $header }}
                </div>
            @else
                <div class="space-y-2">
                    @if($eyebrow)
                        <p class="text-[0.68rem] font-semibold uppercase tracking-[0.24em] text-base-content/45">{{ $eyebrow }}</p>
                    @endif

                    @if($title)
                        <h2 class="font-display text-2xl font-semibold tracking-tight text-base-content">{{ $title }}</h2>
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