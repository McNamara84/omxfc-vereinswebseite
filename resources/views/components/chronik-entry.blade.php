@props([
    'date',
    'avif' => null,
    'webp' => null,
    'alt' => null,
])

<article x-data class="relative rounded-[2rem] border border-base-content/10 bg-base-100/72 p-5 shadow-sm shadow-base-content/5 sm:p-6">
    <span class="absolute -left-9 top-6 inline-flex h-4 w-4 rounded-full border-4 border-base-100 bg-primary shadow-sm"></span>

    <div class="space-y-3">
        <time class="text-sm font-semibold uppercase tracking-[0.18em] text-primary/80 sm:text-base">{{ $date }}</time>

        <div class="space-y-3 text-sm leading-relaxed text-base-content/78 sm:text-base">
            {{ $slot }}
        </div>

        @if($webp && $alt)
            <a
                href="#"
                class="group mt-2 block max-w-xl overflow-hidden rounded-[1.5rem] border border-base-content/10 bg-base-100 shadow-md transition hover:-translate-y-0.5 hover:shadow-lg"
                data-lightbox-payload='@json(["avif" => $avif, "webp" => $webp, "alt" => $alt])'
                @click.prevent='$dispatch("chronik-lightbox", JSON.parse($el.dataset.lightboxPayload))'
            >
                <picture>
                    @if($avif)
                        <source type="image/avif" srcset="{{ $avif }}">
                    @endif
                    <source type="image/webp" srcset="{{ $webp }}">
                    <img loading="lazy" src="{{ $webp }}" class="w-full object-cover transition duration-300 group-hover:scale-[1.02]" alt="{{ $alt }}">
                </picture>
            </a>
        @endif
    </div>
</article>