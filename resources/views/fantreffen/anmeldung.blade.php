<x-app-layout
    :title="'Maddrax-Fantreffen 2026 – Offizieller MADDRAX Fanclub e. V.'"
    :description="'Melde dich jetzt an zum Maddrax-Fantreffen am 9. Mai 2026 in Köln mit Signierstunde und Verleihung der Goldenen Taratze.'"
    :socialImage="asset('build/assets/omxfc-logo-Df-1StAj.png')">

{{-- Strukturierte Daten für Google Rich Results --}}
<x-slot name="head">
<script type="application/ld+json">
{
    "@@context": "https://schema.org",
    "@@type": "Event",
    "name": "Maddrax-Fantreffen 2026",
    "description": "Das jährliche Fantreffen des Offiziellen MADDRAX Fanclub e. V. mit Signierstunde und Verleihung der Goldenen Taratze.",
    "startDate": "2026-05-09T19:00:00+02:00",
    "endDate": "2026-05-09T23:00:00+02:00",
    "eventStatus": "https://schema.org/EventScheduled",
    "eventAttendanceMode": "https://schema.org/OfflineEventAttendanceMode",
    "location": {
        "@@type": "Place",
        "name": "L'Osteria Köln Mülheim",
        "address": {
            "@@type": "PostalAddress",
            "streetAddress": "Düsseldorfer Str. 1-3",
            "addressLocality": "Köln",
            "postalCode": "51063",
            "addressCountry": "DE"
        }
    },
    "organizer": {
        "@@type": "Organization",
        "name": "Offizieller MADDRAX Fanclub e. V.",
        "url": "{{ config('app.url') }}"
    },
    "offers": [
        {
            "@@type": "Offer",
            "name": "Vereinsmitglieder",
            "price": "0",
            "priceCurrency": "EUR",
            "availability": "https://schema.org/InStock",
            "validFrom": "2025-01-01",
            "url": "{{ route('fantreffen.2026') }}"
        },
        {
            "@@type": "Offer",
            "name": "Gäste",
            "price": "5.00",
            "priceCurrency": "EUR",
            "availability": "https://schema.org/InStock",
            "validFrom": "2025-01-01",
            "url": "{{ route('fantreffen.2026') }}"
        }
    ],
    "image": "{{ asset('build/assets/omxfc-logo-Df-1StAj.png') }}"
}
</script>

{{-- Breadcrumb-Schema für bessere Darstellung in Suchergebnissen --}}
<script type="application/ld+json">
{
    "@@context": "https://schema.org",
    "@@type": "BreadcrumbList",
    "itemListElement": [
        {
            "@@type": "ListItem",
            "position": 1,
            "name": "Startseite",
            "item": "{{ config('app.url') }}"
        },
        {
            "@@type": "ListItem",
            "position": 2,
            "name": "Veranstaltungen",
            "item": "{{ config('app.url') }}/termine"
        },
        {
            "@@type": "ListItem",
            "position": 3,
            "name": "Maddrax-Fantreffen 2026",
            "item": "{{ route('fantreffen.2026') }}"
        }
    ]
}
</script>

{{-- FAQ-Schema für mögliche Rich Snippets --}}
<script type="application/ld+json">
{
    "@@context": "https://schema.org",
    "@@type": "FAQPage",
    "mainEntity": [
        {
            "@@type": "Question",
            "name": "Wann findet das Maddrax-Fantreffen 2026 statt?",
            "acceptedAnswer": {
                "@@type": "Answer",
                "text": "Das Maddrax-Fantreffen 2026 findet am Samstag, 9. Mai 2026 ab 19:00 Uhr statt."
            }
        },
        {
            "@@type": "Question",
            "name": "Wo findet das Fantreffen statt?",
            "acceptedAnswer": {
                "@@type": "Answer",
                "text": "Das Fantreffen findet in der L'Osteria Köln Mülheim statt (Düsseldorfer Str. 1-3, 51063 Köln)."
            }
        },
        {
            "@@type": "Question",
            "name": "Was kostet die Teilnahme am Fantreffen?",
            "acceptedAnswer": {
                "@@type": "Answer",
                "text": "Für Vereinsmitglieder ist die Teilnahme kostenlos. Gäste werden um eine Spende von 5 € gebeten. Optional kann ein Event-T-Shirt für 25 € bestellt werden."
            }
        },
        {
            "@@type": "Question",
            "name": "Muss ich Vereinsmitglied sein um teilzunehmen?",
            "acceptedAnswer": {
                "@@type": "Answer",
                "text": "Nein, auch Gäste sind herzlich willkommen! Du kannst dich als Gast anmelden oder vorher Mitglied werden, um kostenlos teilzunehmen."
            }
        },
        {
            "@@type": "Question",
            "name": "Gibt es eine Signierstunde mit MADDRAX-Autoren?",
            "acceptedAnswer": {
                "@@type": "Answer",
                "text": "Ja! Ab 19:00 Uhr gibt es eine Signierstunde, bei der du deine Lieblingsautoren treffen kannst."
            }
        },
        {
            "@@type": "Question",
            "name": "Was ist die Goldene Taratze?",
            "acceptedAnswer": {
                "@@type": "Answer",
                "text": "Die Goldene Taratze ist ein Fan-Preis, der jährlich beim Fantreffen an besondere Personen oder Projekte aus der MADDRAX-Community verliehen wird."
            }
        }
    ]
}
</script>
</x-slot>

<div class="bg-base-200 -mt-8">
    {{-- Hero-Banner --}}
    <div class="relative bg-gradient-to-br from-[#8B0116] to-[#6b000e] text-white py-12 sm:py-16">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h1 class="text-5xl font-bold mb-6">Maddrax-Fantreffen 2026 in Köln</h1>
            <div class="flex flex-col sm:flex-row justify-center gap-6 text-lg mb-6">
                <span>📅 Samstag, 9. Mai 2026</span>
                <span>🕖 ab 19:00 Uhr</span>
                <span>📍 L´Osteria Köln Mülheim</span>
            </div>
            <x-button label="📍 Route in Google Maps" link="https://maps.app.goo.gl/dzLHUqVHqJrkWDkr5" external class="btn-outline border-white text-white hover:bg-white hover:text-[#8B0116]" />
        </div>
    </div>

    {{-- VIP Authors Banner - Prominent Placement --}}
    @if ($vipAuthors->isNotEmpty())
        <div class="bg-gradient-to-r from-amber-400 via-yellow-400 to-amber-400 dark:from-amber-600 dark:via-yellow-500 dark:to-amber-600 py-6 shadow-lg" role="region" aria-labelledby="vip-authors-heading">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex flex-col md:flex-row items-center justify-center gap-4 text-center">
                    <div class="flex items-center gap-3">
                        <x-icon name="o-star" class="w-10 h-10 text-amber-800 dark:text-amber-900 flex-shrink-0" />
                        <div>
                            <h2 id="vip-authors-heading" class="text-xl md:text-2xl font-bold text-amber-900 dark:text-amber-950">
                                VIP-Autoren bestätigt!
                            </h2>
                            <p class="text-amber-800 dark:text-amber-900 font-medium mt-1">
                                Triff die Autoren der MADDRAX-Serie persönlich:
                            </p>
                        </div>
                    </div>
                    <div class="flex flex-wrap justify-center gap-2 md:gap-3">
                        @foreach ($vipAuthors as $author)
                            <x-badge :value="$author->display_name . ($author->is_tentative ? ' (unter Vorbehalt)' : '')" class="badge-lg bg-white/90 dark:bg-gray-900/80 text-amber-900 dark:text-amber-300 font-semibold shadow-md" />
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    @endif

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
        @if(session('success'))
            <x-alert icon="o-check-circle" class="alert-success mb-4" dismissible>
                {{ session('success') }}
            </x-alert>
        @endif

        <x-alert icon="o-information-circle" class="alert-warning mb-4">
            <h3 class="font-bold mb-2">ColoniaCon am selben Wochenende!</h3>
            <p class="mb-2">Am selben Wochenende findet auch die <a href="https://www.coloniacon-tng.de/2026" target="_blank" rel="noopener noreferrer" class="underline font-semibold hover:opacity-80">ColoniaCon</a> statt. Auf der ColoniaCon erwartet euch am Samstag um 14:00 Uhr ein großes Maddrax-Panel mit zahlreichen Autor*innen:</p>
            <p class="mb-2 font-semibold">Michael Schönenbröcher, Christin Schwarz, Michael Edelbrock, Tanja Guth, Thomas Ziebula, Ansgar Back (unter Vorbehalt), Claudia Kern (unter Vorbehalt), Michael Markus Turner (unter Vorbehalt), Wolfgang Hohlbein (unter Vorbehalt), Susan Schwarz (unter Vorbehalt).</p>
            <p class="mb-2">Außerdem gibt es am Sonntag um 10:40 Uhr eine spannende Vorstellung des OMXFC und des Maddraxikons – also unbedingt vorbeischauen!</p>
            <p>Und das Beste: Vom Veranstaltungsort der ColoniaCon sind es nur fünf Minuten zu Fuß bis zum Fantreffen – ideal, um das ganze Wochenende in Maddrax-Stimmung zu verbringen!</p>
        </x-alert>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <div class="lg:col-span-2 space-y-8">
                {{-- Programm --}}
                <x-card shadow>
                    <x-slot:title>
                        <span class="text-2xl text-primary">Programm</span>
                    </x-slot:title>
                    <div class="space-y-4">
                        <div class="flex gap-4">
                            <span class="font-bold text-primary">19:00</span>
                            <div>
                                <h3 class="font-semibold">Signierstunde mit Autoren</h3>
                                @if ($vipAuthors->isNotEmpty())
                                    <p class="text-base-content/70">
                                        Mit dabei:
                                        @foreach ($vipAuthors as $author)
                                            <span class="font-medium text-primary">
                                                {{ $author->display_name }}@if ($author->is_tentative) <span class="text-xs font-semibold">(unter Vorbehalt)</span>@endif
                                            </span>@if (!$loop->last), @endif
                                        @endforeach
                                    </p>
                                    @if ($vipAuthors->contains('is_tentative', true))
                                        <p class="text-base-content/70 mt-2">
                                            Einige Autor:innen haben ihre Teilnahme bereits zugesagt, andere sind noch angefragt oder haben nur vorläufig zugesagt. Bitte beachtet, dass sich die Gästeliste kurzfristig ändern kann.
                                        </p>
                                    @endif
                                @else
                                    <p class="text-base-content/70">Triff deine Lieblingsautoren!</p>
                                @endif
                            </div>
                        </div>
                        <div class="flex gap-4">
                            <span class="font-bold text-primary">20:00</span>
                            <div>
                                <h3 class="font-semibold">Verleihung Goldene Taratze</h3>
                                <p class="text-base-content/70">Die große Preisverleihung!</p>
                            </div>
                        </div>
                    </div>
                </x-card>

                {{-- Kosten --}}
                <x-card shadow>
                    <x-slot:title>
                        <span class="text-2xl text-primary">Kosten</span>
                    </x-slot:title>
                    <div class="space-y-3">
                        <div class="p-3 bg-success/10 rounded-box">
                            <div class="font-semibold text-base-content mb-1">Vereinsmitglieder</div>
                            <p class="text-sm text-base-content/70">Teilnahme am Event: <strong class="text-success">kostenlos</strong></p>
                        </div>
                        <div class="p-3 bg-info/10 rounded-box">
                            <div class="font-semibold text-base-content mb-1">Gäste</div>
                            <p class="text-sm text-base-content/70">Teilnahme am Event: <strong class="text-info">5,00 €</strong> Spende erbeten</p>
                        </div>
                        <div class="p-3 bg-secondary/10 rounded-box">
                            <div class="font-semibold text-base-content mb-1">Event-T-Shirt (optional)</div>
                            <p class="text-sm text-base-content/70">
                                <strong class="text-secondary">25,00 €</strong> Spende
                            </p>
                            <p class="text-xs text-base-content/50 mt-1 italic">
                                Für Gäste zusammen mit Teilnahme: 30,00 €
                            </p>
                            <x-fantreffen-tshirt-deadline-notice
                                :tshirtDeadlinePassed="$tshirtDeadlinePassed"
                                :tshirtDeadlineFormatted="$tshirtDeadlineFormatted"
                                :daysUntilDeadline="$daysUntilDeadline"
                                variant="compact"
                            />
                        </div>
                    </div>
                </x-card>

                {{-- FAQ-Sektion --}}
                <x-card shadow>
                    <x-slot:title>
                        <span class="text-2xl text-primary">Häufige Fragen</span>
                    </x-slot:title>
                    <div class="space-y-1">
                        <x-collapse name="faq-group" class="collapse-arrow border border-base-content/10 rounded-box">
                            <x-slot:heading>Muss ich Vereinsmitglied sein?</x-slot:heading>
                            <x-slot:content>
                                <p class="text-sm text-base-content/70">Nein! Auch Gäste sind herzlich willkommen. Als Mitglied ist die Teilnahme allerdings kostenlos.</p>
                            </x-slot:content>
                        </x-collapse>
                        <x-collapse name="faq-group" class="collapse-arrow border border-base-content/10 rounded-box">
                            <x-slot:heading>Was ist die Goldene Taratze?</x-slot:heading>
                            <x-slot:content>
                                <p class="text-sm text-base-content/70">Ein Fan-Preis, der jährlich an besondere Personen oder Projekte aus der MADDRAX-Community verliehen wird.</p>
                            </x-slot:content>
                        </x-collapse>
                        <x-collapse name="faq-group" class="collapse-arrow border border-base-content/10 rounded-box">
                            <x-slot:heading>Kann ich ein T-Shirt bestellen?</x-slot:heading>
                            <x-slot:content>
                                <p class="text-sm text-base-content/70">Ja! Für 25 € Spende (Gäste: 30 € inkl. Teilnahme) kannst du ein exklusives Event-T-Shirt bestellen.</p>
                            </x-slot:content>
                        </x-collapse>
                        <x-collapse name="faq-group" class="collapse-arrow border border-base-content/10 rounded-box">
                            <x-slot:heading>Gibt es eine Signierstunde?</x-slot:heading>
                            <x-slot:content>
                                <p class="text-sm text-base-content/70">Ja! Ab 19:00 Uhr kannst du deine Lieblingsautoren treffen und dir Bücher signieren lassen.</p>
                            </x-slot:content>
                        </x-collapse>
                    </div>
                </x-card>
            </div>

            {{-- Anmeldeformular (rechte Spalte) --}}
            <div class="lg:col-span-1">
                <div class="bg-base-100 rounded-xl shadow-lg overflow-hidden sticky top-4">
                    <div class="bg-gradient-to-r from-[#8B0116] to-[#a01526] px-6 py-4">
                        <h2 class="text-2xl font-bold text-white">Anmeldung</h2>
                    </div>
                    <div class="p-6">
                        @if(isset($errors) && $errors->any())
                            <x-alert icon="o-exclamation-triangle" class="alert-error mb-4">
                                <ul class="text-sm space-y-1">
                                    @foreach($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </x-alert>
                        @endif

                        @if(!Auth::check())
                            <x-alert icon="o-information-circle" class="alert-warning mb-4">
                                <p class="text-sm">Bist du Vereinsmitglied? <a href="{{ route('login') }}" class="underline font-bold link">Jetzt einloggen</a> um kostenlos teilzunehmen!</p>
                            </x-alert>
                        @endif

                        <form method="POST" action="{{ route('fantreffen.2026.store') }}" id="fantreffen-form" class="space-y-4">
                            @csrf

                            @guest
                                <x-input label="Vorname *" name="vorname" :value="old('vorname')" required data-testid="fantreffen-vorname" />
                                <x-input label="Nachname *" name="nachname" :value="old('nachname')" required data-testid="fantreffen-nachname" />
                                <x-input label="E-Mail *" type="email" name="email" :value="old('email')" required data-testid="fantreffen-email" />
                            @else
                                <x-alert icon="o-check-circle" class="alert-success">
                                    <p class="text-sm">Angemeldet als <strong>{{ $user->vorname }} {{ $user->nachname }}</strong></p>
                                    <p class="text-sm mt-1">Deine Teilnahme ist <strong>kostenlos</strong>!</p>
                                </x-alert>
                            @endguest

                            <div>
                                <x-input label="Mobile Rufnummer (optional)" type="tel" name="mobile" :value="old('mobile', optional($user)->mobile ?? '')" placeholder="+49 123 456789" hint="Für WhatsApp-Updates" data-testid="fantreffen-mobile" />
                            </div>

                            @if(!$tshirtDeadlinePassed)
                                <div class="border-t border-base-content/10 pt-4">
                                    {{-- Prominenter Hinweis zur Bestellfrist --}}
                                    <x-fantreffen-tshirt-deadline-notice
                                        :tshirtDeadlinePassed="$tshirtDeadlinePassed"
                                        :tshirtDeadlineFormatted="$tshirtDeadlineFormatted"
                                        :daysUntilDeadline="$daysUntilDeadline"
                                        variant="prominent"
                                    />
                                    <x-checkbox label="Event-T-Shirt bestellen" id="tshirt_bestellt" name="tshirt_bestellt" value="1" :checked="old('tshirt_bestellt')" />
                                    <p class="text-xs text-base-content/50 mt-1 ml-8">25,00 € Spende{{ !Auth::check() ? ' (zusammen mit Teilnahme: 30,00 €)' : '' }}</p>

                                    <div id="tshirt-groesse-container" class="mt-3 hidden">
                                        @php
                                            $tshirtGroessen = collect(['XS', 'S', 'M', 'L', 'XL', 'XXL', 'XXXL'])
                                                ->map(fn($s) => ['id' => $s, 'name' => $s])
                                                ->toArray();
                                        @endphp
                                        <x-form-select label="T-Shirt-Größe *" id="tshirt_groesse" name="tshirt_groesse" :options="$tshirtGroessen" placeholder="Bitte wählen..." :value="old('tshirt_groesse')" data-testid="fantreffen-tshirt-groesse" required />
                                    </div>
                                </div>
                            @endif

                            <x-button
                                type="submit"
                                :label="$paymentAmount > 0 ? 'Weiter zur Zahlung (' . number_format($paymentAmount, 2, ',', '.') . ' €)' : 'Jetzt anmelden'"
                                class="btn-primary w-full"
                                data-testid="fantreffen-submit"
                            />
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@vite(['resources/js/fantreffen.js'])

</x-app-layout>
