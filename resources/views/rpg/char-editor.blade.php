<x-app-layout>
    <x-member-page class="max-w-4xl">
        <div class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6">
            <h1 class="text-2xl font-semibold text-[#8B0116] dark:text-red-400 mb-6">Charakter-Editor</h1>

            <form action="#" method="POST" enctype="multipart/form-data">
                @csrf

                <input type="hidden" name="available_advantage_points" id="available_advantage_points" value="1">
                <input type="hidden" name="figurenstaerke" id="figurenstaerke" value="1">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                    <div>
                        <label for="player_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Spielername</label>
                        <input type="text" name="player_name" id="player_name" class="w-full rounded-md shadow-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:border-[#8B0116] dark:focus:border-[#FF6B81] focus:ring focus:ring-[#8B0116] dark:focus:ring-[#FF6B81] focus:ring-opacity-50">
                    </div>

                    <div>
                        <label for="character_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Charaktername</label>
                        <input type="text" name="character_name" id="character_name" class="w-full rounded-md shadow-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:border-[#8B0116] dark:focus:border-[#FF6B81] focus:ring focus:ring-[#8B0116] dark:focus:ring-[#FF6B81] focus:ring-opacity-50">
                    </div>

                    <div>
                        <label for="race" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Rasse</label>
                        <select name="race" id="race" class="w-full rounded-md shadow-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:border-[#8B0116] dark:focus:border-[#FF6B81] focus:ring focus:ring-[#8B0116] dark:focus:ring-[#FF6B81] focus:ring-opacity-50">
                            <option value="" disabled selected>Rasse wählen</option>
                            <option value="Barbar">Barbar</option>
                        </select>
                    </div>

                    <div>
                        <label for="culture" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Kultur</label>
                        <select name="culture" id="culture" class="w-full rounded-md shadow-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:border-[#8B0116] dark:focus:border-[#FF6B81] focus:ring focus:ring-[#8B0116] dark:focus:ring-[#FF6B81] focus:ring-opacity-50">
                            <option value="" disabled selected>Kultur wählen</option>
                            <option value="Landbewohner">Landbewohner</option>
                        </select>
                    </div>

                    <div class="md:col-span-2">
                        <label for="portrait" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Porträt/Symbol</label>
                        <input type="file" name="portrait" id="portrait" accept="image/*" class="w-full rounded-md shadow-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:border-[#8B0116] dark:focus:border-[#FF6B81] focus:ring focus:ring-[#8B0116] dark:focus:ring-[#FF6B81] focus:ring-opacity-50">
                    </div>

                <div class="md:col-span-2">
                    <h2 class="text-xl font-semibold text-[#8B0116] dark:text-red-400 mb-2">Beschreibung</h2>
                    <textarea name="description" id="description" rows="4" class="w-full rounded-md shadow-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:border-[#8B0116] dark:focus:border-[#FF6B81] focus:ring focus:ring-[#8B0116] dark:focus:ring-[#FF6B81] focus:ring-opacity-50"></textarea>
                </div>
            </div>

            <div class="flex justify-end mb-6">
                <button id="continue-button" type="button" class="hidden inline-flex items-center px-4 py-2 bg-[#8B0116] dark:bg-red-400 text-white rounded-md">Weiter, bei Wudan</button>
            </div>

            <fieldset id="advanced-fields" disabled class="opacity-50">
            <div class="mb-6">
                <h2 class="text-xl font-semibold text-[#8B0116] dark:text-red-400 mb-2">Attribute</h2>
                <p id="attribute-points" class="text-sm text-gray-700 dark:text-gray-300 mb-2"></p>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                        <div>
                            <label for="st" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Stärke (ST)</label>
                            <input type="number" name="attributes[st]" id="st" min="-1" max="1" step="1" class="w-full rounded-md shadow-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:border-[#8B0116] dark:focus:border-[#FF6B81] focus:ring focus:ring-[#8B0116] dark:focus:ring-[#FF6B81] focus:ring-opacity-50">
                        </div>
                        <div>
                            <label for="ge" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Geschicklichkeit (GE)</label>
                            <input type="number" name="attributes[ge]" id="ge" min="-1" max="1" step="1" class="w-full rounded-md shadow-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:border-[#8B0116] dark:focus:border-[#FF6B81] focus:ring focus:ring-[#8B0116] dark:focus:ring-[#FF6B81] focus:ring-opacity-50">
                        </div>
                        <div>
                            <label for="ro" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Robustheit (RO)</label>
                            <input type="number" name="attributes[ro]" id="ro" min="-1" max="1" step="1" class="w-full rounded-md shadow-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:border-[#8B0116] dark:focus:border-[#FF6B81] focus:ring focus:ring-[#8B0116] dark:focus:ring-[#FF6B81] focus:ring-opacity-50">
                        </div>
                        <div>
                            <label for="wi" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Willenskraft (WI)</label>
                            <input type="number" name="attributes[wi]" id="wi" min="-1" max="1" step="1" class="w-full rounded-md shadow-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:border-[#8B0116] dark:focus:border-[#FF6B81] focus:ring focus:ring-[#8B0116] dark:focus:ring-[#FF6B81] focus:ring-opacity-50">
                        </div>
                        <div>
                            <label for="wa" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Wahrnehmung (WA)</label>
                            <input type="number" name="attributes[wa]" id="wa" min="-1" max="1" step="1" class="w-full rounded-md shadow-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:border-[#8B0116] dark:focus:border-[#FF6B81] focus:ring focus:ring-[#8B0116] dark:focus:ring-[#FF6B81] focus:ring-opacity-50">
                        </div>
                        <div>
                            <label for="in" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Intelligenz (IN)</label>
                            <input type="number" name="attributes[in]" id="in" min="-1" max="1" step="1" class="w-full rounded-md shadow-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:border-[#8B0116] dark:focus:border-[#FF6B81] focus:ring focus:ring-[#8B0116] dark:focus:ring-[#FF6B81] focus:ring-opacity-50">
                        </div>
                        <div>
                            <label for="au" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Auftreten (AU)</label>
                            <input type="number" name="attributes[au]" id="au" min="-1" max="1" step="1" class="w-full rounded-md shadow-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:border-[#8B0116] dark:focus:border-[#FF6B81] focus:ring focus:ring-[#8B0116] dark:focus:ring-[#FF6B81] focus:ring-opacity-50">
                        </div>
                    </div>
                </div>

                <div class="mb-6">
                    <h2 class="text-xl font-semibold text-[#8B0116] dark:text-red-400 mb-2">Fertigkeiten</h2>
                    <p id="skill-points" class="text-sm text-gray-700 dark:text-gray-300 mb-2"></p>
                    <div id="barbar-combat-toggle" class="hidden mb-2">
                        <label for="barbar-combat-select" class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Barbar Kampfbonus</label>
                        <select id="barbar-combat-select" class="w-full sm:w-auto rounded-md shadow-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:border-[#8B0116] dark:focus:border-[#FF6B81] focus:ring focus:ring-[#8B0116] dark:focus:ring-[#FF6B81] focus:ring-opacity-50">
                            <option value="Nahkampf">Nahkampf (+1)</option>
                            <option value="Fernkampf">Fernkampf (+1)</option>
                        </select>
                    </div>
                    <div id="skills-container" class="space-y-2"></div>
                    <button type="button" id="add-skill" class="mt-2 inline-flex items-center px-3 py-1 bg-[#8B0116] dark:bg-red-400 text-white rounded-md">Fertigkeit hinzufügen</button>
                    <datalist id="skills-list">
                        <option value="Athletik" data-description="Klettern, Schwimmen, Laufen, Fitness; hilft beim Ausweichen. (ST, GE, RO)"></option>
                        <option value="Beruf" data-description="erlernter Beruf (Bauer, Schmied, Pilot, etc.), mehrere möglich. (GE, IN, AU)"></option>
                        <option value="Bildung" data-description="zivilisierte Ausbildung, Voraussetzung für Technik &amp; Waffen. (IN, WA)"></option>
                        <option value="Diebeskunst" data-description="Taschendiebstahl, Schlösser knacken, Diebesgut einschätzen. (GE, WA)"></option>
                        <option value="Fahren" data-description="Wagen, Boote, Fahrzeuge, auch High-Tech. (GE, WA)"></option>
                        <option value="Fernkampf" data-description="Speere, Bögen, Schleudern, Armbrüste usw. (GE, WA)"></option>
                        <option value="Feuerwaffen" data-description="Schuss- &amp; Energiewaffen. (GE, WA, abhängig von Bildung)"></option>
                        <option value="Handeln" data-description="Feilschen, Warenkenntnis, Handelsrouten. (AU, IN)"></option>
                        <option value="Heiler" data-description="Wundversorgung, Heilkunst, Rettung vor dem Tod. (IN)"></option>
                        <option value="Heimlichkeit" data-description="Schleichen, Verbergen. (GE)"></option>
                        <option value="Intuition" data-description="„sechster Sinn“, Gefahren erspüren (Alternative zu Bildung). (WA)"></option>
                        <option value="Kunde" data-description="Fachkenntnis in speziellen Bereichen (Regionen, Tiere, Pflanzen, Bräuche). (IN, WA)"></option>
                        <option value="Nahkampf" data-description="unbewaffneter Kampf und Nahkampfwaffen. (ST, GE)"></option>
                        <option value="Pilot" data-description="Flieger aller Art (vom Gleiter bis zum Jet). (GE, WA)"></option>
                        <option value="Reiten" data-description="Reittiere lenken und zähmen. (GE)"></option>
                        <option value="Sprachen" data-description="pro Punkt eine Sprache/Dialekt (mit Bildung auch Lesen/Schreiben). (IN)"></option>
                        <option value="Techniker" data-description="technische Geräte bedienen, warten, reparieren. (IN, GE)"></option>
                        <option value="Unterhalten" data-description="Geschichten, Musik, Tanz, Gaukeln, Schauspiel. (AU, IN, GE)"></option>
                        <option value="Überleben" data-description="Orientierung, Nahrung, Leben in der Wildnis. (RO, WA)"></option>
                        <option value="Wissenschaftler" data-description="wissenschaftliche Disziplinen (Physik, Chemie, Biologie ...). Maximalwert ≤ Bildung. (IN)"></option>
                    </datalist>
                </div>

                <div class="mb-6">
                    <h2 class="text-xl font-semibold text-[#8B0116] dark:text-red-400 mb-2">Besonderheiten</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="advantages" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Vorteile</label>
                            <select name="advantages[]" id="advantages" multiple aria-describedby="advantage-description" class="w-full rounded-md shadow-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:border-[#8B0116] dark:focus:border-[#FF6B81] focus:ring focus:ring-[#8B0116] dark:focus:ring-[#FF6B81] focus:ring-opacity-50">
                                <option value="Anführer" data-description="+2 auf Proben zum Befehlen/Überzeugen">Anführer</option>
                                <option value="Gestaltwandler" data-description="Körper/Stimme verändern">Gestaltwandler</option>
                                <option value="Gesteigertes Attribut" data-description="+1 auf ein Attribut nach Wahl (muss begründet werden (Mutation, Cyborg, Nanotech etc.))">Gesteigertes Attribut</option>
                                <option value="Gesteigerter Sinn" data-description="+3 auf WA-Proben für einen Sinn (muss begründet werden (Mutation, Cyborg, Nanotech etc.))">Gesteigerter Sinn</option>
                                <option value="High-Tech-Ausrüstung" data-description="4 High-Tech-Gegenstände (SL-Zustimmung)">High-Tech-Ausrüstung</option>
                                <option value="Kampfreflexe" data-description="+2 Bonus auf alle Ausweichen-Proben">Kampfreflexe</option>
                                <option value="Kaltblütig" data-description="+1 auf Verteidigungswürfe">Kaltblütig</option>
                                <option value="Kiemen" data-description="unbegrenzt unter Wasser atmen">Kiemen</option>
                                <option value="Kind zweier Welten" data-description="darf sowohl Bildung als auch Intuition lernen">Kind zweier Welten</option>
                                <option value="Nachtsicht" data-description="ohne Abzüge im Dunkeln sehen (muss begründet werden (Mutation, Cyborg, Nanotech etc.))">Nachtsicht</option>
                                <option value="Natürliche Waffen" data-description="+1 Schaden im Nahkampf (muss begründet werden (Mutation, Cyborg, Nanotech etc.))">Natürliche Waffen</option>
                                <option value="Panzerung" data-description="besitzt Schutzfaktor 1 (kumulativ) (muss begründet werden (Mutation, Cyborg, Nanotech etc.))">Panzerung</option>
                                <option value="Psychische Kraft" data-description="Zugriff auf psychische Kräfte (S. 37)">Psychische Kraft</option>
                                <option value="Psychisches Reservoir" data-description="höchster psychischer FW zählt doppelt bei PEP">Psychisches Reservoir</option>
                                <option value="Regeneration" data-description="heilt 10× schneller (stapelbar) (muss begründet werden (Mutation, Cyborg, Nanotech etc.))">Regeneration</option>
                                <option value="Scharfschütze" data-description="+1 auf Fernkampfangriffe, +1 Schaden auf kurze Distanz">Scharfschütze</option>
                                <option value="Schnell" data-description="+2 Grundbewegung, +1 Initiative (muss begründet werden (Mutation, Cyborg, Nanotech etc.))">Schnell</option>
                                <option value="Sprachbegabt" data-description="kann bis zu 3 Sprachen pro Fertigkeitspunkt lernen">Sprachbegabt</option>
                                <option value="Tiergefährte" data-description="treuer Tierbegleiter (SL-Zustimmung)">Tiergefährte</option>
                                <option value="Zäh" data-description="Schutzfaktor +1 durch Zähigkeit">Zäh</option>
                            </select>
                            <p id="advantage-description" class="mt-1 text-sm text-gray-600 dark:text-gray-400" aria-live="polite"></p>
                        </div>
                        <div>
                            <label for="disadvantages" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nachteile</label>
                            <select name="disadvantages[]" id="disadvantages" multiple aria-describedby="disadvantage-description" class="w-full rounded-md shadow-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:border-[#8B0116] dark:focus:border-[#FF6B81] focus:ring focus:ring-[#8B0116] dark:focus:ring-[#FF6B81] focus:ring-opacity-50">
                                <option value="Abergläubisch" data-description="mind. 3 abergläubische Eigenarten wählen">Abergläubisch</option>
                                <option value="Abhängige" data-description="muss Angehörige/Familie beschützen">Abhängige</option>
                                <option value="Anfälligkeit gegen Wahnsinn" data-description="geht bei Triggern in Wahnsinn über (SL übernimmt Figur)">Anfälligkeit gegen Wahnsinn</option>
                                <option value="Auffällig" data-description="stark erkennbar, −4 auf Verkleiden">Auffällig</option>
                                <option value="Blutdurst" data-description="braucht alle 24h frisches Blut, sonst −1 kumulativ auf Proben">Blutdurst</option>
                                <option value="Ehrenkodex" data-description="strenger Moralkodex, schränkt Handlungen ein">Ehrenkodex</option>
                                <option value="Feind" data-description="mächtiger Feind (Volk oder Person) bedroht das Leben ständig">Feind</option>
                            </select>
                            <p id="disadvantage-description" class="mt-1 text-sm text-gray-600 dark:text-gray-400" aria-live="polite"></p>
                        </div>
                    </div>
                </div>

                <div class="mb-6">
                    <h2 id="equipment-heading" class="text-xl font-semibold text-[#8B0116] dark:text-red-400 mb-2">Ausrüstung</h2>
                    <textarea name="equipment" id="equipment" rows="4" aria-labelledby="equipment-heading" class="w-full rounded-md shadow-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:border-[#8B0116] dark:focus:border-[#FF6B81] focus:ring focus:ring-[#8B0116] dark:focus:ring-[#FF6B81] focus:ring-opacity-50"></textarea>
                </div>

                <div class="flex justify-end">
                    <button id="submit-button" type="submit" disabled class="inline-flex items-center px-4 py-2 bg-gray-400 dark:bg-gray-600 border border-transparent rounded-md font-semibold text-white cursor-not-allowed">
                        Speichern
                    </button>
                </div>
            </fieldset>
            </form>
                    </div>
    </x-member-page>
</x-app-layout>

