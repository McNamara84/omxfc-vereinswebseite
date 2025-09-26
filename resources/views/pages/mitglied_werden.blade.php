<x-app-layout title="Mitglied werden – Offizieller MADDRAX Fanclub e. V." description="Online-Antrag zur Aufnahme in den Fanclub der MADDRAX-Romanserie.">
    <x-public-page>
        <h1 class="text-2xl sm:text-3xl font-bold text-[#8B0116] dark:text-[#ff4b63] mb-4 sm:mb-8">Mitglied werden</h1>
        <!-- Erfolg-/Fehlermeldungen -->
        <div id="form-messages" class="mb-4 hidden"></div>
        <form id="mitgliedschaft-form" class="w-full" method="POST" action="{{ route('mitglied.store') }}"
            data-success-url="{{ route('mitglied.werden.erfolgreich') }}">
            @csrf
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 md:gap-6">
                <x-forms.text-field name="vorname" label="Vorname" required class="w-full" autocomplete="given-name" />

                <x-forms.text-field name="nachname" label="Nachname" required class="w-full" autocomplete="family-name" />

                <x-forms.text-field name="strasse" label="Straße" required class="w-full" autocomplete="address-line1" />

                <x-forms.text-field name="hausnummer" label="Hausnummer" required class="w-full" autocomplete="address-line2" />

                <x-forms.text-field name="plz" label="Postleitzahl" required class="w-full" autocomplete="postal-code" />

                <x-forms.text-field name="stadt" label="Stadt" required class="w-full" autocomplete="address-level2" />

                <x-forms.select-field
                    name="land"
                    label="Land"
                    class="w-full"
                    placeholder="Bitte wählen"
                    :options="[
                        'Deutschland' => 'Deutschland',
                        'Österreich' => 'Österreich',
                        'Schweiz' => 'Schweiz',
                    ]"
                    required
                />

                <x-forms.text-field name="mail" label="Mailadresse" type="email" required class="w-full" autocomplete="username" />

                <x-forms.text-field name="passwort" label="Passwort" type="password" required class="w-full" autocomplete="new-password" help="Mindestens 6 Zeichen." />

                <x-forms.text-field name="passwort_confirmation" label="Passwort wiederholen" type="password" required class="w-full" autocomplete="new-password" help="Bitte wiederhole dein Passwort." />
                <x-forms.range-field
                    name="mitgliedsbeitrag"
                    label="Jährlicher Mitgliedsbeitrag"
                    class="col-span-1 md:col-span-2 w-full"
                    min="12"
                    max="120"
                    step="1"
                    value="12"
                    output-id="beitrag-output"
                    output-suffix="€"
                    help="Du kannst deinen Mitgliedsbeitrag ab einem monatlichen Beitrag von 1€/Monat (12€/Jahr) selbst wählen. Diesen Mitgliedsbeitrag kannst du jederzeit in deinen Einstellungen im internen Mitgliederbereich ändern und so deinen nächsten Jahresbeitrag anpassen. Bei Fragen hierzu wende dich gerne an den Vorstand."
                />
                <x-forms.text-field name="telefon" label="Handynummer (optional)" type="tel" class="w-full" autocomplete="tel" placeholder="+49 170 1234567" help="Optional. Bitte im internationalen Format eingeben." />

                <x-forms.select-field
                    name="verein_gefunden"
                    label="Wie hast du von uns erfahren? (optional)"
                    class="w-full"
                    placeholder="Bitte auswählen"
                    :options="[
                        'Facebook' => 'Facebook',
                        'Instagram' => 'Instagram',
                        'Leserkontaktseite' => 'Leserkontaktseite',
                        'Befreundete Person' => 'Befreundete Person',
                        'Fantreffen/MaddraxCon' => 'Fantreffen/MaddraxCon',
                        'Google' => 'Google',
                        'Sonstiges' => 'Sonstiges',
                    ]"
                />

                <!-- Checkbox über volle Breite -->
                <div class="col-span-1 md:col-span-2 flex items-start mt-2">
                    <input type="checkbox" id="satzung_check" name="satzung_check" required
                        class="mt-1 rounded border-gray-300 shadow-sm">
                    <label for="satzung_check" class="ml-2 text-sm">
                        Ich habe die <a href="{{ route('satzung') }}" target="_blank"
                            class="text-blue-600 dark:text-blue-400 hover:underline">Satzung</a> gelesen und bin mit ihr
                        einverstanden.
                    </label>
                </div>
            </div>
            <button type="submit" id="submit-button"
                class="mt-6 bg-[#8B0116] text-white py-2 px-4 rounded-md hover:bg-[#7a0113] transition duration-150 opacity-50 cursor-not-allowed dark:bg-[#9f0119] dark:hover:bg-[#8a0115]"
                disabled>Antrag absenden</button>
            <!-- Lade-Indikator -->
            <div id="loading-indicator" class="mt-4 hidden flex items-center justify-center">
                <svg class="animate-spin h-8 w-8 text-[#8B0116]" xmlns="http://www.w3.org/2000/svg" fill="none"
                    viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v2.5A5.5 5.5 0 006.5 12H4z"></path>
                </svg>
                <span class="ml-2 font-medium text-[#8B0116]">Dein Antrag wird gesendet, bitte warten...</span>
            </div>
        </form>
    </x-public-page>
</x-app-layout>
