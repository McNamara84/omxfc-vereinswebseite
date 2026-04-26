<footer class="border-t border-base-content/10 bg-neutral text-neutral-content">
    <div class="mx-auto flex max-w-[88rem] flex-col gap-4 px-4 py-6 sm:px-6 lg:flex-row lg:items-center lg:justify-between lg:px-8">
        <div class="space-y-3">
            <a href="{{ route('fantreffen.2026') }}" wire:navigate class="btn btn-warning btn-sm rounded-full font-semibold">
                Fantreffen 2026 entdecken
            </a>

            <p class="text-sm text-neutral-content/75">
                OMXFC e.V. {{ date('Y') }} · Version {{ $appVersion }} · Die Community-Plattform für Projekte, Vereinsleben und MADDRAX-Fandom.
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-3 text-sm">
            <a href="{{ route('impressum') }}" class="link link-hover">Impressum</a>
            <a href="{{ route('datenschutz') }}" class="link link-hover">Datenschutz</a>
            <a href="{{ route('changelog') }}" class="link link-hover">Changelog</a>
        </div>
    </div>
</footer>