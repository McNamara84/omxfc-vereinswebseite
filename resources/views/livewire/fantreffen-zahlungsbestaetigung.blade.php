<div class="py-12">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        {{-- Success Header --}}
        <div class="bg-green-50 dark:bg-green-900/20 border-2 border-green-500 dark:border-green-700 rounded-lg p-8 mb-8 text-center">
            <div class="flex justify-center mb-4">
                <svg class="w-16 h-16 text-green-500 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
            <h1 class="text-3xl font-bold text-gray-900 dark:text-white mb-2">
                Anmeldung erfolgreich!
            </h1>
            <p class="text-lg text-gray-700 dark:text-gray-300">
                Vielen Dank für deine Anmeldung zum Maddrax-Fantreffen 2026!
            </p>
        </div>

        {{-- Anmeldungsdetails --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6 mb-6">
            <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-4">Deine Anmeldung</h2>
            
            <div class="space-y-3 text-sm">
                <div class="flex justify-between py-2 border-b border-gray-200 dark:border-gray-700">
                    <span class="font-medium text-gray-700 dark:text-gray-300">Name:</span>
                    <span class="text-gray-900 dark:text-white">{{ $anmeldung->full_name }}</span>
                </div>

                <div class="flex justify-between py-2 border-b border-gray-200 dark:border-gray-700">
                    <span class="font-medium text-gray-700 dark:text-gray-300">E-Mail:</span>
                    <span class="text-gray-900 dark:text-white">{{ $anmeldung->registrant_email }}</span>
                </div>

                @if($anmeldung->mobile)
                    <div class="flex justify-between py-2 border-b border-gray-200 dark:border-gray-700">
                        <span class="font-medium text-gray-700 dark:text-gray-300">Mobil:</span>
                        <span class="text-gray-900 dark:text-white">{{ $anmeldung->mobile }}</span>
                    </div>
                @endif

                <div class="flex justify-between py-2 border-b border-gray-200 dark:border-gray-700">
                    <span class="font-medium text-gray-700 dark:text-gray-300">Status:</span>
                    <span class="text-gray-900 dark:text-white">
                        {{ $anmeldung->ist_mitglied ? 'Vereinsmitglied' : 'Gast' }}
                    </span>
                </div>

                @if($anmeldung->tshirt_bestellt)
                    <div class="flex justify-between py-2 border-b border-gray-200 dark:border-gray-700">
                        <span class="font-medium text-gray-700 dark:text-gray-300">T-Shirt:</span>
                        <span class="text-gray-900 dark:text-white">
                            Ja (Größe: {{ $anmeldung->tshirt_groesse }})
                        </span>
                    </div>
                @endif

                <div class="flex justify-between py-2">
                    <span class="font-medium text-gray-700 dark:text-gray-300">Anmeldung vom:</span>
                    <span class="text-gray-900 dark:text-white">{{ $anmeldung->created_at->format('d.m.Y H:i') }} Uhr</span>
                </div>
            </div>
        </div>

        {{-- Zahlungsinformationen --}}
        @if($anmeldung->payment_status === 'pending' && $paypalMeUrl)
            <div class="bg-yellow-50 dark:bg-yellow-900/20 border-2 border-yellow-400 dark:border-yellow-700 rounded-lg p-6 mb-6">
                <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                    <svg class="w-6 h-6 text-yellow-600 dark:text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    Zahlung erforderlich
                </h2>

                <div class="space-y-4">
                    <div class="bg-white dark:bg-gray-800 rounded-lg p-4">
                        <div class="flex justify-between items-center mb-2">
                            <span class="text-gray-700 dark:text-gray-300">
                                @if($anmeldung->ist_mitglied)
                                    @if($anmeldung->tshirt_bestellt)
                                        T-Shirt Spende
                                    @endif
                                @else
                                    @if($anmeldung->tshirt_bestellt)
                                        Teilnahme + T-Shirt
                                    @else
                                        Teilnahmegebühr
                                    @endif
                                @endif
                            </span>
                            <span class="text-2xl font-bold text-[#8B0116] dark:text-[#ff4b63]">
                                {{ number_format((float) $anmeldung->payment_amount, 2, ',', '.') }} €
                            </span>
                        </div>
                        
                        @if(!$anmeldung->ist_mitglied && $anmeldung->tshirt_bestellt)
                            <div class="text-xs text-gray-600 dark:text-gray-400 space-y-1">
                                <div class="flex justify-between">
                                    <span>Teilnahme (Gast):</span>
                                    <span>5,00 €</span>
                                </div>
                                <div class="flex justify-between">
                                    <span>T-Shirt:</span>
                                    <span>25,00 €</span>
                                </div>
                            </div>
                        @endif
                    </div>

                    <div class="text-sm text-gray-700 dark:text-gray-300 space-y-2">
                        <p><strong>So geht's weiter:</strong></p>
                        <ol class="list-decimal list-inside space-y-1 ml-2">
                            <li>Klicke auf den PayPal-Button unten</li>
                            <li>Melde dich bei PayPal an</li>
                            <li>Bestätige die Zahlung als <strong>Freunde & Familie</strong> (keine Gebühren)</li>
                            <li>Nach erfolgreicher Zahlung erhältst du eine Bestätigung per E-Mail</li>
                        </ol>
                    </div>

                    <div class="text-center pt-4">
                        <a 
                            href="{{ $paypalMeUrl }}" 
                            target="_blank" rel="noopener noreferrer"
                            class="inline-flex items-center gap-2 bg-[#0070ba] hover:bg-[#005ea6] text-white font-bold py-3 px-8 rounded-lg transition-colors shadow-lg"
                        >
                            <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M20.067 8.478c.492.88.556 2.014.3 3.327-.74 3.806-3.276 5.12-6.514 5.12h-.5a.805.805 0 00-.794.68l-.04.22-.63 3.993-.032.17a.804.804 0 01-.794.679H7.72a.483.483 0 01-.477-.558L7.418 21h1.518l.95-6.02h1.385c4.678 0 7.75-2.203 8.796-6.502z"/>
                                <path d="M2.379 21.125a.483.483 0 01-.477-.558l2.334-14.808A.805.805 0 015.03 5.08h4.97c1.641 0 2.867.341 3.74 1.14.77.703 1.227 1.763 1.409 3.022-.012.103-.022.207-.028.312a6.314 6.314 0 01-.811 2.898c-.823 1.387-2.135 2.348-3.908 2.865a9.483 9.483 0 01-3.265.507H7.76a.805.805 0 00-.794.68l-.042.22-1.177 7.466a.483.483 0 01-.477.413z"/>
                            </svg>
                            Jetzt mit PayPal zahlen ({{ number_format((float) $anmeldung->payment_amount, 2, ',', '.') }} €)
                        </a>
                    </div>

                    <div class="text-xs text-gray-600 dark:text-gray-400 text-center">
                        <p><strong>Wichtig:</strong> Bitte wähle bei PayPal die Option <strong>"Freunde & Familie"</strong>, um Gebühren zu vermeiden.</p>
                        <p class="mt-1">Empfänger: vorstand@maddrax-fanclub.de</p>
                    </div>
                </div>
            </div>
        @elseif($anmeldung->payment_status === 'free')
            <div class="bg-green-50 dark:bg-green-900/20 border-2 border-green-500 dark:border-green-700 rounded-lg p-6 mb-6 text-center">
                <svg class="w-12 h-12 text-green-500 dark:text-green-400 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
                <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-2">Keine Zahlung erforderlich</h2>
                <p class="text-gray-700 dark:text-gray-300">Als Vereinsmitglied ist deine Teilnahme kostenlos!</p>
            </div>
        @endif

        {{-- Nächste Schritte --}}
        <div class="bg-blue-50 dark:bg-blue-900/20 border-2 border-blue-400 dark:border-blue-700 rounded-lg p-6 mb-6">
            <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-4">Was passiert jetzt?</h2>
            
            <div class="space-y-3 text-sm text-gray-700 dark:text-gray-300">
                <div class="flex gap-3">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-blue-500 text-white rounded-full flex items-center justify-center font-bold">1</div>
                    </div>
                    <div>
                        <p class="font-medium text-gray-900 dark:text-white">Bestätigungs-E-Mail</p>
                        <p>Du erhältst in Kürze eine E-Mail mit allen Details zur Veranstaltung.</p>
                    </div>
                </div>

                @if($anmeldung->payment_status === 'pending')
                    <div class="flex gap-3">
                        <div class="flex-shrink-0">
                            <div class="w-8 h-8 bg-blue-500 text-white rounded-full flex items-center justify-center font-bold">2</div>
                        </div>
                        <div>
                            <p class="font-medium text-gray-900 dark:text-white">Zahlung durchführen</p>
                            <p>Bitte überweise den Betrag über den PayPal-Link oben.</p>
                        </div>
                    </div>

                    <div class="flex gap-3">
                        <div class="flex-shrink-0">
                            <div class="w-8 h-8 bg-blue-500 text-white rounded-full flex items-center justify-center font-bold">3</div>
                        </div>
                        <div>
                            <p class="font-medium text-gray-900 dark:text-white">Zahlungsbestätigung</p>
                            <p>Nach Zahlungseingang wird deine Anmeldung final bestätigt.</p>
                        </div>
                    </div>
                @endif

                <div class="flex gap-3">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-blue-500 text-white rounded-full flex items-center justify-center font-bold">{{ $anmeldung->payment_status === 'pending' ? '4' : '2' }}</div>
                    </div>
                    <div>
                        <p class="font-medium text-gray-900 dark:text-white">Bis zum 9. Mai 2026</p>
                        <p>Wir freuen uns auf dich beim Fantreffen im L´Osteria Köln Mülheim!</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Zurück-Button --}}
        <div class="text-center">
            <a 
                href="{{ route('home') }}" 
                class="inline-flex items-center gap-2 text-[#8B0116] dark:text-[#ff4b63] hover:underline font-medium"
            >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                Zurück zur Startseite
            </a>
        </div>
    </div>
</div>
