<x-app-layout>
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-10 bg-gray-100 dark:bg-gray-800 rounded-lg shadow-sm">
        <h1 class="text-2xl sm:text-3xl font-bold text-[#8B0116] dark:text-[#ff4b63] mb-4 sm:mb-8">Mitglied werden</h1>
        <!-- Erfolg-/Fehlermeldungen -->
        <div id="form-messages" class="mb-4 hidden"></div>
        <form id="mitgliedschaft-form" class="w-full">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 md:gap-6">
                <div class="w-full">
                    <label for="vorname" class="block font-semibold mb-1">Vorname</label>
                    <input type="text" id="vorname" name="vorname" required
                        class="w-full rounded-md border-gray-300 shadow-sm">
                    <span class="text-sm text-red-600" id="error-vorname"></span>
                </div>
                <div class="w-full">
                    <label for="nachname" class="block font-semibold mb-1">Nachname</label>
                    <input type="text" id="nachname" name="nachname" required
                        class="w-full rounded-md border-gray-300 shadow-sm">
                    <span class="text-sm text-red-600" id="error-nachname"></span>
                </div>
                <div class="w-full">
                    <label for="strasse" class="block font-semibold mb-1">Straße</label>
                    <input type="text" id="strasse" name="strasse" required
                        class="w-full rounded-md border-gray-300 shadow-sm">
                    <span class="text-sm text-red-600" id="error-strasse"></span>
                </div>
                <div class="w-full">
                    <label for="hausnummer" class="block font-semibold mb-1">Hausnummer</label>
                    <input type="text" id="hausnummer" name="hausnummer" required
                        class="w-full rounded-md border-gray-300 shadow-sm">
                    <span class="text-sm text-red-600" id="error-hausnummer"></span>
                </div>
                <div class="w-full">
                    <label for="plz" class="block font-semibold mb-1">Postleitzahl</label>
                    <input type="text" id="plz" name="plz" required class="w-full rounded-md border-gray-300 shadow-sm">
                    <span class="text-sm text-red-600" id="error-plz"></span>
                </div>
                <div class="w-full">
                    <label for="stadt" class="block font-semibold mb-1">Stadt</label>
                    <input type="text" id="stadt" name="stadt" required
                        class="w-full rounded-md border-gray-300 shadow-sm">
                    <span class="text-sm text-red-600" id="error-stadt"></span>
                </div>
                <div class="w-full">
                    <label for="land" class="block font-semibold mb-1">Land</label>
                    <select id="land" name="land" required class="w-full rounded-md border-gray-300 shadow-sm">
                        <option value="">Bitte wählen</option>
                        <option>Deutschland</option>
                        <option>Österreich</option>
                        <option>Schweiz</option>
                    </select>
                    <span class="text-sm text-red-600" id="error-land"></span>
                </div>
                <div class="w-full">
                    <label for="mail" class="block font-semibold mb-1">Mailadresse</label>
                    <input type="email" id="mail" name="mail" required
                        class="w-full rounded-md border-gray-300 shadow-sm">
                    <span class="text-sm text-red-600" id="error-mail"></span>
                </div>
                <div class="w-full">
                    <label for="passwort" class="block font-semibold mb-1">Passwort</label>
                    <input type="password" id="passwort" name="passwort" required
                        class="w-full rounded-md border-gray-300 shadow-sm">
                    <span class="text-sm text-red-600" id="error-passwort"></span>
                </div>
                <div class="w-full">
                    <label for="passwort_confirmation" class="block font-semibold mb-1">Passwort wiederholen</label>
                    <input type="password" id="passwort_confirmation" name="passwort_confirmation" required
                        class="w-full rounded-md border-gray-300 shadow-sm">
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
                        class="w-full rounded-md border-gray-300 shadow-sm">
                    <span class="text-sm text-red-600" id="error-telefon"></span>
                </div>

                <div class="w-full">
                    <label for="verein_gefunden" class="block font-semibold mb-1">Wie hast du von uns erfahren?
                        (optional)</label>
                    <select id="verein_gefunden" name="verein_gefunden"
                        class="w-full rounded-md border-gray-300 shadow-sm">
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
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v2.5A5.5 5.5 0 006.5 12H4z">
                    </path>
                </svg>
                <span class="ml-2 font-medium text-[#8B0116]">Dein Antrag wird gesendet, bitte warten...</span>
            </div>
        </form>
    </div>
</x-app-layout>