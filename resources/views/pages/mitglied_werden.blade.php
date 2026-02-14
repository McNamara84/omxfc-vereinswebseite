<x-app-layout title="Mitglied werden – Offizieller MADDRAX Fanclub e. V." description="Online-Antrag zur Aufnahme in den Fanclub der MADDRAX-Romanserie.">
    <x-public-page>
        <h1 class="text-2xl sm:text-3xl font-bold text-[#8B0116] dark:text-[#ff4b63] mb-4 sm:mb-8">Mitglied werden</h1>
        <!-- Erfolg-/Fehlermeldungen -->
        <div id="form-messages" class="mb-4 hidden"></div>
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
                <x-select
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
                <x-select
                    name="verein_gefunden"
                    label="Wie hast du von uns erfahren? (optional)"
                    aria-label="Wie hast du von uns erfahren?"
                    class="w-full"
                    placeholder="Bitte auswählen"
                    :options="$vereinGefundenOptions"
                />

                <!-- Checkbox über volle Breite -->
                <div class="col-span-1 md:col-span-2 flex items-start mt-2">
                    <input type="checkbox" id="satzung_check" name="satzung_check" class="checkbox mt-1">
                    <label for="satzung_check" class="ml-2 text-sm">
                        Ich habe die <a href="{{ route('satzung') }}" target="_blank"
                            class="text-blue-600 dark:text-blue-400 hover:underline">Satzung</a> gelesen und bin mit ihr
                        einverstanden.
                    </label>
                </div>
            </div>
            <button type="submit" id="submit-button" class="btn btn-primary mt-6 opacity-50 cursor-not-allowed" disabled>Antrag absenden</button>
            <!-- Lade-Indikator -->
            <div id="loading-indicator" class="mt-4 hidden flex items-center justify-center">
                <x-loading class="loading-spinner loading-lg text-[#8B0116]" />
                <span class="ml-2 font-medium text-[#8B0116]">Dein Antrag wird gesendet, bitte warten...</span>
            </div>
        </form>
    </x-public-page>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const form = document.getElementById('mitgliedschaft-form');
            const satzungCheck = document.getElementById('satzung_check');
            const submitButton = document.getElementById('submit-button');
            const missingFieldWarnings = new Set();

            const fields = {
                vorname: { regex: /.+/, error: 'Vorname ist erforderlich.' },
                nachname: { regex: /.+/, error: 'Nachname ist erforderlich.' },
                strasse: { regex: /.+/, error: 'Straße ist erforderlich.' },
                hausnummer: { regex: /.+/, error: 'Hausnummer ist erforderlich.' },
                plz: { regex: /^\d{4,6}$/, error: 'Bitte gültige PLZ eingeben (4-6 Zahlen).' },
                stadt: { regex: /.+/, error: 'Stadt ist erforderlich.' },
                land: { regex: /^(Deutschland|Österreich|Schweiz)$/, error: 'Bitte wähle dein Land.' },
                mail: { regex: /^[^@\s]+@[^@\s]+\.[^@\s]+$/, error: 'Bitte gültige Mailadresse eingeben.' },
                passwort: { regex: /^.{6,}$/, error: 'Passwort mindestens 6 Zeichen.' },
                passwort_confirmation: { matchWith: 'passwort', error: 'Passwörter stimmen nicht überein.' },
                telefon: {
                    regex: /^(\+\d{1,3}\s?)?(\d{4,14})$/,
                    error: 'Bitte gültige Handynummer eingeben.',
                    optional: true,
                },
                verein_gefunden: {
                    regex: /^(Facebook|Instagram|Leserkontaktseite|Befreundete Person|Fantreffen\/MaddraxCon|Google|Sonstiges)$/,
                    error: 'Bitte wähle eine Option aus.',
                    optional: true,
                },
            };

            satzungCheck.addEventListener('change', toggleSubmit);
            form.addEventListener('input', validateForm);
            form.addEventListener('submit', handleSubmit);

            function toggleSubmit() {
                submitButton.disabled = !satzungCheck.checked || !form.checkValidity();
                submitButton.classList.toggle('opacity-50', submitButton.disabled);
                submitButton.classList.toggle('cursor-not-allowed', submitButton.disabled);
            }

            function getErrorElement(id) {
                return (
                    document.getElementById(`error-${id}`) ||
                    document.querySelector(`[data-error-for="${id}"]`)
                );
            }

            function warnMissingField(id) {
                if (missingFieldWarnings.has(id)) {
                    return;
                }

                missingFieldWarnings.add(id);

                if (typeof console !== 'undefined' && typeof console.warn === 'function') {
                    console.warn(`[Mitgliedschaftsformular] Feld mit ID "${id}" wurde nicht gefunden. Bitte überprüfe die Formularstruktur.`);
                }
            }

            function setFieldError(input, message) {
                const fieldName = input.getAttribute('name') || input.id;
                const errorElem = getErrorElement(fieldName);

                if (errorElem) {
                    errorElem.textContent = message;
                }

                if (message) {
                    input.setCustomValidity(message);
                    input.setAttribute('aria-invalid', 'true');
                    return false;
                }

                input.setCustomValidity('');
                input.removeAttribute('aria-invalid');
                return true;
            }

            function validateForm() {
                let isValid = true;

                for (const [id, rules] of Object.entries(fields)) {
                    const input = form.querySelector(`[name="${id}"]`);

                    if (!input) {
                        warnMissingField(id);
                        continue;
                    }

                    if (rules.optional && !input.value.trim()) {
                        setFieldError(input, '');
                        continue;
                    }

                    if (rules.matchWith) {
                        const matchElem = form.querySelector(`[name="${rules.matchWith}"]`);
                        const hasError = input.value !== matchElem.value;
                        isValid = setFieldError(input, hasError ? rules.error : '') && isValid;
                    } else {
                        const hasError = !rules.regex.test(input.value.trim());
                        isValid = setFieldError(input, hasError ? rules.error : '') && isValid;
                    }
                }

                toggleSubmit();
                return isValid;
            }

            async function handleSubmit(e) {
                e.preventDefault();

                if (!validateForm() || !satzungCheck.checked) {
                    return;
                }

                const formData = new FormData(form);
                const messages = document.getElementById('form-messages');
                const submitButton = document.getElementById('submit-button');
                const loadingIndicator = document.getElementById('loading-indicator');

                // Button deaktivieren und Lade-Indikator zeigen
                submitButton.disabled = true;
                submitButton.classList.add('hidden');
                loadingIndicator.classList.remove('hidden');

                try {
                    const response = await fetch('/mitglied-werden', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Accept': 'application/json'
                        },
                        body: formData,
                        credentials: 'same-origin'
                    });

                    let result = null;
                    try {
                        result = await response.json();
                    } catch (jsonError) {
                        console.error('JSON parsing error:', jsonError);
                    }

                    if ((response.ok && result && result.success) || (result && result.success)) {
                        window.location.href = '/mitglied-werden/erfolgreich';
                    } else if (response.status === 422 && result && result.errors) {
                        // Serverseitige Validierungsfehler den jeweiligen Feldern zuordnen
                        for (const [field, msgs] of Object.entries(result.errors)) {
                            const input = form.querySelector(`[name="${field}"]`);
                            const message = msgs.join(' ');
                            if (input) {
                                setFieldError(input, message);
                            } else {
                                warnMissingField(field);
                                const errorElem = getErrorElement(field);
                                if (errorElem) {
                                    errorElem.textContent = message;
                                }
                            }
                        }

                        messages.textContent = 'Bitte korrigiere die markierten Felder.';
                        messages.className = 'mb-4 p-4 bg-red-100 border border-red-400 text-red-800 rounded';
                        messages.classList.remove('hidden');
                        messages.scrollIntoView({ behavior: 'smooth' });

                        // Button und Indikator zurücksetzen bei Fehler
                        submitButton.disabled = false;
                        submitButton.classList.remove('hidden');
                        loadingIndicator.classList.add('hidden');
                    } else {
                        messages.textContent = 'Unbekannter Fehler aufgetreten.';
                        messages.className = 'mb-4 p-4 bg-red-100 border border-red-400 text-red-800 rounded';
                        messages.classList.remove('hidden');
                        messages.scrollIntoView({ behavior: 'smooth' });

                        // Button und Indikator zurücksetzen bei Fehler
                        submitButton.disabled = false;
                        submitButton.classList.remove('hidden');
                        loadingIndicator.classList.add('hidden');
                    }
                } catch (error) {
                    messages.className = 'mb-4 p-4 bg-red-100 border border-red-400 text-red-800 rounded';
                    messages.textContent = 'Ein unerwarteter Fehler ist aufgetreten. Bitte versuche es später erneut.';
                    messages.classList.remove('hidden');
                    messages.scrollIntoView({ behavior: 'smooth' });
                    console.error('Fehler:', error);

                    // Button und Indikator zurücksetzen bei Fehler
                    submitButton.disabled = false;
                    submitButton.classList.remove('hidden');
                    loadingIndicator.classList.add('hidden');
                }
            }

            // Initialer Check beim Laden der Seite
            toggleSubmit();
        });
    </script>
</x-app-layout>
