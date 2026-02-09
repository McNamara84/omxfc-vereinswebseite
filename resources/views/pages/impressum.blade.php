<x-app-layout title="Impressum – Offizieller MADDRAX Fanclub e. V." description="Verantwortliche Ansprechpartner, Kontakt und Vereinsregistereintrag gemäß §5 TMG.">
    <x-public-page>
        <h1 class="text-2xl sm:text-3xl font-bold text-primary mb-4 sm:mb-8">Impressum</h1>

        <p class="mb-6">Dieses Impressum gilt für alle Angebote unter der Domain <strong>maddrax-fanclub.de</strong>
            inklusive aller Subdomains (Unterseiten).</p>

        <section class="mb-6">
            <h2 class="text-xl font-semibold mb-2">Soziale Medien</h2>
            <p>Dieses Impressum gilt auch für unsere Auftritte in den folgenden sozialen Medien:</p>
            <ul class="list-disc ml-6 mt-2">
                <li><a href="https://www.facebook.com/mxikon" target="_blank"
                        class="link link-primary">Facebook</a></li>
                <li><a href="https://www.instagram.com/offizieller_maddrax_fanclub/" target="_blank"
                        class="link link-primary">Instagram</a></li>
                <li><a href="https://www.youtube.com/@mxikon" target="_blank"
                        class="link link-primary">YouTube</a></li>
            </ul>
        </section>

        <section class="mb-6">
            <h2 class="text-xl font-semibold mb-2">Angaben gemäß §5 TMG</h2>
            <p class="font-semibold">Offizieller MADDRAX Fanclub e. V.</p>
            <p class="mt-2">Vertretungsberechtigter:</p>
            <p class="mt-1">1. Vorsitzende<br>
                Tatjana Antipanova<br>
                Guido-Seeber-Weg 12<br>
                14480 Potsdam<br>
                Deutschland</p>

            <p class="mt-4">E-Mail: <a href="mailto:vorstand@maddrax-fanclub.de"
                    class="link link-primary">vorstand@maddrax-fanclub.de</a><br>
                Telefon: <a href="tel:+491794218330" class="link link-primary">+49 179 4218330</a></p>
        </section>

        <section>
            <h2 class="text-xl font-semibold mb-2">Eintragung</h2>
            <p>Register: Vereinsregister<br>
                Registernummer: 9677</p>
        </section>

        @php
            $organizationStructuredData = [
                '@context' => 'https://schema.org',
                '@type' => 'Organization',
                'name' => 'Offizieller MADDRAX Fanclub e. V.',
                'legalName' => 'Offizieller MADDRAX Fanclub e. V.',
                'foundingDate' => '2023-05-20',
                'url' => 'https://www.maddrax-fanclub.de',
                'address' => [
                    '@type' => 'PostalAddress',
                    'streetAddress' => 'Guido-Seeber-Weg 12',
                    'postalCode' => '14480',
                    'addressLocality' => 'Potsdam',
                    'addressCountry' => 'DE',
                ],
                'contactPoint' => [
                    '@type' => 'ContactPoint',
                    'telephone' => '+49 179 4218330',
                    'email' => 'vorstand@maddrax-fanclub.de',
                    'contactType' => 'customer service',
                ],
                'sameAs' => [
                    'https://www.facebook.com/mxikon',
                    'https://www.instagram.com/offizieller_maddrax_fanclub/',
                    'https://www.youtube.com/@mxikon',
                ],
            ];
        @endphp
        <script type="application/ld+json">
            @json($organizationStructuredData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        </script>
    </x-public-page>
</x-app-layout>
