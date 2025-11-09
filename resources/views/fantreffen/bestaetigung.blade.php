<x-app-layout
    title="Anmeldebestätigung – Maddrax-Fantreffen 2026"
    description="Deine Anmeldung zum Maddrax-Fantreffen 2026 wurde erfolgreich gespeichert."
    :socialImage="asset('build/assets/omxfc-logo-Df-1StAj.png')">
<div class="bg-gray-50 dark:bg-gray-900 -mt-8 min-h-screen py-12">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Erfolgsbox -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-8 mb-6">
            <div class="text-center mb-6">
                <div class="mx-auto w-16 h-16 bg-green-100 dark:bg-green-900 rounded-full flex items-center justify-center mb-4">
                    <svg class="w-8 h-8 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                </div>
                <h1 class="text-3xl font-bold text-gray-900 dark:text-gray-100 mb-2">Anmeldung erfolgreich!</h1>
                <p class="text-gray-600 dark:text-gray-400">Wir freuen uns auf dich beim Maddrax-Fantreffen 2026!</p>
            </div>

            <!-- Anmeldedaten -->
            <div class="border-t border-gray-200 dark:border-gray-700 pt-6">
                <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100 mb-4">Deine Anmeldedaten</h2>
                <dl class="space-y-3">
                    <div class="flex justify-between">
                        <dt class="text-gray-600 dark:text-gray-400">Name:</dt>
                        <dd class="font-medium text-gray-900 dark:text-gray-100">{{ $anmeldung->vorname }} {{ $anmeldung->nachname }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-600 dark:text-gray-400">E-Mail:</dt>
                        <dd class="font-medium text-gray-900 dark:text-gray-100">{{ $anmeldung->email }}</dd>
                    </div>
                    @if($anmeldung->mobile)
                    <div class="flex justify-between">
                        <dt class="text-gray-600 dark:text-gray-400">Mobilnummer:</dt>
                        <dd class="font-medium text-gray-900 dark:text-gray-100">{{ $anmeldung->mobile }}</dd>
                    </div>
                    @endif
                    @if($anmeldung->tshirt_bestellt)
                    <div class="flex justify-between">
                        <dt class="text-gray-600 dark:text-gray-400">T-Shirt:</dt>
                        <dd class="font-medium text-gray-900 dark:text-gray-100">Größe {{ $anmeldung->tshirt_groesse }}</dd>
                    </div>
                    @endif
                </dl>
            </div>

            <!-- Zahlungsinformationen -->
            @if($anmeldung->betrag > 0)
            <div class="border-t border-gray-200 dark:border-gray-700 mt-6 pt-6">
                <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100 mb-4">💳 Zahlungsinformationen</h2>
                <div class="bg-blue-50 dark:bg-blue-900 border border-blue-200 dark:border-blue-700 rounded-lg p-4 mb-4">
                    <p class="text-lg font-semibold text-blue-900 dark:text-blue-100 mb-2">
                        Zu zahlender Betrag: {{ number_format($anmeldung->betrag, 2, ',', '.') }} €
                    </p>
                    <p class="text-sm text-blue-800 dark:text-blue-200">
                        Bitte überweise den Betrag auf unser Vereinskonto oder nutze PayPal.me
                    </p>
                </div>

                <!-- PayPal Button -->
                <a href="https://www.paypal.com/paypalme/{{ config('services.paypal.me_username') }}/{{ number_format($anmeldung->betrag, 2, '.', '') }}EUR" 
                   target="_blank"
                   class="block w-full px-6 py-3 bg-[#0070ba] text-white font-semibold rounded-lg hover:bg-[#005a92] text-center mb-4">
                    💳 Jetzt mit PayPal bezahlen ({{ number_format($anmeldung->betrag, 2, ',', '.') }} €)
                </a>

                <!-- Bankverbindung -->
                <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                    <h3 class="font-semibold text-gray-900 dark:text-gray-100 mb-2">Oder per Banküberweisung:</h3>
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-gray-600 dark:text-gray-400">Empfänger:</dt>
                            <dd class="font-medium text-gray-900 dark:text-gray-100">OMXFC e. V.</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-gray-600 dark:text-gray-400">IBAN:</dt>
                            <dd class="font-mono text-sm text-gray-900 dark:text-gray-100">DE89 3702 0500 0008 8158 00</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-gray-600 dark:text-gray-400">BIC:</dt>
                            <dd class="font-mono text-sm text-gray-900 dark:text-gray-100">BFSWDE33XXX</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-gray-600 dark:text-gray-400">Verwendungszweck:</dt>
                            <dd class="font-medium text-gray-900 dark:text-gray-100">Fantreffen 2026 - {{ $anmeldung->vorname }} {{ $anmeldung->nachname }}</dd>
                        </div>
                    </dl>
                </div>

                <p class="text-xs text-gray-500 dark:text-gray-400 mt-4 text-center">
                    ⚠️ Wichtig: Bitte gib den Verwendungszweck genau so an, damit wir deine Zahlung zuordnen können.
                </p>
            </div>
            @endif

            <!-- Bestätigungs-E-Mail -->
            <div class="border-t border-gray-200 dark:border-gray-700 mt-6 pt-6">
                <div class="flex items-start gap-3">
                    <svg class="w-6 h-6 text-blue-600 dark:text-blue-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                    </svg>
                    <div>
                        <p class="text-sm text-gray-700 dark:text-gray-300">
                            Du hast eine Bestätigungs-E-Mail an <strong>{{ $anmeldung->email }}</strong> erhalten.
                        </p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                            Falls du keine E-Mail erhalten hast, überprüfe bitte deinen Spam-Ordner.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Buttons -->
            <div class="mt-8 flex flex-col sm:flex-row gap-3">
                <a href="{{ route('home') }}" 
                   class="flex-1 px-6 py-3 bg-gray-200 dark:bg-gray-700 text-gray-900 dark:text-gray-100 font-semibold rounded-lg hover:bg-gray-300 dark:hover:bg-gray-600 text-center">
                    🏠 Zur Startseite
                </a>
                @auth
                <a href="{{ route('dashboard') }}" 
                   class="flex-1 px-6 py-3 bg-[#8B0116] text-white font-semibold rounded-lg hover:bg-[#6b000e] text-center">
                    📊 Zum Dashboard
                </a>
                @endauth
            </div>
        </div>
    </div>
</div>
</x-app-layout>
