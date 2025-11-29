<footer class="bg-gray-900 text-white py-4">
    <div class="container mx-auto px-4 text-center">
        <div class="mb-2">
            <a href="{{ route('fantreffen.2026') }}" class="text-yellow-400 hover:text-yellow-300 font-semibold">
                🎉 Fantreffen 2026 – Jetzt anmelden!
            </a>
        </div>
        <a href="{{ route('impressum') }}" class="hover:text-gray-300">Impressum</a> |
        <a href="{{ route('datenschutz') }}" class="hover:text-gray-300">Datenschutz</a>
        <p class="mt-2">© OMXFC e.V. {{ date('Y') }} | Version {{ $appVersion }} | <a href="{{ route('changelog') }}" class="hover:text-gray-300">Changelog</a></p>
    </div>
</footer>