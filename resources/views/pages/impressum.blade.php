<x-app-layout title="Impressum – Offizieller MADDRAX Fanclub e. V." description="Verantwortliche Ansprechpartner, Kontakt und Vereinsregistereintrag gemäß §5 TMG.">
    <x-public-page class="space-y-8">
        <x-ui.page-header
            eyebrow="Rechtlich verantwortlich"
            title="Impressum"
            description="Hier findest du die offiziellen Kontaktdaten, den Vereinsregistereintrag und den Geltungsbereich für die Online-Angebote des OMXFC."
        >
            <x-slot:actions>
                <div class="flex flex-wrap gap-2">
                    <span class="badge badge-primary badge-outline rounded-full px-3 py-3">maddrax-fanclub.de</span>
                    <span class="badge badge-outline rounded-full px-3 py-3">Angaben gemäß §5 TMG</span>
                    <span class="badge badge-outline rounded-full px-3 py-3">Vereinsregister</span>
                </div>
            </x-slot:actions>
        </x-ui.page-header>

        <section class="grid gap-8 xl:grid-cols-[minmax(0,1fr)_minmax(19rem,0.8fr)] xl:items-start">
            <div class="space-y-6">
                <x-ui.panel title="Geltungsbereich" description="Dieses Impressum umfasst die Hauptdomain und alle zugehörigen Unterseiten des Vereinsauftritts.">
                    <p>Dieses Impressum gilt für alle Angebote unter der Domain <strong>maddrax-fanclub.de</strong> inklusive aller Subdomains (Unterseiten).</p>
                </x-ui.panel>

                <x-ui.panel title="Soziale Medien" description="Die Impressumspflicht gilt ebenso für unsere externen Profile und Community-Auftritte.">
                    <ul class="grid gap-3 sm:grid-cols-3">
                        <li class="rounded-[1.25rem] border border-base-content/10 bg-base-100/72 px-4 py-3">
                            <a href="https://www.facebook.com/mxikon" target="_blank" rel="noopener noreferrer" class="link link-primary font-medium">Facebook</a>
                        </li>
                        <li class="rounded-[1.25rem] border border-base-content/10 bg-base-100/72 px-4 py-3">
                            <a href="https://www.instagram.com/offizieller_maddrax_fanclub/" target="_blank" rel="noopener noreferrer" class="link link-primary font-medium">Instagram</a>
                        </li>
                        <li class="rounded-[1.25rem] border border-base-content/10 bg-base-100/72 px-4 py-3">
                            <a href="https://www.youtube.com/@mxikon" target="_blank" rel="noopener noreferrer" class="link link-primary font-medium">YouTube</a>
                        </li>
                    </ul>
                </x-ui.panel>

                <x-ui.panel title="Eintragung" description="Die Angaben zum Registereintrag bleiben hier transparent und schnell auffindbar.">
                    <p>Register: Vereinsregister<br>
                        Registernummer: 9677</p>
                </x-ui.panel>
            </div>

            <div class="space-y-6 xl:sticky xl:top-6">
                <x-ui.panel title="Angaben gemäß §5 TMG" description="Verantwortlich für den Vereinsauftritt ist der offiziell vertretungsberechtigte Vorstand.">
                    <div class="space-y-4 text-sm leading-relaxed sm:text-base">
                        <div>
                            <p class="font-semibold">Offizieller MADDRAX Fanclub e. V.</p>
                            <p class="mt-2">Vertretungsberechtigter:</p>
                            <p class="mt-1">1. Vorsitzende<br>
                                Tatjana Antipanova<br>
                                Guido-Seeber-Weg 12<br>
                                14480 Potsdam<br>
                                Deutschland</p>
                        </div>

                        <div class="rounded-[1.25rem] border border-base-content/10 bg-base-100/72 px-4 py-3">
                            <p>E-Mail: <a href="mailto:vorstand@maddrax-fanclub.de" class="link link-primary">vorstand@maddrax-fanclub.de</a></p>
                            <p class="mt-2">Telefon: <a href="tel:+491794218330" class="link link-primary">+49 179 4218330</a></p>
                        </div>
                    </div>
                </x-ui.panel>

                <x-ui.panel title="Direkter Kontakt" description="Für rechtliche Rückfragen oder formale Anliegen ist der Vorstand der richtige Einstieg.">
                    <div class="flex flex-col gap-3">
                        <a href="mailto:vorstand@maddrax-fanclub.de" class="btn btn-primary rounded-full">E-Mail schreiben</a>
                        <a href="{{ route('datenschutz') }}" wire:navigate class="btn btn-ghost rounded-full bg-base-100/75">Zum Datenschutz</a>
                    </div>
                </x-ui.panel>
            </div>
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
