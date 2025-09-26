<x-app-layout title="Mitglied werden – Offizieller MADDRAX Fanclub e. V." description="Online-Antrag zur Aufnahme in den Fanclub der MADDRAX-Romanserie.">
    <x-public-page>
        <h1 class="text-2xl sm:text-3xl font-bold text-[#8B0116] dark:text-[#ff4b63] mb-4 sm:mb-8">Mitglied werden</h1>
        <!-- Erfolg-/Fehlermeldungen -->
        <div id="form-messages" class="mb-4 hidden"></div>
        <form id="mitgliedschaft-form" class="w-full">
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
                <!-- Mitgliedsbeitrag über volle Breite -->
                <div class="col-span-1 md:col-span-2 w-full">
                    <label for="mitgliedsbeitrag" class="block font-semibold mb-1">
                        Jährlicher Mitgliedsbeitrag: <span id="beitrag-output">12€</span>
                    </label>
                    <input type="range" id="mitgliedsbeitrag" name="mitgliedsbeitrag" min="12" max="120" value="{{ old('mitgliedsbeitrag', 12) }}"
                        class="w-full">
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">
                        Du kannst deinen Mitgliedsbeitrag ab einem monatlichen Beitrag von 1€/Monat (12€/Jahr) selbst
                        wählen. Diesen Mitgliedsbeitrag kannst du jederzeit in deinen Einstellungen im internen
                        Mitgliederbereich ändern und so deinen nächsten Jahresbeitrag anpassen. Bei Fragen hierzu wende
                        dich gerne an den Vorstand.
                    </p>
                </div>
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
                    <input type="checkbox" id="satzung_check" name="satzung_check"
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
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const form = document.getElementById('mitgliedschaft-form');
            const beitrag = document.getElementById('mitgliedsbeitrag');
            const beitragOutput = document.getElementById('beitrag-output');
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

            beitragOutput.textContent = `${beitrag.value}€`;

            beitrag.addEventListener('input', () => {
                beitragOutput.textContent = beitrag.value + '€';
            });

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
                const errorElem = getErrorElement(input.id);

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
                    const input = document.getElementById(id);

                    if (!input) {
                        warnMissingField(id);
                        continue;
                    }

                    if (rules.optional && !input.value.trim()) {
                        setFieldError(input, '');
                        continue;
                    }

                    if (rules.matchWith) {
                        const matchElem = document.getElementById(rules.matchWith);
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
                            const input = document.getElementById(field);
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
