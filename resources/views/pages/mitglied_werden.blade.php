<x-app-layout>
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
        <h1 class="text-3xl font-bold text-[#8B0116] mb-8">Mitglied werden</h1>

        <!-- Erfolg-/Fehlermeldungen -->
        <div id="form-messages" class="mb-4 hidden"></div>

        <form id="mitgliedschaft-form">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="vorname" class="block font-semibold">Vorname</label>
                    <input type="text" id="vorname" name="vorname" required
                        class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                    <span class="text-sm text-red-600" id="error-vorname"></span>
                </div>

                <div>
                    <label for="nachname" class="block font-semibold">Nachname</label>
                    <input type="text" id="nachname" name="nachname" required
                        class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                    <span class="text-sm text-red-600" id="error-nachname"></span>
                </div>

                <div>
                    <label for="strasse" class="block font-semibold">Straße</label>
                    <input type="text" id="strasse" name="strasse" required
                        class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                    <span class="text-sm text-red-600" id="error-strasse"></span>
                </div>

                <div>
                    <label for="hausnummer" class="block font-semibold">Hausnummer</label>
                    <input type="text" id="hausnummer" name="hausnummer" required
                        class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                    <span class="text-sm text-red-600" id="error-hausnummer"></span>
                </div>

                <div>
                    <label for="plz" class="block font-semibold">Postleitzahl</label>
                    <input type="text" id="plz" name="plz" required
                        class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                    <span class="text-sm text-red-600" id="error-plz"></span>
                </div>

                <div>
                    <label for="stadt" class="block font-semibold">Stadt</label>
                    <input type="text" id="stadt" name="stadt" required
                        class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                    <span class="text-sm text-red-600" id="error-stadt"></span>
                </div>

                <div>
                    <label for="land" class="block font-semibold">Land</label>
                    <select id="land" name="land" required class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                        <option value="">Bitte wählen</option>
                        <option>Deutschland</option>
                        <option>Österreich</option>
                        <option>Schweiz</option>
                    </select>
                    <span class="text-sm text-red-600" id="error-land"></span>
                </div>

                <div>
                    <label for="mail" class="block font-semibold">Mailadresse</label>
                    <input type="email" id="mail" name="mail" required
                        class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                    <span class="text-sm text-red-600" id="error-mail"></span>
                </div>

                <div>
                    <label for="passwort" class="block font-semibold">Passwort</label>
                    <input type="password" id="passwort" name="passwort" required
                        class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                    <span class="text-sm text-red-600" id="error-passwort"></span>
                </div>

                <div>
                    <label for="passwort_confirmation" class="block font-semibold">Passwort wiederholen</label>
                    <input type="password" id="passwort_confirmation" name="passwort_confirmation" required
                        class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                    <span class="text-sm text-red-600" id="error-passwort_confirmation"></span>
                </div>

                <div class="col-span-2">
                    <label for="mitgliedsbeitrag" class="block font-semibold">
                        Jährlicher Mitgliedsbeitrag: <span id="beitrag-output">12€</span>
                    </label>
                    <input type="range" id="mitgliedsbeitrag" name="mitgliedsbeitrag" min="12" max="120" value="12"
                        class="w-full">

                    <p class="mt-2 text-sm text-gray-600">
                        Du kannst deinen Mitgliedsbeitrag ab einem monatlichen Beitrag von 1€/Monat (12€/Jahr) selbst
                        wählen. Diesen Mitgliedsbeitrag kannst du jederzeit in deinen Einstellungen im internen
                        Mitgliederbereich ändern und so deinen nächsten Jahresbeitrag anpassen. Bei Fragen hierzu wende
                        dich gerne an den Vorstand.
                    </p>
                </div>

                <div>
                    <label for="telefon" class="block font-semibold">Handynummer (optional)</label>
                    <input type="tel" id="telefon" name="telefon" placeholder="+49 170 1234567"
                        class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                    <span class="text-sm text-red-600" id="error-telefon"></span>
                </div>

                <div>
                    <label for="verein_gefunden" class="block font-semibold">Wie hast du von uns erfahren? (optional)</label>
                    <select id="verein_gefunden" name="verein_gefunden"
                        class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
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

                <div class="col-span-2 flex items-center">
                    <input type="checkbox" id="satzung_check" name="satzung_check"
                        class="rounded border-gray-300 shadow-sm">
                    <label for="satzung_check" class="ml-2">
                        Ich habe die <a href="{{ route('satzung') }}" target="_blank"
                            class="text-blue-600 hover:underline">Satzung</a> gelesen und bin mit ihr einverstanden.
                    </label>
                </div>
            </div>

            <button type="submit" id="submit-button"
                class="mt-6 bg-[#8B0116] text-white py-2 px-4 rounded-md hover:bg-[#7a0113] transition duration-150 opacity-50 cursor-not-allowed"
                disabled>Antrag absenden</button>
        </form>
    </div>
</x-app-layout>