@php
    $advantages = [
        'Anführer',
        'Gestaltwandler',
        'Gesteigertes Attribut',
        'Gesteigerter Sinn',
        'High-Tech-Ausrüstung',
        'Kampfreflexe',
        'Kaltblütig',
        'Kiemen',
        'Kind zweier Welten',
        'Nachtsicht',
        'Natürliche Waffen',
        'Panzerung',
        'Psychische Kraft',
        'Psychisches Reservoir',
        'Regeneration',
        'Scharfschütze',
        'Schnell',
        'Sprachbegabt',
        'Tiergefährte',
        'Zäh',
    ];

    $disadvantages = [
        'Abergläubisch',
        'Abhängige',
        'Anfälligkeit gegen Wahnsinn',
        'Auffällig',
        'Blutdurst',
        'Ehrenkodex',
        'Feind',
        'Primitiv',
        'Gejagt',
    ];
@endphp
<x-app-layout>
    <x-member-page class="max-w-4xl">
        <x-ui.page-header
            eyebrow="Adminbereich"
            title="Charakter-Editor"
            description="Erstelle und exportiere Charakterbögen mit Basisdaten, Attributen, Fertigkeiten und Ausrüstung in einer zusammenhängenden Editoransicht."
            data-testid="page-header"
        />

        <x-ui.panel title="Charakterdaten" description="Alle Pflichtfelder, Freischaltungen und Exportaktionen bleiben in einem einzigen Editorfluss gebündelt.">
            <form action="#" method="POST" enctype="multipart/form-data" x-data="charEditor()" data-testid="char-editor-form">
                @csrf

                <input type="hidden" name="player_name" :value="playerName" :disabled="!shouldMirrorBaseFields()">
                <input type="hidden" name="character_name" :value="characterName" :disabled="!shouldMirrorBaseFields()">
                <input type="hidden" name="race" :value="race" :disabled="!shouldMirrorBaseFields()">
                <input type="hidden" name="culture" :value="culture" :disabled="!shouldMirrorBaseFields()">
                <input type="hidden" name="portrait_data_url" :value="portraitPreview || ''" :disabled="!shouldSubmitPortraitPreview()">

                <input type="hidden" name="available_advantage_points" :value="freeAdvantagePoints()">
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
                            <option value="Hydrit">Hydrit</option>
                        </select>
                    </div>

                    <div :class="{ 'opacity-50': advancedUnlocked }">
                        <label for="culture" class="block text-sm font-medium text-base-content mb-1">Kultur</label>
                        <select name="culture" id="culture" class="select select-bordered w-full" x-model="culture" :disabled="advancedUnlocked">
                            <option value="" disabled>Kultur wählen</option>
                            <option value="Landbewohner" :disabled="!isCultureSelectable('Landbewohner')">Landbewohner</option>
                            <option value="Stadtbewohner" :disabled="!isCultureSelectable('Stadtbewohner')">Stadtbewohner</option>
                            <option value="Meeresbewohner" :disabled="!isCultureSelectable('Meeresbewohner')">Meeresbewohner</option>
                        </select>
                    </div>

                    <div class="md:col-span-2" :class="{ 'opacity-50': advancedUnlocked }">
                        <label for="portrait" class="block text-sm font-medium text-base-content mb-1">Porträt/Symbol</label>
                        <input type="file" name="portrait" id="portrait" accept="image/*" class="file-input file-input-bordered w-full" @change="handlePortraitUpload($event)" :disabled="advancedUnlocked">
                        <img x-show="portraitPreview" x-cloak :src="portraitPreview" class="mt-2 w-24 h-24 object-cover rounded border border-base-content/20" alt="Portrait Vorschau" data-testid="char-editor-portrait-preview">
                    </div>

                    <div class="md:col-span-2">
                        <h2 class="text-xl font-semibold text-primary mb-2">Beschreibung</h2>
                        <x-textarea name="description" id="description" rows="4" x-model="description" @input="descriptionUserEdited = true" />
                    </div>
                </div>

                <div class="flex justify-end mb-6" x-show="basicsFilled() && !advancedUnlocked" x-cloak>
                    <x-button type="button" label="Weiter, bei Wudan" class="btn-primary" @click="unlockAdvanced()" data-testid="char-editor-continue-button" />
                </div>

                <fieldset :disabled="!advancedUnlocked" :class="{ 'opacity-50': !advancedUnlocked }">
                    <div class="mb-6">
                        <h2 class="text-xl font-semibold text-primary mb-2">Attribute</h2>
                        <p class="text-sm text-base-content mb-2" x-text="'Verfügbare Attributspunkte: ' + apRemaining()"></p>
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                            @foreach(['st' => 'Stärke (ST)', 'ge' => 'Geschicklichkeit (GE)', 'ro' => 'Robustheit (RO)', 'wi' => 'Willenskraft (WI)', 'wa' => 'Wahrnehmung (WA)', 'in' => 'Intelligenz (IN)', 'au' => 'Auftreten (AU)'] as $attrId => $label)
                            <div>
                                <x-input type="number" label="{{ $label }}" name="attributes[{{ $attrId }}]" id="{{ $attrId }}" min="-1" x-bind:max="attributeMax()" step="1" x-model.number="attributes.{{ $attrId }}" @change="clampAttribute('{{ $attrId }}')" />
                            </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="mb-6">
                        <h2 class="text-xl font-semibold text-primary mb-2">Fertigkeiten</h2>
                        <p class="text-sm text-base-content mb-2" x-text="'Verfügbare Fertigkeitspunkte: ' + fpRemaining()"></p>
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
                        <div x-show="culture === 'Meeresbewohner'" class="mb-2 grid grid-cols-1 gap-2 sm:grid-cols-2">
                            <div>
                                <label for="sea-profession-select" class="text-sm font-medium text-base-content mb-1">Meeresbewohner Beruf-Bonus</label>
                                <select id="sea-profession-select" class="select select-bordered w-full" x-model="seaProfessionSkill" @change="setSeaProfessionSkill(seaProfessionSkill)">
                                    <option value="Beruf: Farmer">Beruf: Farmer (+1)</option>
                                    <option value="Beruf: Künstler">Beruf: Künstler (+1)</option>
                                </select>
                            </div>
                            <div>
                                <label for="sea-knowledge-combat-select" class="text-sm font-medium text-base-content mb-1">Meeresbewohner Zusatzbonus</label>
                                <select id="sea-knowledge-combat-select" class="select select-bordered w-full" x-model="seaKnowledgeOrCombatSkill" @change="setSeaKnowledgeOrCombatSkill(seaKnowledgeOrCombatSkill)">
                                    <option value="Wissenschaftler">Wissenschaftler (+1)</option>
                                    <option value="Techniker">Techniker (+1)</option>
                                    <option value="Nahkampf">Nahkampf (+1)</option>
                                </select>
                            </div>
                        </div>
                        <div class="space-y-2">
                            <template x-for="(skill, index) in skills" :key="index">
                                <div class="grid grid-cols-1 sm:grid-cols-4 gap-2 items-center">
                                    <input type="hidden"
                                        :name="'skills[' + index + '][name]'"
                                        :value="skill.name"
                                        :disabled="!shouldMirrorSkillName(skill)"
                                    >
                                    <input type="hidden"
                                        :name="'skills[' + index + '][value]'"
                                        :value="skill.value"
                                        :disabled="!shouldMirrorSkillValue(skill)"
                                    >
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
                        <x-button type="button" label="Fertigkeit hinzufügen" class="btn-primary btn-sm mt-2" @click="addSkill()" x-bind:disabled="fpRemaining() <= 0" />
                        <datalist id="skills-list">
                            <option value="Athletik"></option>
                            <option value="Beruf"></option>
                            <option value="Beruf: Farmer"></option>
                            <option value="Beruf: Künstler"></option>
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
                                <div class="flex flex-wrap items-baseline justify-between gap-2 mb-2">
                                    <h3 id="advantages-heading" class="text-sm font-medium text-base-content">Vorteile</h3>
                                    <p class="text-xs text-base-content/70" aria-live="polite" x-text="'Freie Vorteile: ' + freeAdvantagePoints()"></p>
                                </div>

                                <template x-for="disabledAdvantage in selectedDisabledAdvantages()" :key="'disabled-advantage-' + disabledAdvantage">
                                    <input type="hidden" name="advantages[]" :value="disabledAdvantage">
                                </template>

                                <div class="max-h-80 space-y-2 overflow-y-auto rounded-md border border-base-300 bg-base-200/40 p-2" role="group" aria-labelledby="advantages-heading" data-testid="char-editor-advantages-list">
                                    @foreach($advantages as $advantage)
                                        <label
                                            for="advantage-{{ $loop->index }}"
                                            class="flex min-h-12 items-start gap-3 rounded-md border border-base-300 bg-base-100 px-3 py-2 text-sm transition"
                                            :class="{ 'border-primary/60 bg-primary/5': selectedAdvantages.includes(@js($advantage)), 'opacity-60': isAdvantageDisabled(@js($advantage)), 'hover:border-primary/50': !isAdvantageDisabled(@js($advantage)) }"
                                        >
                                            <input
                                                type="checkbox"
                                                id="advantage-{{ $loop->index }}"
                                                name="advantages[]"
                                                value="{{ $advantage }}"
                                                class="checkbox checkbox-primary checkbox-sm mt-0.5 shrink-0"
                                                x-model="selectedAdvantages"
                                                :disabled="isAdvantageDisabled(@js($advantage))"
                                            >
                                            <span class="min-w-0 flex-1 leading-5">{{ $advantage }}</span>
                                            @if($advantage === 'Zäh')
                                                <span class="badge badge-primary badge-outline shrink-0">Pflicht</span>
                                            @else
                                                <template x-if="raceLocked.advantages.includes(@js($advantage))"><span class="badge badge-primary badge-outline shrink-0">Pflicht</span></template>
                                            @endif
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                            <div>
                                <div class="flex flex-wrap items-baseline justify-between gap-2 mb-2">
                                    <h3 id="disadvantages-heading" class="text-sm font-medium text-base-content">Nachteile</h3>
                                    <p class="text-xs text-base-content/70" aria-live="polite" x-text="'Gewählte Nachteile: ' + selectedDisadvantages.length + ' / benötigt: ' + chosenAdvantagesCount()"></p>
                                </div>

                                <template x-for="lockedDisadvantage in selectedLockedDisadvantages()" :key="'locked-disadvantage-' + lockedDisadvantage">
                                    <input type="hidden" name="disadvantages[]" :value="lockedDisadvantage">
                                </template>

                                <div class="max-h-80 space-y-2 overflow-y-auto rounded-md border border-base-300 bg-base-200/40 p-2" role="group" aria-labelledby="disadvantages-heading" data-testid="char-editor-disadvantages-list">
                                    @foreach($disadvantages as $disadvantage)
                                        <label
                                            for="disadvantage-{{ $loop->index }}"
                                            class="flex min-h-12 items-start gap-3 rounded-md border border-base-300 bg-base-100 px-3 py-2 text-sm transition"
                                            :class="{ 'border-primary/60 bg-primary/5': selectedDisadvantages.includes(@js($disadvantage)), 'opacity-60': isDisadvantageDisabled(@js($disadvantage)), 'hover:border-primary/50': !isDisadvantageDisabled(@js($disadvantage)) }"
                                        >
                                            <input
                                                type="checkbox"
                                                id="disadvantage-{{ $loop->index }}"
                                                name="disadvantages[]"
                                                value="{{ $disadvantage }}"
                                                class="checkbox checkbox-primary checkbox-sm mt-0.5 shrink-0"
                                                x-model="selectedDisadvantages"
                                                :disabled="isDisadvantageDisabled(@js($disadvantage))"
                                            >
                                            <span class="min-w-0 flex-1 leading-5">{{ $disadvantage }}</span>
                                            <template x-if="isDisadvantageDisabled(@js($disadvantage))"><span class="badge badge-primary badge-outline shrink-0">Pflicht</span></template>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="mb-6">
                        <h2 id="equipment-heading" class="text-xl font-semibold text-primary mb-2">Ausrüstung</h2>
                        <x-textarea name="equipment" id="equipment" rows="4" x-model="equipment" aria-labelledby="equipment-heading" />
                    </div>

                    <div class="flex justify-end space-x-2">
                        <x-button id="pdf-button" type="submit" formaction="{{ route('rpg.char-editor.pdf') }}" formtarget="_blank" x-bind:disabled="!formValid()" label="PDF drucken" icon="o-document-text" class="btn-ghost" data-testid="pdf-button" />
                        <x-button id="submit-button" type="submit" x-bind:disabled="!formValid()" label="Speichern" icon="o-check" class="btn-primary" data-testid="submit-button" />
                    </div>
                </fieldset>
            </form>
        </x-ui.panel>
    </x-member-page>
</x-app-layout>
