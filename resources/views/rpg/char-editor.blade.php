<x-app-layout>
    <x-member-page class="max-w-4xl">
        <x-header title="Charakter-Editor" separator data-testid="page-header" />

        <x-card shadow>
            <form action="#" method="POST" enctype="multipart/form-data" data-char-editor data-testid="char-editor-form">
                @csrf

                <input type="hidden" name="available_advantage_points" id="available_advantage_points" value="2">
                <input type="hidden" name="figurenstaerke" id="figurenstaerke" value="1">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                    <div data-lockable>
                        <x-input label="Spielername" name="player_name" id="player_name" />
                    </div>

                    <div data-lockable>
                        <x-input label="Charaktername" name="character_name" id="character_name" />
                    </div>

                    <div data-lockable>
                        <label for="race" class="block text-sm font-medium text-base-content mb-1">Rasse</label>
                        <select name="race" id="race" class="select select-bordered w-full">
                            <option value="" disabled selected>Rasse wählen</option>
                            <option value="Barbar" title="Im 26. Jahrhundert besteht die Zivilisation zum größten Teil aus Barbaren. Sie leben in unterschiedlichen Kulturen, beispielsweise als Seefahrer (die Disuuslachter), Nomaden (die Wandernden Völker) oder Ruinenbewohner (die Loords von Landán). Die zeichnen sich durch Zähigkeit, Wildheit und Kampflust aus, sind zumeist primitiv und leben in Clans. Ehre und Mut werden hoch geschätzt. Technologisch bewegen sich die meisten Barbaren zwischen der späten Steinzeit und dem frühen Mittelalter.">Barbar</option>
                            <option value="Guul" title="Guule sind bedauernswerte Mutationen des Homo Sapiens. Sie sind dürr, fast zwei Meter groß und völlig unbehaart. Ihre langen knochigen Arme enden in Krallen. Die verhornten Füße laufen an den Fersen in einem fingerdicken Stachel aus. Aus dem Maul tropft weißlicher Schleim, was ihr abstoßendes Äußeres zusätzlich verstärkt. Guule sind meist nur mit einem Lendenschurz bekleidet. Sie ernähren sich von Aas und Gebeinen, die sie u.a. aus Gräbern holen.">Guul</option>
                        </select>
                    </div>

                    <div data-lockable>
                        <label for="culture" class="block text-sm font-medium text-base-content mb-1">Kultur</label>
                        <select name="culture" id="culture" class="select select-bordered w-full">
                            <option value="" disabled selected>Kultur wählen</option>
                            <option value="Landbewohner" title="Landbewohner bewirtschaften den Boden und versuchen als Bauern und Viehzüchter ihren Lebensunterhalt zu verdienen. Die meisten sind einfache Menschen, die Ruhe und Frieden suchen, nicht viel von der Welt wissen und einfache Landgötter anbeten. Aberglauben ist weit verbreitet.">Landbewohner</option>
                            <option value="Stadtbewohner" title="Stadtbewohner versuchen in der dunklen Zukunft der Erde neues Leben erblühen zu lassen. Dazu haben sie sich in neu erbauten Siedlungen (zuweilen auf Ruinen aus der Zeit vor dem Kometen) angesiedelt und leben als Händler, Handwerker und Bauern. Die Mauern ihrer Siedlungen schützen sie vor den Gefahren der Wildnis. Ihre Siedlungen sind somit Lichter der Hoffnung in der Dunkelheit.">Stadtbewohner</option>
                        </select>
                    </div>

                    <div class="md:col-span-2" data-lockable>
                        <label for="portrait" class="block text-sm font-medium text-base-content mb-1">Porträt/Symbol</label>
                        <input type="file" name="portrait" id="portrait" accept="image/*" class="file-input file-input-bordered w-full">
                        <img id="portrait-preview" class="hidden mt-2 w-24 h-24 object-cover rounded border border-base-content/20" alt="Portrait Vorschau">
                    </div>

                    <div class="md:col-span-2">
                        <h2 class="text-xl font-semibold text-primary mb-2">Beschreibung</h2>
                        <x-textarea name="description" id="description" rows="4" />
                    </div>
                </div>

                <div class="flex justify-end mb-6">
                    <x-button id="continue-button" type="button" label="Weiter, bei Wudan" class="btn-primary hidden" />
                </div>

                <fieldset id="advanced-fields" disabled class="opacity-50">
                    <div class="mb-6">
                        <h2 class="text-xl font-semibold text-primary mb-2">Attribute</h2>
                        <p id="attribute-points" class="text-sm text-base-content mb-2"></p>
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                            <div>
                                <x-input type="number" label="Stärke (ST)" name="attributes[st]" id="st" min="-1" max="1" step="1" />
                            </div>
                            <div>
                                <x-input type="number" label="Geschicklichkeit (GE)" name="attributes[ge]" id="ge" min="-1" max="1" step="1" />
                            </div>
                            <div>
                                <x-input type="number" label="Robustheit (RO)" name="attributes[ro]" id="ro" min="-1" max="1" step="1" />
                            </div>
                            <div>
                                <x-input type="number" label="Willenskraft (WI)" name="attributes[wi]" id="wi" min="-1" max="1" step="1" />
                            </div>
                            <div>
                                <x-input type="number" label="Wahrnehmung (WA)" name="attributes[wa]" id="wa" min="-1" max="1" step="1" />
                            </div>
                            <div>
                                <x-input type="number" label="Intelligenz (IN)" name="attributes[in]" id="in" min="-1" max="1" step="1" />
                            </div>
                            <div>
                                <x-input type="number" label="Auftreten (AU)" name="attributes[au]" id="au" min="-1" max="1" step="1" />
                            </div>
                        </div>
                    </div>

                    <div class="mb-6">
                        <h2 class="text-xl font-semibold text-primary mb-2">Fertigkeiten</h2>
                        <p id="skill-points" class="text-sm text-base-content mb-2"></p>
                        <div id="barbar-combat-toggle" class="hidden mb-2">
                            <label for="barbar-combat-select" class="text-sm font-medium text-base-content mb-1">Barbar Kampfbonus</label>
                            <select id="barbar-combat-select" class="select select-bordered w-full sm:w-auto">
                                <option value="Nahkampf">Nahkampf (+1)</option>
                                <option value="Fernkampf">Fernkampf (+1)</option>
                            </select>
                        </div>
                        <div id="city-skill-toggle" class="hidden mb-2">
                            <label for="city-skill-select" class="text-sm font-medium text-base-content mb-1">Stadtbewohner Bonus</label>
                            <select id="city-skill-select" class="select select-bordered w-full sm:w-auto">
                                <option value="Unterhalten">Unterhalten (+1)</option>
                                <option value="Sprachen">Sprachen (+1)</option>
                            </select>
                        </div>
                        <div id="skills-container" class="space-y-2"></div>
                        <x-button type="button" id="add-skill" label="Fertigkeit hinzufügen" class="btn-primary btn-sm mt-2" />
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
                            <option value="Intuition" data-description="„sechster Sinn", Gefahren erspüren (Alternative zu Bildung). (WA)"></option>
                            <option value="Kunde" data-description="Fachkenntnis in speziellen Bereichen (Regionen, Tiere, Pflanzen, Bräuche). (IN, WA)"></option>
                            <option value="Nahkampf" data-description="unbewaffneter Kampf und Nahkampfwaffen. (ST, GE)"></option>
                            <option value="Natürliche Waffen" data-description="Schläge, Bisse, Krallen und andere natürliche Angriffe. (ST, GE)"></option>
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
                        <h2 class="text-xl font-semibold text-primary mb-2">Besonderheiten</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="advantages" class="block text-sm font-medium text-base-content mb-1">Vorteile</label>
                                <select name="advantages[]" id="advantages" multiple aria-describedby="advantage-description" class="select select-bordered w-full min-h-40">
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
                                <p id="advantage-description" class="mt-1 text-sm text-base-content/60" aria-live="polite"></p>
                            </div>
                            <div>
                                <label for="disadvantages" class="block text-sm font-medium text-base-content mb-1">Nachteile</label>
                                <select name="disadvantages[]" id="disadvantages" multiple aria-describedby="disadvantage-description" class="select select-bordered w-full min-h-40">
                                    <option value="Abergläubisch" data-description="mind. 3 abergläubische Eigenarten wählen">Abergläubisch</option>
                                    <option value="Abhängige" data-description="muss Angehörige/Familie beschützen">Abhängige</option>
                                    <option value="Anfälligkeit gegen Wahnsinn" data-description="geht bei Triggern in Wahnsinn über (SL übernimmt Figur)">Anfälligkeit gegen Wahnsinn</option>
                                    <option value="Auffällig" data-description="stark erkennbar, −4 auf Verkleiden">Auffällig</option>
                                    <option value="Blutdurst" data-description="braucht alle 24h frisches Blut, sonst −1 kumulativ auf Proben">Blutdurst</option>
                                    <option value="Ehrenkodex" data-description="strenger Moralkodex, schränkt Handlungen ein">Ehrenkodex</option>
                                    <option value="Feind" data-description="mächtiger Feind (Volk oder Person) bedroht das Leben ständig">Feind</option>
                                    <option value="Primitiv" data-description="kann keine komplexe Technologie nutzen">Primitiv</option>
                                    <option value="Gejagt" data-description="wird von Feinden verfolgt">Gejagt</option>
                                </select>
                                <p id="disadvantage-description" class="mt-1 text-sm text-base-content/60" aria-live="polite"></p>
                            </div>
                        </div>
                    </div>

                    <div class="mb-6">
                        <h2 id="equipment-heading" class="text-xl font-semibold text-primary mb-2">Ausrüstung</h2>
                        <x-textarea name="equipment" id="equipment" rows="4" aria-labelledby="equipment-heading" />
                    </div>

                    <div class="flex justify-end space-x-2">
                        <x-button id="pdf-button" type="submit" formaction="{{ route('rpg.char-editor.pdf') }}" formtarget="_blank" disabled label="PDF drucken" icon="o-document-text" class="btn-ghost" data-testid="pdf-button" />
                        <x-button id="submit-button" type="submit" disabled label="Speichern" icon="o-check" class="btn-primary" data-testid="submit-button" />
                    </div>
                </fieldset>
            </form>
        </x-card>
    </x-member-page>
</x-app-layout>
