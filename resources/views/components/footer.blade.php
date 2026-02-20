<footer class="bg-neutral text-neutral-content py-4">
    <div class="container mx-auto px-4 text-center">
        <div class="mb-2">
            <a href="{{ route('fantreffen.2026') }}" class="btn btn-warning btn-xs font-semibold">
                🎉 Fantreffen 2026 – Jetzt anmelden!
            </a>
        </div>
        <a href="{{ route('impressum') }}" class="link link-neutral-content">Impressum</a> |
        <a href="{{ route('datenschutz') }}" class="link link-neutral-content">Datenschutz</a>
        <p class="mt-2">© OMXFC e.V. {{ date('Y') }} | Version {{ $appVersion }} | <a href="{{ route('changelog') }}" class="link link-neutral-content">Changelog</a></p>
    </div>
</footer>