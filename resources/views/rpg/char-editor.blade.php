<x-app-layout>
    <x-member-page class="max-w-4xl">
        <x-header title="Charakter-Editor" separator data-testid="page-header" />

        <x-card shadow>
            <form action="#" method="POST" enctype="multipart/form-data" x-data="charEditor" data-testid="char-editor-form">
                @csrf

                <input type="hidden" name="available_advantage_points" :value="freeAdvantagePoints">
                <input type="hidden" name="figurenstaerke" value="1">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                    <div :class="{ 'opacity-50': advancedUnlocked }">
                        <x-input label="Spielername" name="player_name" x-model="playerName" x-bind:disabled="advancedUnlocked" />
                    </div>

                    <div :class="{ 'opacity-50': advancedUnlocked }">
                        <x-input label="Charaktername" name="character_name" x-model="characterName" x-bind:disabled="advancedUnlocked" />
                    </div>

                    <div :class="{ 'opacity-50': advancedUnlocked }">
                        <label for="race" class="block text-sm font-medium text-base-content mb-1">Rasse</label>
                        <select name="race" id="race" class="select select-bordered w-full" x-model="race" :disabled="advancedUnlocked">
                            <option value="" disabled>Rasse wählen</option>
                            <option value="Barbar">Barbar</option>
                            <option value="Guul">Guul</option>
                        </select>
                    </div>

                    <div :class="{ 'opacity-50': advancedUnlocked }">
                        <label for="culture" class="block text-sm font-medium text-base-content mb-1">Kultur</label>
                        <select name="culture" id="culture" class="select select-bordered w-full" x-model="culture" :disabled="advancedUnlocked">
                            <option value="" disabled>Kultur wählen</option>
                            <option value="Landbewohner">Landbewohner</option>
                            <option value="Stadtbewohner">Stadtbewohner</option>
                        </select>
                    </div>

                    <div class="md:col-span-2" :class="{ 'opacity-50': advancedUnlocked }">
                        <label for="portrait" class="block text-sm font-medium text-base-content mb-1">Porträt/Symbol</label>
                        <input type="file" name="portrait" id="portrait" accept="image/*" class="file-input file-input-bordered w-full" @change="handlePortraitUpload($event)" :disabled="advancedUnlocked">
                        <img x-show="portraitPreview" :src="portraitPreview" class="mt-2 w-24 h-24 object-cover rounded border border-base-content/20" alt="Portrait Vorschau">
                    </div>

                    <div class="md:col-span-2">
                        <h2 class="text-xl font-semibold text-primary mb-2">Beschreibung</h2>
                        <x-textarea name="description" id="description" rows="4" x-model="description" @input="descriptionUserEdited = true" />
                    </div>
                </div>

                <div class="flex justify-end mb-6" x-show="basicsFilled && !advancedUnlocked">
                    <x-button type="button" label="Weiter, bei Wudan" class="btn-primary" @click="unlockAdvanced()" />
                </div>

                <fieldset :disabled="!advancedUnlocked" :class="{ 'opacity-50': !advancedUnlocked }">
                    <div class="mb-6">
                        <h2 class="text-xl font-semibold text-primary mb-2">Attribute</h2>
                        <p class="text-sm text-base-content mb-2" x-text="'Verfügbare Attributspunkte: ' + apRemaining"></p>
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                            @foreach(['st' => 'Stärke (ST)', 'ge' => 'Geschicklichkeit (GE)', 'ro' => 'Robustheit (RO)', 'wi' => 'Willenskraft (WI)', 'wa' => 'Wahrnehmung (WA)', 'in' => 'Intelligenz (IN)', 'au' => 'Auftreten (AU)'] as $attrId => $label)
                            <div>
                                <x-input type="number" label="{{ $label }}" name="attributes[{{ $attrId }}]" id="{{ $attrId }}" min="-1" x-bind:max="attributeMax" step="1" x-model.number="attributes.{{ $attrId }}" @change="clampAttribute('{{ $attrId }}')" />
                            </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="mb-6">
                        <h2 class="text-xl font-semibold text-primary mb-2">Fertigkeiten</h2>
                        <p class="text-sm text-base-content mb-2" x-text="'Verfügbare Fertigkeitspunkte: ' + fpRemaining"></p>
                        <div x-show="race === 'Barbar'" class="mb-2">
                            <label for="barbar-combat-select" class="text-sm font-medium text-base-content mb-1">Barbar Kampfbonus</label>
                            <select id="barbar-combat-select" class="select select-bordered w-full sm:w-auto" x-model="barbarCombatSkill" @change="setBarbarCombatSkill(barbarCombatSkill)">
                                <option value="Nahkampf">Nahkampf (+1)</option>
                                <option value="Fernkampf">Fernkampf (+1)</option>
                            </select>
                        </div>
                        <div x-show="culture === 'Stadtbewohner'" class="mb-2">
                            <label for="city-skill-select" class="text-sm font-medium text-base-content mb-1">Stadtbewohner Bonus</label>
                            <select id="city-skill-select" class="select select-bordered w-full sm:w-auto" x-model="citySkill" @change="setCitySkill(citySkill)">
                                <option value="Unterhalten">Unterhalten (+1)</option>
                                <option value="Sprachen">Sprachen (+1)</option>
                            </select>
                        </div>
                        <div class="space-y-2">
                            <template x-for="(skill, index) in skills" :key="index">
                                <div class="grid grid-cols-1 sm:grid-cols-4 gap-2 items-center">
                                    <input type="text" list="skills-list"
                                        :name="'skills[' + index + '][name]'"
                                        class="skill-name sm:col-span-2 w-full rounded-md shadow-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:border-[#8B0116] dark:focus:border-[#FF6B81] focus:ring focus:ring-[#8B0116] dark:focus:ring-[#FF6B81] focus:ring-opacity-50"
                                        placeholder="Fertigkeit"
                                        x-model="skill.name"
                                        :disabled="skill.nameDisabled"
                                    >
                                    <input type="number"
                                        :name="'skills[' + index + '][value]'"
                                        class="w-full rounded-md shadow-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:border-[#8B0116] dark:focus:border-[#FF6B81] focus:ring focus:ring-[#8B0116] dark:focus:ring-[#FF6B81] focus:ring-opacity-50"
                                        placeholder="FW" step="1"
                                        x-model.number="skill.value"
                                        :min="getSkillMin(skill.name)"
                                        :max="getSkillMax(skill.name)"
                                        :disabled="isSkillDisabled(skill)"
                                        @change="clampSkillValue(skill)"
                                    >
                                    <template x-if="!skill.locked">
                                        <button type="button" class="px-2 py-1 bg-red-500 text-white rounded-md" @click="removeSkill(index)">-</button>
                                    </template>
                                    <template x-if="skill.badge">
                                        <span class="text-xs px-2 py-0.5 rounded bg-blue-200 dark:bg-blue-700 text-blue-800 dark:text-blue-200" x-text="skill.badge"></span>
                                    </template>
                                </div>
                            </template>
                        </div>
                        <x-button type="button" label="Fertigkeit hinzufügen" class="btn-primary btn-sm mt-2" @click="addSkill()" x-bind:disabled="fpRemaining <= 0" />
                        <datalist id="skills-list">
                            <option value="Athletik"></option>
                            <option value="Beruf"></option>
                            <option value="Bildung"></option>
                            <option value="Diebeskunst"></option>
                            <option value="Fahren"></option>
                            <option value="Fernkampf"></option>
                            <option value="Feuerwaffen"></option>
                            <option value="Handeln"></option>
                            <option value="Heiler"></option>
                            <option value="Heimlichkeit"></option>
                            <option value="Intuition"></option>
                            <option value="Kunde"></option>
                            <option value="Nahkampf"></option>
                            <option value="Natürliche Waffen"></option>
                            <option value="Pilot"></option>
                            <option value="Reiten"></option>
                            <option value="Sprachen"></option>
                            <option value="Techniker"></option>
                            <option value="Unterhalten"></option>
                            <option value="Überleben"></option>
                            <option value="Wissenschaftler"></option>
                        </datalist>
                    </div>

                    <div class="mb-6">
                        <h2 class="text-xl font-semibold text-primary mb-2">Besonderheiten</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="advantages" class="block text-sm font-medium text-base-content mb-1">Vorteile</label>
                                <select name="advantages[]" id="advantages" multiple class="select select-bordered w-full min-h-40" x-model="selectedAdvantages">
                                    <option value="Anführer" :disabled="isAdvantageDisabled('Anführer')">Anführer</option>
                                    <option value="Gestaltwandler" :disabled="isAdvantageDisabled('Gestaltwandler')">Gestaltwandler</option>
                                    <option value="Gesteigertes Attribut" :disabled="isAdvantageDisabled('Gesteigertes Attribut')">Gesteigertes Attribut</option>
                                    <option value="Gesteigerter Sinn" :disabled="isAdvantageDisabled('Gesteigerter Sinn')">Gesteigerter Sinn</option>
                                    <option value="High-Tech-Ausrüstung" :disabled="isAdvantageDisabled('High-Tech-Ausrüstung')">High-Tech-Ausrüstung</option>
                                    <option value="Kampfreflexe" :disabled="isAdvantageDisabled('Kampfreflexe')">Kampfreflexe</option>
                                    <option value="Kaltblütig" :disabled="isAdvantageDisabled('Kaltblütig')">Kaltblütig</option>
                                    <option value="Kiemen" :disabled="isAdvantageDisabled('Kiemen')">Kiemen</option>
                                    <option value="Kind zweier Welten" :disabled="isAdvantageDisabled('Kind zweier Welten')">Kind zweier Welten</option>
                                    <option value="Nachtsicht" :disabled="isAdvantageDisabled('Nachtsicht')">Nachtsicht</option>
                                    <option value="Natürliche Waffen" :disabled="isAdvantageDisabled('Natürliche Waffen')">Natürliche Waffen</option>
                                    <option value="Panzerung" :disabled="isAdvantageDisabled('Panzerung')">Panzerung</option>
                                    <option value="Psychische Kraft" :disabled="isAdvantageDisabled('Psychische Kraft')">Psychische Kraft</option>
                                    <option value="Psychisches Reservoir" :disabled="isAdvantageDisabled('Psychisches Reservoir')">Psychisches Reservoir</option>
                                    <option value="Regeneration" :disabled="isAdvantageDisabled('Regeneration')">Regeneration</option>
                                    <option value="Scharfschütze" :disabled="isAdvantageDisabled('Scharfschütze')">Scharfschütze</option>
                                    <option value="Schnell" :disabled="isAdvantageDisabled('Schnell')">Schnell</option>
                                    <option value="Sprachbegabt" :disabled="isAdvantageDisabled('Sprachbegabt')">Sprachbegabt</option>
                                    <option value="Tiergefährte" :disabled="isAdvantageDisabled('Tiergefährte')">Tiergefährte</option>
                                    <option value="Zäh" :disabled="isAdvantageDisabled('Zäh')" selected>Zäh</option>
                                </select>
                            </div>
                            <div>
                                <label for="disadvantages" class="block text-sm font-medium text-base-content mb-1">Nachteile</label>
                                <select name="disadvantages[]" id="disadvantages" multiple class="select select-bordered w-full min-h-40" x-model="selectedDisadvantages">
                                    <option value="Abergläubisch" :disabled="isDisadvantageDisabled('Abergläubisch')">Abergläubisch</option>
                                    <option value="Abhängige" :disabled="isDisadvantageDisabled('Abhängige')">Abhängige</option>
                                    <option value="Anfälligkeit gegen Wahnsinn" :disabled="isDisadvantageDisabled('Anfälligkeit gegen Wahnsinn')">Anfälligkeit gegen Wahnsinn</option>
                                    <option value="Auffällig" :disabled="isDisadvantageDisabled('Auffällig')">Auffällig</option>
                                    <option value="Blutdurst" :disabled="isDisadvantageDisabled('Blutdurst')">Blutdurst</option>
                                    <option value="Ehrenkodex" :disabled="isDisadvantageDisabled('Ehrenkodex')">Ehrenkodex</option>
                                    <option value="Feind" :disabled="isDisadvantageDisabled('Feind')">Feind</option>
                                    <option value="Primitiv" :disabled="isDisadvantageDisabled('Primitiv')">Primitiv</option>
                                    <option value="Gejagt" :disabled="isDisadvantageDisabled('Gejagt')">Gejagt</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="mb-6">
                        <h2 id="equipment-heading" class="text-xl font-semibold text-primary mb-2">Ausrüstung</h2>
                        <x-textarea name="equipment" id="equipment" rows="4" x-model="equipment" aria-labelledby="equipment-heading" />
                    </div>

                    <div class="flex justify-end space-x-2">
                        <x-button id="pdf-button" type="submit" formaction="{{ route('rpg.char-editor.pdf') }}" formtarget="_blank" x-bind:disabled="!formValid" label="PDF drucken" icon="o-document-text" class="btn-ghost" data-testid="pdf-button" />
                        <x-button id="submit-button" type="submit" x-bind:disabled="!formValid" label="Speichern" icon="o-check" class="btn-primary" data-testid="submit-button" />
                    </div>
                </fieldset>
            </form>
        </x-card>
    </x-member-page>
</x-app-layout>
