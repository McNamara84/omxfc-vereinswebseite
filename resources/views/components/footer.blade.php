<footer class="bg-neutral text-neutral-content py-4">
    <div class="container mx-auto px-4 text-center">
        <div class="mb-2">
            <a href="{{ route('fantreffen.2026') }}" class="link font-semibold no-underline hover:underline">
                🎉 Fantreffen 2026 – Jetzt anmelden!
            </a>
        </div>
        <a href="{{ route('impressum') }}" class="link link-hover">Impressum</a> |
        <a href="{{ route('datenschutz') }}" class="link link-hover">Datenschutz</a>
        <p class="mt-2">© OMXFC e.V. {{ date('Y') }} | Version {{ $appVersion }} | <a href="{{ route('changelog') }}" class="link link-hover">Changelog</a></p>
    </div>
</footer>