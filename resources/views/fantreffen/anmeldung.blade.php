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
    <div class="relative bg-primary text-primary-content py-12 sm:py-16">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h1 class="text-5xl font-bold mb-6">Maddrax-Fantreffen 2026 in Köln</h1>
            <div class="flex flex-col sm:flex-row justify-center gap-6 text-lg mb-6">
                <span>📅 Samstag, 9. Mai 2026</span>
                <span>🕖 ab 19:00 Uhr</span>
                <span>📍 L´Osteria Köln Mülheim</span>
            </div>
            <a href="https://maps.app.goo.gl/dzLHUqVHqJrkWDkr5" target="_blank" class="inline-block px-6 py-3 bg-base-100 text-primary font-semibold rounded-lg hover:bg-base-200">📍 Route in Google Maps</a>
        </div>
    </div>

    {{-- VIP Authors Banner - Prominent Placement --}}
    @if ($vipAuthors->isNotEmpty())
        <div class="bg-warning py-6 shadow-lg" role="region" aria-labelledby="vip-authors-heading">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex flex-col md:flex-row items-center justify-center gap-4 text-center">
                    <div class="flex items-center gap-3">
                        <svg class="w-10 h-10 text-warning-content flex-shrink-0" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                        </svg>
                        <div>
                            <h2 id="vip-authors-heading" class="text-xl md:text-2xl font-bold text-warning-content">
                                VIP-Autoren bestätigt!
                            </h2>
                            <p class="text-warning-content font-medium mt-1">
                                Triff die Autoren der MADDRAX-Serie persönlich:
                            </p>
                        </div>
                    </div>
                    <div class="flex flex-wrap justify-center gap-2 md:gap-3">
                        @foreach ($vipAuthors as $author)
                            <span class="inline-flex items-center px-4 py-2 bg-base-100/90 text-warning-content font-semibold rounded-full shadow-md text-sm md:text-base">
                                <svg class="w-4 h-4 mr-2 text-amber-500" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                                </svg>
                                {{ $author->display_name }}
                                @if ($author->is_tentative)
                                    <span class="ml-2 text-xs font-semibold text-warning-content">(unter Vorbehalt)</span>
                                @endif
                            </span>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    @endif

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
        @if(session('success'))
            <div class="mb-4 p-4 bg-success/10 border-l-4 border-success rounded">
                <p class="text-success">{{ session('success') }}</p>
            </div>
        @endif
        <div class="mb-4 p-4 bg-warning/10 border-l-4 border-warning rounded">
            <h3 class="font-bold mb-2">ColoniaCon am selben Wochenende!</h3>
            <p class="mb-2">Am selben Wochenende findet auch die <a href="https://www.coloniacon-tng.de/2026" target="_blank" rel="noopener noreferrer" class="text-warning underline font-semibold hover:text-warning/80">ColoniaCon</a> statt. Auf der ColoniaCon erwartet euch am Samstag um 14:00 Uhr ein großes Maddrax-Panel mit zahlreichen Autor*innen:</p>
            <p class="mb-2 font-semibold">Michael Schönenbröcher, Christin Schwarz, Michael Edelbrock, Tanja Guth, Thomas Ziebula, Ansgar Back (unter Vorbehalt), Claudia Kern (unter Vorbehalt), Michael Markus Turner (unter Vorbehalt), Wolfgang Hohlbein (unter Vorbehalt), Susan Schwarz (unter Vorbehalt).</p>
            <p class="mb-2">Außerdem gibt es am Sonntag um 10:40 Uhr eine spannende Vorstellung des OMXFC und des Maddraxikons – also unbedingt vorbeischauen!</p>
            <p>Und das Beste: Vom Veranstaltungsort der ColoniaCon sind es nur fünf Minuten zu Fuß bis zum Fantreffen – ideal, um das ganze Wochenende in Maddrax-Stimmung zu verbringen!</p>
        </div>
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <div class="lg:col-span-2 space-y-8">
                <div class="bg-base-100 rounded-xl shadow-lg p-6">
                    <h2 class="text-2xl font-bold mb-4 text-primary">Programm</h2>
                    <div class="space-y-4">
                        <div class="flex gap-4">
                            <span class="font-bold text-primary">19:00</span>
                            <div>
                                <h3 class="font-semibold">Signierstunde mit Autoren</h3>
                                @if ($vipAuthors->isNotEmpty())
                                    <p class="text-base-content/60">
                                        Mit dabei:
                                        @foreach ($vipAuthors as $author)
                                            <span class="font-medium text-primary">
                                                {{ $author->display_name }}@if ($author->is_tentative) <span class="text-xs font-semibold">(unter Vorbehalt)</span>@endif
                                            </span>@if (!$loop->last), @endif
                                        @endforeach
                                    </p>
                                    @if ($vipAuthors->contains('is_tentative', true))
                                        <p class="text-base-content/60 mt-2">
                                            Einige Autor:innen haben ihre Teilnahme bereits zugesagt, andere sind noch angefragt oder haben nur vorläufig zugesagt. Bitte beachtet, dass sich die Gästeliste kurzfristig ändern kann.
                                        </p>
                                    @endif
                                @else
                                    <p class="text-base-content/60">Triff deine Lieblingsautoren!</p>
                                @endif
                            </div>
                        </div>
                        <div class="flex gap-4">
                            <span class="font-bold text-primary">20:00</span>
                            <div>
                                <h3 class="font-semibold">Verleihung Goldene Taratze</h3>
                                <p class="text-base-content/60">Die große Preisverleihung!</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-base-100 rounded-xl shadow-lg p-6">
                    <h2 class="text-2xl font-bold mb-4 text-primary">Kosten</h2>
                    <div class="space-y-3">
                        <div class="p-3 bg-success/10 rounded">
                            <div class="font-semibold text-base-content mb-1">Vereinsmitglieder</div>
                            <p class="text-sm text-base-content/70">Teilnahme am Event: <strong class="text-success">kostenlos</strong></p>
                        </div>
                        <div class="p-3 bg-info/10 rounded">
                            <div class="font-semibold text-base-content mb-1">Gäste</div>
                            <p class="text-sm text-base-content/70">Teilnahme am Event: <strong class="text-info">5,00 €</strong> Spende erbeten</p>
                        </div>
                        <div class="p-3 bg-secondary/10 rounded">
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
                </div>
                {{-- FAQ-Sektion --}}
                <div class="bg-base-100 rounded-xl shadow-lg p-6">
                    <h2 class="text-2xl font-bold mb-4 text-primary">Häufige Fragen</h2>
                    <div class="space-y-3" x-data="{ open: null }">
                        <div class="border-b border-base-content/20 pb-3">
                            <button @click="open = open === 1 ? null : 1" class="flex justify-between w-full text-left font-semibold text-base-content">
                                <span>Muss ich Vereinsmitglied sein?</span>
                                <span x-text="open === 1 ? '−' : '+'"></span>
                            </button>
                            <p x-show="open === 1" x-collapse class="mt-2 text-base-content/60 text-sm">
                                Nein! Auch Gäste sind herzlich willkommen. Als Mitglied ist die Teilnahme allerdings kostenlos.
                            </p>
                        </div>
                        <div class="border-b border-base-content/20 pb-3">
                            <button @click="open = open === 2 ? null : 2" class="flex justify-between w-full text-left font-semibold text-base-content">
                                <span>Was ist die Goldene Taratze?</span>
                                <span x-text="open === 2 ? '−' : '+'"></span>
                            </button>
                            <p x-show="open === 2" x-collapse class="mt-2 text-base-content/60 text-sm">
                                Ein Fan-Preis, der jährlich an besondere Personen oder Projekte aus der MADDRAX-Community verliehen wird.
                            </p>
                        </div>
                        <div class="border-b border-base-content/20 pb-3">
                            <button @click="open = open === 3 ? null : 3" class="flex justify-between w-full text-left font-semibold text-base-content">
                                <span>Kann ich ein T-Shirt bestellen?</span>
                                <span x-text="open === 3 ? '−' : '+'"></span>
                            </button>
                            <p x-show="open === 3" x-collapse class="mt-2 text-base-content/60 text-sm">
                                Ja! Für 25 € Spende (Gäste: 30 € inkl. Teilnahme) kannst du ein exklusives Event-T-Shirt bestellen.
                            </p>
                        </div>
                        <div class="pb-1">
                            <button @click="open = open === 4 ? null : 4" class="flex justify-between w-full text-left font-semibold text-base-content">
                                <span>Gibt es eine Signierstunde?</span>
                                <span x-text="open === 4 ? '−' : '+'"></span>
                            </button>
                            <p x-show="open === 4" x-collapse class="mt-2 text-base-content/60 text-sm">
                                Ja! Ab 19:00 Uhr kannst du deine Lieblingsautoren treffen und dir Bücher signieren lassen.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="lg:col-span-1">
                <div class="bg-base-100 rounded-xl shadow-lg overflow-hidden sticky top-4">
                    <div class="bg-primary px-6 py-4">
                        <h2 class="text-2xl font-bold text-white">Anmeldung</h2>
                    </div>
                    <div class="p-6">
                        @if(isset($errors) && $errors->any())
                            <div class="mb-4 p-4 bg-error/10 border-l-4 border-error rounded">
                                <ul class="text-sm text-error space-y-1">
                                    @foreach($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                        
                        @if(!Auth::check())
                            <div class="mb-4 p-3 bg-warning/10 rounded">
                                <p class="text-sm">Bist du Vereinsmitglied? <a href="{{ route('login') }}" class="underline font-bold">Jetzt einloggen</a> um kostenlos teilzunehmen!</p>
                            </div>
                        @endif

                        <form method="POST" action="{{ route('fantreffen.2026.store') }}" id="fantreffen-form" class="space-y-4">
                            @csrf

                            @guest
                                <div>
                                    <label class="block text-sm font-medium mb-2">Vorname *</label>
                                    <input type="text" name="vorname" value="{{ old('vorname') }}" class="w-full px-3 py-2 border rounded border-base-content/20" required>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium mb-2">Nachname *</label>
                                    <input type="text" name="nachname" value="{{ old('nachname') }}" class="w-full px-3 py-2 border rounded border-base-content/20" required>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium mb-2">E-Mail *</label>
                                    <input type="email" name="email" value="{{ old('email') }}" class="w-full px-3 py-2 border rounded border-base-content/20" required>
                                </div>
                            @else
                                <div class="p-4 bg-success/10 rounded">
                                    <p class="text-sm">✅ Angemeldet als <strong>{{ $user->vorname }} {{ $user->nachname }}</strong></p>
                                    <p class="text-sm mt-1">Deine Teilnahme ist <strong>kostenlos</strong>!</p>
                                </div>
                            @endguest

                            <div>
                                <label class="block text-sm font-medium mb-2">Mobile Rufnummer (optional)</label>
                                <input type="tel" name="mobile" value="{{ old('mobile', optional($user)->mobile ?? '') }}" class="w-full px-3 py-2 border rounded border-base-content/20" placeholder="+49 123 456789">
                                <p class="text-xs text-base-content/50 mt-1">Für WhatsApp-Updates</p>
                            </div>

                            @if(!$tshirtDeadlinePassed)
                                <div class="border-t pt-4">
                                    {{-- Prominenter Hinweis zur Bestellfrist --}}
                                    <x-fantreffen-tshirt-deadline-notice 
                                        :tshirtDeadlinePassed="$tshirtDeadlinePassed"
                                        :tshirtDeadlineFormatted="$tshirtDeadlineFormatted"
                                        :daysUntilDeadline="$daysUntilDeadline"
                                        variant="prominent"
                                    />
                                    <label class="flex items-start gap-2">
                                        <input type="checkbox" name="tshirt_bestellt" id="tshirt_bestellt" value="1" 
                                               {{ old('tshirt_bestellt') ? 'checked' : '' }}
                                               class="w-5 h-5 mt-0.5">
                                        <div>
                                            <span class="font-medium">Event-T-Shirt bestellen</span>
                                            <p class="text-xs text-base-content/50 mt-1">25,00 € Spende{{ !Auth::check() ? ' (zusammen mit Teilnahme: 30,00 €)' : '' }}</p>
                                        </div>
                                    </label>
                                    
                                    <div id="tshirt-groesse-container" class="mt-3 hidden">
                                        <label class="block text-sm font-medium mb-2">T-Shirt-Größe *</label>
                                        <select name="tshirt_groesse" id="tshirt_groesse" class="w-full px-3 py-2 border rounded border-base-content/20">
                                            <option value="">Bitte wählen...</option>
                                            <option value="XS" {{ old('tshirt_groesse') === 'XS' ? 'selected' : '' }}>XS</option>
                                            <option value="S" {{ old('tshirt_groesse') === 'S' ? 'selected' : '' }}>S</option>
                                            <option value="M" {{ old('tshirt_groesse') === 'M' ? 'selected' : '' }}>M</option>
                                            <option value="L" {{ old('tshirt_groesse') === 'L' ? 'selected' : '' }}>L</option>
                                            <option value="XL" {{ old('tshirt_groesse') === 'XL' ? 'selected' : '' }}>XL</option>
                                            <option value="XXL" {{ old('tshirt_groesse') === 'XXL' ? 'selected' : '' }}>XXL</option>
                                            <option value="XXXL" {{ old('tshirt_groesse') === 'XXXL' ? 'selected' : '' }}>XXXL</option>
                                        </select>
                                    </div>
                                </div>
                            @endif

                            <button type="submit" class="w-full px-6 py-3 bg-primary text-primary-content font-bold rounded-lg hover:bg-primary/80 transition">
                                @if($paymentAmount > 0)
                                    Weiter zur Zahlung ({{ number_format($paymentAmount, 2, ',', '.') }} €)
                                @else
                                    Jetzt anmelden
                                @endif
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@vite(['resources/js/fantreffen.js'])

</x-app-layout>
