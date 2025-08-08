<x-app-layout title="Mitglied werden – Offizieller MADDRAX Fanclub e. V." description="Online-Antrag zur Aufnahme in den Fanclub der MADDRAX-Romanserie.">
    <x-public-page>
        <h1 class="text-2xl sm:text-3xl font-bold text-[#8B0116] dark:text-[#ff4b63] mb-4 sm:mb-8">Mitglied werden</h1>
        <!-- Erfolg-/Fehlermeldungen -->
        <div id="form-messages" class="mb-4 hidden"></div>
        <form id="mitgliedschaft-form" class="w-full">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 md:gap-6">
                <div class="w-full">
                    <label for="vorname" class="block font-semibold mb-1">Vorname</label>
                    <input type="text" id="vorname" name="vorname" required
                        class="w-full rounded-md border-gray-300 shadow-sm bg-white text-gray-900 dark:bg-gray-700 dark:text-gray-100">
                    <span class="text-sm text-red-600" id="error-vorname"></span>
                </div>
                <div class="w-full">
                    <label for="nachname" class="block font-semibold mb-1">Nachname</label>
                    <input type="text" id="nachname" name="nachname" required
                        class="w-full rounded-md border-gray-300 shadow-sm bg-white text-gray-900 dark:bg-gray-700 dark:text-gray-100">
                    <span class="text-sm text-red-600" id="error-nachname"></span>
                </div>
                <div class="w-full">
                    <label for="strasse" class="block font-semibold mb-1">Straße</label>
                    <input type="text" id="strasse" name="strasse" required
                        class="w-full rounded-md border-gray-300 shadow-sm bg-white text-gray-900 dark:bg-gray-700 dark:text-gray-100">
                    <span class="text-sm text-red-600" id="error-strasse"></span>
                </div>
                <div class="w-full">
                    <label for="hausnummer" class="block font-semibold mb-1">Hausnummer</label>
                    <input type="text" id="hausnummer" name="hausnummer" required
                        class="w-full rounded-md border-gray-300 shadow-sm bg-white text-gray-900 dark:bg-gray-700 dark:text-gray-100">
                    <span class="text-sm text-red-600" id="error-hausnummer"></span>
                </div>
                <div class="w-full">
                    <label for="plz" class="block font-semibold mb-1">Postleitzahl</label>
                    <input type="text" id="plz" name="plz" required
                        class="w-full rounded-md border-gray-300 shadow-sm bg-white text-gray-900 dark:bg-gray-700 dark:text-gray-100">
                    <span class="text-sm text-red-600" id="error-plz"></span>
                </div>
                <div class="w-full">
                    <label for="stadt" class="block font-semibold mb-1">Stadt</label>
                    <input type="text" id="stadt" name="stadt" required
                        class="w-full rounded-md border-gray-300 shadow-sm bg-white text-gray-900 dark:bg-gray-700 dark:text-gray-100">
                    <span class="text-sm text-red-600" id="error-stadt"></span>
                </div>
                <div class="w-full">
                    <label for="land" class="block font-semibold mb-1">Land</label>
                    <select id="land" name="land" required
                        class="w-full rounded-md border-gray-300 shadow-sm bg-white text-gray-900 dark:bg-gray-700 dark:text-gray-100">
                        <option value="">Bitte wählen</option>
                        <option>Deutschland</option>
                        <option>Österreich</option>
                        <option>Schweiz</option>
                    </select>
                    <span class="text-sm text-red-600" id="error-land"></span>
                </div>
                <div class="w-full">
                    <label for="mail" class="block font-semibold mb-1">Mailadresse</label>
                    <input type="email" id="mail" name="mail" required autocomplete="username"
                        class="w-full rounded-md border-gray-300 shadow-sm bg-white text-gray-900 dark:bg-gray-700 dark:text-gray-100">
                    <span class="text-sm text-red-600" id="error-mail"></span>
                </div>
                <div class="w-full">
                    <label for="passwort" class="block font-semibold mb-1">Passwort</label>
                    <input type="password" id="passwort" name="passwort" required autocomplete="new-password"
                        class="w-full rounded-md border-gray-300 shadow-sm bg-white text-gray-900 dark:bg-gray-700 dark:text-gray-100">
                    <span class="text-sm text-red-600" id="error-passwort"></span>
                </div>
                <div class="w-full">
                    <label for="passwort_confirmation" class="block font-semibold mb-1">Passwort wiederholen</label>
                    <input type="password" id="passwort_confirmation" name="passwort_confirmation" required
                        autocomplete="new-password"
                        class="w-full rounded-md border-gray-300 shadow-sm bg-white text-gray-900 dark:bg-gray-700 dark:text-gray-100">
                    <span class="text-sm text-red-600" id="error-passwort_confirmation"></span>
                </div>
                <!-- Mitgliedsbeitrag über volle Breite -->
                <div class="col-span-1 md:col-span-2 w-full">
                    <label for="mitgliedsbeitrag" class="block font-semibold mb-1">
                        Jährlicher Mitgliedsbeitrag: <span id="beitrag-output">12€</span>
                    </label>
                    <input type="range" id="mitgliedsbeitrag" name="mitgliedsbeitrag" min="12" max="120" value="12"
                        class="w-full">
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">
                        Du kannst deinen Mitgliedsbeitrag ab einem monatlichen Beitrag von 1€/Monat (12€/Jahr) selbst
                        wählen. Diesen Mitgliedsbeitrag kannst du jederzeit in deinen Einstellungen im internen
                        Mitgliederbereich ändern und so deinen nächsten Jahresbeitrag anpassen. Bei Fragen hierzu wende
                        dich gerne an den Vorstand.
                    </p>
                </div>
                <div class="w-full">
                    <label for="telefon" class="block font-semibold mb-1">Handynummer (optional)</label>
                    <input type="tel" id="telefon" name="telefon" placeholder="+49 170 1234567"
                        class="w-full rounded-md border-gray-300 shadow-sm bg-white text-gray-900 dark:bg-gray-700 dark:text-gray-100">
                    <span class="text-sm text-red-600" id="error-telefon"></span>
                </div>
                <div class="w-full">
                    <label for="verein_gefunden" class="block font-semibold mb-1">Wie hast du von uns erfahren?
                        (optional)</label>
                    <select id="verein_gefunden" name="verein_gefunden"
                        class="w-full rounded-md border-gray-300 shadow-sm bg-white text-gray-900 dark:bg-gray-700 dark:text-gray-100">
                        <option value="">Bitte auswählen</option>
                        <option>Facebook</option>
                        <option>Instagram</option>
                        <option>Leserkontaktseite</option>
                        <option>Befreundete Person</option>
                        <option>Fantreffen/MaddraxCon</option>
                        <option>Google</option>
                        <option>Sonstiges</option>
                    </select>
                    <span class="text-sm text-red-600" id="error-verein_gefunden"></span>
                </div>
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

            const fields = {
                vorname: { regex: /.+/, error: "Vorname ist erforderlich." },
                nachname: { regex: /.+/, error: "Nachname ist erforderlich." },
                strasse: { regex: /.+/, error: "Straße ist erforderlich." },
                hausnummer: { regex: /.+/, error: "Hausnummer ist erforderlich." },
                plz: { regex: /^\d{4,6}$/, error: "Bitte gültige PLZ eingeben (4-6 Zahlen)." },
                stadt: { regex: /.+/, error: "Stadt ist erforderlich." },
                land: { regex: /^(Deutschland|Österreich|Schweiz)$/, error: "Bitte wähle dein Land." },
                mail: { regex: /^[^@\s]+@[^@\s]+\.[^@\s]+$/, error: "Bitte gültige Mailadresse eingeben." },
                passwort: { regex: /^.{6,}$/, error: "Passwort mindestens 6 Zeichen." },
                passwort_confirmation: { matchWith: 'passwort', error: "Passwörter stimmen nicht überein." },
                telefon: { regex: /^(\+\d{1,3}\s?)?(\d{4,14})$/, error: "Bitte gültige Handynummer eingeben.", optional: true },
                verein_gefunden: { regex: /^(Facebook|Instagram|Leserkontaktseite|Befreundete Person|Fantreffen\/MaddraxCon|Google|Sonstiges)$/, error: "Bitte wähle eine Option aus.", optional: true }
            };

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

            function validateForm() {
                let isValid = true;

                for (const [id, rules] of Object.entries(fields)) {
                    const input = document.getElementById(id);
                    const errorElem = document.getElementById(`error-${id}`);

                    if (rules.optional && !input.value.trim()) {
                        errorElem.textContent = '';
                        continue;
                    }

                    if (rules.matchWith) {
                        const matchElem = document.getElementById(rules.matchWith);
                        if (input.value !== matchElem.value) {
                            errorElem.textContent = rules.error;
                            isValid = false;
                        } else {
                            errorElem.textContent = '';
                        }
                    } else if (!rules.regex.test(input.value.trim())) {
                        errorElem.textContent = rules.error;
                        isValid = false;
                    } else {
                        errorElem.textContent = '';
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
                            const errorElem = document.getElementById(`error-${field}`);
                            if (errorElem) {
                                errorElem.textContent = msgs.join(' ');
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
