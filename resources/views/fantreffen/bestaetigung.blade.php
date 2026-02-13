<x-app-layout
    title="Anmeldebestätigung – Maddrax-Fantreffen 2026"
    description="Deine Anmeldung zum Maddrax-Fantreffen 2026 wurde erfolgreich gespeichert."
    :socialImage="asset('build/assets/omxfc-logo-Df-1StAj.png')">
<div class="bg-base-200 -mt-8 min-h-screen py-12">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        {{-- Erfolgsbox --}}
        <x-card shadow class="mb-6">
            <div class="text-center mb-6">
                <div class="mx-auto w-16 h-16 bg-success/20 rounded-full flex items-center justify-center mb-4">
                    <x-icon name="o-check" class="w-8 h-8 text-success" />
                </div>
                <h1 class="text-3xl font-bold text-base-content mb-2">Anmeldung erfolgreich!</h1>
                <p class="text-base-content/60">Wir freuen uns auf dich beim Maddrax-Fantreffen 2026!</p>
            </div>

            {{-- Anmeldedaten --}}
            <div class="border-t border-base-content/10 pt-6">
                <h2 class="text-xl font-semibold text-base-content mb-4">Deine Anmeldedaten</h2>
                <dl class="space-y-3">
                    <div class="flex justify-between">
                        <dt class="text-base-content/60">Name:</dt>
                        <dd class="font-medium text-base-content">{{ $anmeldung->vorname }} {{ $anmeldung->nachname }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-base-content/60">E-Mail:</dt>
                        <dd class="font-medium text-base-content">{{ $anmeldung->email }}</dd>
                    </div>
                    @if($anmeldung->mobile)
                    <div class="flex justify-between">
                        <dt class="text-base-content/60">Mobilnummer:</dt>
                        <dd class="font-medium text-base-content">{{ $anmeldung->mobile }}</dd>
                    </div>
                    @endif
                    @if($anmeldung->tshirt_bestellt)
                    <div class="flex justify-between">
                        <dt class="text-base-content/60">T-Shirt:</dt>
                        <dd class="font-medium text-base-content">Größe {{ $anmeldung->tshirt_groesse }}</dd>
                    </div>
                    @endif
                </dl>
            </div>

            {{-- Zahlungsinformationen --}}
            @if($anmeldung->payment_amount > 0)
            <div class="border-t border-base-content/10 mt-6 pt-6">
                <h2 class="text-xl font-semibold text-base-content mb-4">💳 Zahlungsinformationen</h2>
                <x-alert icon="o-banknotes" class="alert-info mb-4">
                    <p class="text-lg font-semibold mb-2">
                        Zu zahlender Betrag: {{ number_format($anmeldung->payment_amount, 2, ',', '.') }} €
                    </p>
                    <p class="text-sm">
                        Bitte überweise den Betrag auf unser Vereinskonto oder nutze PayPal.me
                    </p>
                </x-alert>

                {{-- PayPal Button --}}
                <x-button
                    label="💳 Jetzt mit PayPal bezahlen ({{ number_format($anmeldung->payment_amount, 2, ',', '.') }} €)"
                    link="https://www.paypal.com/paypalme/OMXFC/{{ number_format($anmeldung->payment_amount, 2, '.', '') }}"
                    external
                    class="btn-block mb-4"
                    style="background-color: #0070ba; border-color: #0070ba; color: white;"
                    data-testid="fantreffen-paypal-button"
                />

                {{-- PayPal-Gastzahlung Anleitung --}}
                <div class="bg-base-200 rounded-box p-4">
                    <h3 class="font-semibold text-base-content mb-3">💡 Kein PayPal-Account? Kein Problem!</h3>
                    <p class="text-sm text-base-content/70 mb-3">
                        Du kannst auch ohne PayPal-Konto oder Kreditkarte bezahlen, indem du PayPal als Gast nutzt.
                        So musst du kein Konto einrichten und zahlst einfach per SEPA-Lastschrift.
                    </p>
                    <ol class="space-y-2 text-sm text-base-content/70">
                        <li class="flex gap-2">
                            <span class="font-semibold text-primary flex-shrink-0">1.</span>
                            <span>Klicke oben auf den PayPal-Button. Du wirst zur PayPal-Bezahlseite weitergeleitet.</span>
                        </li>
                        <li class="flex gap-2">
                            <span class="font-semibold text-primary flex-shrink-0">2.</span>
                            <span>Unter der Login-Maske findest du den Link <strong>"Mit Debitkarte oder Bankkonto zahlen"</strong>. Klicke darauf.</span>
                        </li>
                        <li class="flex gap-2">
                            <span class="font-semibold text-primary flex-shrink-0">3.</span>
                            <span>Fülle deine Bankdaten (IBAN) aus und aktiviere die Zustimmungs-Checkboxen.</span>
                        </li>
                        <li class="flex gap-2">
                            <span class="font-semibold text-primary flex-shrink-0">4.</span>
                            <span>Schließe den Bezahlvorgang mit <strong>"Zustimmen und weiter"</strong> ab.</span>
                        </li>
                    </ol>
                </div>

                <p class="text-xs text-base-content/50 mt-4 text-center">
                    📧 Bei Fragen zur Zahlung wende dich bitte an <a href="mailto:kassenwart@maddrax-fanclub.de" class="link link-hover">kassenwart@maddrax-fanclub.de</a>
                </p>
            </div>
            @endif

            {{-- Bestätigungs-E-Mail --}}
            <div class="border-t border-base-content/10 mt-6 pt-6">
                <div class="flex items-start gap-3">
                    <x-icon name="o-envelope" class="w-6 h-6 text-info flex-shrink-0 mt-0.5" />
                    <div>
                        <p class="text-sm text-base-content/70">
                            Du hast eine Bestätigungs-E-Mail an <strong class="text-base-content">{{ $anmeldung->email }}</strong> erhalten.
                        </p>
                        <p class="text-xs text-base-content/50 mt-1">
                            Falls du keine E-Mail erhalten hast, überprüfe bitte deinen Spam-Ordner.
                        </p>
                    </div>
                </div>
            </div>

            {{-- Buttons --}}
            <div class="mt-8 flex flex-col sm:flex-row gap-3">
                <x-button label="🏠 Zur Startseite" link="{{ route('home') }}" class="btn-ghost flex-1" data-testid="fantreffen-home-button" />
                @auth
                <x-button label="📊 Zum Dashboard" link="{{ route('dashboard') }}" class="btn-primary flex-1" data-testid="fantreffen-dashboard-button" />
                @endauth
            </div>
        </x-card>
    </div>
</div>
</x-app-layout>
