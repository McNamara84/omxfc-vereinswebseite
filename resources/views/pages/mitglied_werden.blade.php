<x-app-layout title="Mitglied werden – Offizieller MADDRAX Fanclub e. V." description="Online-Antrag zur Aufnahme in den Fanclub der MADDRAX-Romanserie.">
    <x-public-page>
        <x-header title="Mitglied werden" class="mb-4 sm:mb-8" data-testid="mitglied-werden-header" useH1 />
        <!-- Erfolg-/Fehlermeldungen -->
        <div id="form-messages" class="mb-4 hidden" role="alert"></div>
        <form id="mitgliedschaft-form" class="w-full">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 md:gap-6">
                <x-input name="vorname" label="Vorname" required class="w-full" autocomplete="given-name" />

                <x-input name="nachname" label="Nachname" required class="w-full" autocomplete="family-name" />

                <x-input name="strasse" label="Straße" required class="w-full" autocomplete="address-line1" />

                <x-input name="hausnummer" label="Hausnummer" required class="w-full" autocomplete="address-line2" />

                <x-input name="plz" label="Postleitzahl" required class="w-full" autocomplete="postal-code" />

                <x-input name="stadt" label="Stadt" required class="w-full" autocomplete="address-level2" />

                @php
                    $landOptions = [
                        ['id' => 'Deutschland', 'name' => 'Deutschland'],
                        ['id' => 'Österreich', 'name' => 'Österreich'],
                        ['id' => 'Schweiz', 'name' => 'Schweiz'],
                    ];
                @endphp
                <x-form-select
                    name="land"
                    label="Land"
                    aria-label="Land"
                    class="w-full"
                    placeholder="Bitte wählen"
                    :options="$landOptions"
                    required
                />

                <x-input name="mail" label="Mailadresse" type="email" required class="w-full" autocomplete="username" />

                <x-input name="passwort" label="Passwort" type="password" required class="w-full" autocomplete="new-password" hint="Mindestens 6 Zeichen." />

                <x-input name="passwort_confirmation" label="Passwort wiederholen" type="password" required class="w-full" autocomplete="new-password" hint="Bitte wiederhole dein Passwort." />

                <div class="col-span-1 md:col-span-2 w-full space-y-2" x-data="{ beitrag: {{ old('mitgliedsbeitrag', 12) }} }">
                    <label for="mitgliedsbeitrag" class="pt-0 label label-text font-semibold">
                        Jährlicher Mitgliedsbeitrag: <span id="beitrag-output" class="font-semibold text-[#8B0116] dark:text-[#ff4b63]" aria-live="polite" x-text="beitrag + '€'">{{ old('mitgliedsbeitrag', 12) }}€</span>
                    </label>
                    <input
                        type="range"
                        id="mitgliedsbeitrag"
                        name="mitgliedsbeitrag"
                        min="12"
                        max="120"
                        step="1"
                        value="{{ old('mitgliedsbeitrag', 12) }}"
                        x-model="beitrag"
                        class="range range-primary w-full"
                    >
                    <p class="text-sm text-base-content/70">Du kannst deinen Mitgliedsbeitrag ab einem monatlichen Beitrag von 1€/Monat (12€/Jahr) selbst wählen. Diesen Mitgliedsbeitrag kannst du jederzeit in deinen Einstellungen im internen Mitgliederbereich ändern und so deinen nächsten Jahresbeitrag anpassen. Bei Fragen hierzu wende dich gerne an den Vorstand.</p>
                </div>

                <x-input name="telefon" label="Handynummer (optional)" type="tel" class="w-full" autocomplete="tel" placeholder="+49 170 1234567" hint="Optional. Bitte im internationalen Format eingeben." />

                @php
                    $vereinGefundenOptions = [
                        ['id' => 'Facebook', 'name' => 'Facebook'],
                        ['id' => 'Instagram', 'name' => 'Instagram'],
                        ['id' => 'Leserkontaktseite', 'name' => 'Leserkontaktseite'],
                        ['id' => 'Befreundete Person', 'name' => 'Befreundete Person'],
                        ['id' => 'Fantreffen/MaddraxCon', 'name' => 'Fantreffen/MaddraxCon'],
                        ['id' => 'Google', 'name' => 'Google'],
                        ['id' => 'Sonstiges', 'name' => 'Sonstiges'],
                    ];
                @endphp
                <x-form-select
                    name="verein_gefunden"
                    label="Wie hast du von uns erfahren? (optional)"
                    aria-label="Wie hast du von uns erfahren?"
                    class="w-full"
                    placeholder="Bitte auswählen"
                    :options="$vereinGefundenOptions"
                />

                <!-- Checkbox über volle Breite -->
                <div class="col-span-1 md:col-span-2 mt-2">
                    <label class="flex gap-3 items-center cursor-pointer" data-testid="mitglied-satzung-check">
                        <input type="checkbox" id="satzung_check" name="satzung_check" class="checkbox" />
                        <span class="text-sm font-medium">
                            Ich habe die <a href="{{ route('satzung') }}" target="_blank" rel="noopener noreferrer" class="link link-primary" onclick="event.stopPropagation()">Satzung</a> gelesen und bin mit ihr einverstanden.
                        </span>
                    </label>
                </div>
            </div>
            <button type="submit" id="submit-button" class="btn btn-primary mt-6 opacity-50 cursor-not-allowed" disabled data-testid="mitglied-submit">
                <x-icon name="o-paper-airplane" class="h-5 w-5" />
                Antrag absenden
            </button>
            <!-- Lade-Indikator -->
            <div id="loading-indicator" class="mt-4 hidden flex items-center justify-center">
                <x-loading class="loading-spinner loading-lg text-primary" />
                <span class="ml-2 font-medium text-primary">Dein Antrag wird gesendet, bitte warten...</span>
            </div>
        </form>
    </x-public-page>
</x-app-layout>
