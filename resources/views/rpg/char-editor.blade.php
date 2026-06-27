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
        'Gejagt',
        'Lichtscheu',
        'Primitiv',
        'Taratzenfutter',
        'Tödliche Immunschwäche',
        'Verpflichtung',
        'Verwundbarkeit',
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
                <input type="hidden" name="gender" :value="gender" :disabled="!shouldMirrorBaseFields()">
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
                        <label for="gender" class="block text-sm font-medium text-base-content mb-1">Geschlecht</label>
                        <select name="gender" id="gender" class="select select-bordered w-full" x-model="gender" :disabled="advancedUnlocked">
                            <option value="" disabled>Geschlecht wählen</option>
                            <option value="weiblich">Weiblich</option>
                            <option value="maennlich">Männlich</option>
                            <option value="divers">Divers / keine Angabe</option>
                        </select>
                    </div>

                    <div :class="{ 'opacity-50': advancedUnlocked }">
                        <label for="race" class="block text-sm font-medium text-base-content mb-1">Rasse</label>
                        <select name="race" id="race" class="select select-bordered w-full" x-model="race" :disabled="advancedUnlocked" @focus="setRaceInfoPreview(race)" @input="setRaceInfoPreview($event.target.value)" @change="setRaceInfoPreview($event.target.value)" @blur="clearRaceInfoPreview()" x-bind:aria-describedby="raceInfo() ? 'race-info-panel' : null">
                            <option value="" disabled>Rasse wählen</option>
                            <option value="Barbar" :disabled="!isRaceSelectable('Barbar')">Barbar</option>
                            <option value="Guul" :disabled="!isRaceSelectable('Guul')">Guul</option>
                            <option value="Hydrit" :disabled="!isRaceSelectable('Hydrit')">Hydrit</option>
                            <option value="Nosfera" :disabled="!isRaceSelectable('Nosfera')">Nosfera</option>
                            <option value="Taratze" :disabled="!isRaceSelectable('Taratze')">Taratze</option>
                            <option value="Wulfane" :disabled="!isRaceSelectable('Wulfane')">Wulfane</option>
                            <option value="Techno" :disabled="!isRaceSelectable('Techno')">Techno</option>
                            <option value="Präkristofluu" :disabled="!isRaceSelectable('Präkristofluu')">Präkristofluu</option>
                        </select>
                        <template x-if="raceInfo()">
                            <div id="race-info-panel" class="mt-3 rounded-md border border-base-300 bg-base-200/40 p-3 text-sm" data-testid="race-info-panel" aria-live="polite">
                                <div class="flex flex-wrap items-baseline justify-between gap-2">
                                    <h3 class="font-semibold text-base-content" x-text="raceInfo().name"></h3>
                                    <span class="text-xs text-base-content/70" x-text="raceInfo().attributes"></span>
                                </div>
                                <p class="mt-2 leading-5 text-base-content/80" x-text="raceInfo().description"></p>
                                <dl class="mt-3 grid grid-cols-1 gap-2">
                                    <template x-for="row in raceInfoRows()" :key="row.label">
                                        <div class="grid grid-cols-1 gap-1 sm:grid-cols-[8rem_1fr]">
                                            <dt class="font-medium text-base-content" x-text="row.label"></dt>
                                            <dd class="text-base-content/80" x-text="row.value"></dd>
                                        </div>
                                    </template>
                                </dl>
                            </div>
                        </template>
                    </div>

                    <div :class="{ 'opacity-50': advancedUnlocked }">
                        <label for="culture" class="block text-sm font-medium text-base-content mb-1">Kultur</label>
                        <select name="culture" id="culture" class="select select-bordered w-full" x-model="culture" :disabled="advancedUnlocked">
                            <option value="" disabled>Kultur wählen</option>
                            <option value="Landbewohner" :disabled="!isCultureSelectable('Landbewohner')">Landbewohner</option>
                            <option value="Stadtbewohner" :disabled="!isCultureSelectable('Stadtbewohner')">Stadtbewohner</option>
                            <option value="Meeresbewohner" :disabled="!isCultureSelectable('Meeresbewohner')">Meeresbewohner</option>
                            <option value="Bunkermensch" :disabled="!isCultureSelectable('Bunkermensch')">Bunkermensch</option>
                            <option value="Mensch des 21. Jahrhunderts" :disabled="!isCultureSelectable('Mensch des 21. Jahrhunderts')">Mensch des 21. Jahrhunderts</option>
                            <option value="Nomade" :disabled="!isCultureSelectable('Nomade')">Nomade</option>
                            <option value="Disuuslachter (Nordmann)" :disabled="!isCultureSelectable('Disuuslachter (Nordmann)')">Disuuslachter (Nordmann)</option>
                            <option value="Ruinenbewohner" :disabled="!isCultureSelectable('Ruinenbewohner')">Ruinenbewohner</option>
                            <option value="Untergrundbewohner" :disabled="!isCultureSelectable('Untergrundbewohner')">Untergrundbewohner</option>
                            <option value="Volk der 13 Inseln" :disabled="!isCultureSelectable('Volk der 13 Inseln')">Volk der 13 Inseln</option>
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
                        <div x-show="race === 'Barbar'" class="mb-3">
                            <label for="barbar-attribute-select" class="text-sm font-medium text-base-content mb-1">Barbar Attributbonus</label>
                            <select id="barbar-attribute-select" class="select select-bordered w-full sm:w-auto" x-model="barbarAttributeBonus" @change="setBarbarAttributeBonus(barbarAttributeBonus)">
                                <template x-for="attributeOption in attributeOptions" :key="'barbar-attribute-' + attributeOption.id">
                                    <option :value="attributeOption.id" x-text="attributeOption.label + ' (+1)'"></option>
                                </template>
                            </select>
                        </div>
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                            @foreach(['st' => 'Stärke (ST)', 'ge' => 'Geschicklichkeit (GE)', 'ro' => 'Robustheit (RO)', 'wi' => 'Willenskraft (WI)', 'wa' => 'Wahrnehmung (WA)', 'in' => 'Intelligenz (IN)', 'au' => 'Auftreten (AU)'] as $attrId => $label)
                            <div>
                                <x-input type="number" label="{{ $label }}" name="attributes[{{ $attrId }}]" id="{{ $attrId }}" x-bind:min="getAttributeMin('{{ $attrId }}')" x-bind:max="getAttributeMax('{{ $attrId }}')" step="1" x-model.number="attributes.{{ $attrId }}" @change="clampAttribute('{{ $attrId }}')" />
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
                        <div x-show="race === 'Techno'" class="mb-3 rounded-md border border-base-300 bg-base-200/40 p-3">
                            <div class="mb-2 flex flex-wrap items-baseline justify-between gap-2">
                                <h3 class="text-sm font-medium text-base-content">Techno-Rassenpunkte</h3>
                                <p class="text-xs text-base-content/70" aria-live="polite" x-text="'Verteilt: ' + technoPoolUsed() + ' / ' + technoSkillPoolPoints"></p>
                            </div>
                            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3">
                                <template x-for="skillName in technoSkillNames" :key="'techno-skill-' + skillName">
                                    <label class="flex min-h-12 items-center justify-between gap-3 rounded-md border border-base-300 bg-base-100 px-3 py-2 text-sm">
                                        <span class="min-w-0 flex-1" x-text="skillName"></span>
                                        <input type="number" min="0" x-bind:max="base.maxFW" step="1" class="input input-bordered input-sm w-20" x-model.number="technoSkillPoints[skillName]" @input="setTechnoSkillPoints(skillName, technoSkillPoints[skillName])" @change="setTechnoSkillPoints(skillName, technoSkillPoints[skillName])" data-testid="techno-skill-points-input">
                                    </label>
                                </template>
                            </div>
                        </div>
                        <div x-show="race === 'Präkristofluu'" class="mb-3 rounded-md border border-base-300 bg-base-200/40 p-3">
                            <div class="mb-2 flex flex-wrap items-baseline justify-between gap-2">
                                <h3 class="text-sm font-medium text-base-content">Präkristofluu-Rassenpunkte</h3>
                                <p class="text-xs text-base-content/70" aria-live="polite" x-text="'Verteilt: ' + praekristofluuPoolUsed() + ' / ' + praekristofluuSkillPoolPoints"></p>
                            </div>
                            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3">
                                <template x-for="skillName in praekristofluuSkillNames" :key="'praekristofluu-skill-' + skillName">
                                    <label class="flex min-h-12 items-center justify-between gap-3 rounded-md border border-base-300 bg-base-100 px-3 py-2 text-sm">
                                        <span class="min-w-0 flex-1" x-text="skillName"></span>
                                        <input type="number" min="0" x-bind:max="base.maxFW" step="1" class="input input-bordered input-sm w-20" x-model.number="praekristofluuSkillPoints[skillName]" @input="setPraekristofluuSkillPoints(skillName, praekristofluuSkillPoints[skillName])" @change="setPraekristofluuSkillPoints(skillName, praekristofluuSkillPoints[skillName])" data-testid="praekristofluu-skill-points-input">
                                    </label>
                                </template>
                            </div>
                        </div>
                        <div x-show="culture === 'Landbewohner'" class="mb-2">
                            <label for="landbewohner-profession-select" class="text-sm font-medium text-base-content mb-1">Landbewohner Beruf-Bonus</label>
                            <select id="landbewohner-profession-select" class="select select-bordered w-full sm:w-auto" x-model="landbewohnerProfessionSkill" @change="setLandbewohnerProfessionSkill(landbewohnerProfessionSkill)">
                                <option value="Beruf: Viehzüchter">Beruf: Viehzüchter (+2)</option>
                                <option value="Beruf: Landwirt">Beruf: Landwirt (+2)</option>
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
                        <div x-show="culture === 'Bunkermensch'" class="mb-2">
                            <label for="bunkermensch-bonus-select" class="text-sm font-medium text-base-content mb-1">Bunkermensch Zusatzbonus</label>
                            <select id="bunkermensch-bonus-select" class="select select-bordered w-full sm:w-auto" x-model="bunkermenschBonusSkill" @change="setBunkermenschBonusSkill(bunkermenschBonusSkill)">
                                <option value="Feuerwaffen">Feuerwaffen (+1)</option>
                                <option value="Pilot">Pilot (+1)</option>
                                <option value="Wissenschaftler">Wissenschaftler (+1)</option>
                            </select>
                        </div>
                        <div x-show="culture === 'Mensch des 21. Jahrhunderts'" class="mb-2 grid grid-cols-1 gap-2 sm:grid-cols-2">
                            <div>
                                <label for="mensch-21-first-bonus-select" class="text-sm font-medium text-base-content mb-1">21. Jahrhundert Bonus 1</label>
                                <select id="mensch-21-first-bonus-select" class="select select-bordered w-full" x-model="mensch21FirstBonusSkill" @change="setMensch21FirstBonusSkill(mensch21FirstBonusSkill)">
                                    <option value="Bildung" :disabled="mensch21SecondBonusSkill === 'Bildung'">Bildung (+1)</option>
                                    <option value="Pilot" :disabled="mensch21SecondBonusSkill === 'Pilot'">Pilot (+1)</option>
                                    <option value="Techniker" :disabled="mensch21SecondBonusSkill === 'Techniker'">Techniker (+1)</option>
                                    <option value="Wissenschaftler" :disabled="mensch21SecondBonusSkill === 'Wissenschaftler'">Wissenschaftler (+1)</option>
                                </select>
                            </div>
                            <div>
                                <label for="mensch-21-second-bonus-select" class="text-sm font-medium text-base-content mb-1">21. Jahrhundert Bonus 2</label>
                                <select id="mensch-21-second-bonus-select" class="select select-bordered w-full" x-model="mensch21SecondBonusSkill" @change="setMensch21SecondBonusSkill(mensch21SecondBonusSkill)">
                                    <option value="Bildung" :disabled="mensch21FirstBonusSkill === 'Bildung'">Bildung (+1)</option>
                                    <option value="Pilot" :disabled="mensch21FirstBonusSkill === 'Pilot'">Pilot (+1)</option>
                                    <option value="Techniker" :disabled="mensch21FirstBonusSkill === 'Techniker'">Techniker (+1)</option>
                                    <option value="Wissenschaftler" :disabled="mensch21FirstBonusSkill === 'Wissenschaftler'">Wissenschaftler (+1)</option>
                                </select>
                            </div>
                        </div>
                        <div x-show="culture === 'Nomade'" class="mb-2 grid grid-cols-1 gap-2 sm:grid-cols-2">
                            <div>
                                <label for="nomade-combat-select" class="text-sm font-medium text-base-content mb-1">Nomade Kampfbonus</label>
                                <select id="nomade-combat-select" class="select select-bordered w-full" x-model="nomadeCombatSkill" @change="setNomadeCombatSkill(nomadeCombatSkill)">
                                    <option value="Nahkampf">Nahkampf (+1)</option>
                                    <option value="Fernkampf">Fernkampf (+1)</option>
                                </select>
                            </div>
                            <div>
                                <label for="nomade-movement-select" class="text-sm font-medium text-base-content mb-1">Nomade Bewegungsbonus</label>
                                <select id="nomade-movement-select" class="select select-bordered w-full" x-model="nomadeMovementSkill" @change="setNomadeMovementSkill(nomadeMovementSkill)">
                                    <option value="Reiten">Reiten (+1)</option>
                                    <option value="Athletik">Athletik (+1)</option>
                                </select>
                            </div>
                        </div>
                        <div x-show="culture === 'Ruinenbewohner'" class="mb-2">
                            <label for="ruinenbewohner-bonus-select" class="text-sm font-medium text-base-content mb-1">Ruinenbewohner Zusatzbonus</label>
                            <select id="ruinenbewohner-bonus-select" class="select select-bordered w-full sm:w-auto" x-model="ruinenbewohnerBonusSkill" @change="setRuinenbewohnerBonusSkill(ruinenbewohnerBonusSkill)">
                                <option value="Nahkampf">Nahkampf (+1)</option>
                                <option value="Fernkampf">Fernkampf (+1)</option>
                                <option value="Athletik">Athletik (+1)</option>
                                <option value="Kunde">Kunde (+1)</option>
                            </select>
                        </div>
                        <div x-show="culture === 'Volk der 13 Inseln'" class="mb-2">
                            <label for="volk-13-profession-select" class="text-sm font-medium text-base-content mb-1">Volk der 13 Inseln Beruf-Bonus</label>
                            <select id="volk-13-profession-select" class="select select-bordered w-full sm:w-auto" x-model="volkDer13InselnProfessionSkill" @change="setVolkDer13InselnProfessionSkill(volkDer13InselnProfessionSkill)">
                                <option value="Beruf: Bauer">Beruf: Bauer (+1)</option>
                                <option value="Beruf: Fischer">Beruf: Fischer (+1)</option>
                            </select>
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
                            <option value="Beruf: Bauer"></option>
                            <option value="Beruf: Bergmann"></option>
                            <option value="Beruf: Landwirt"></option>
                            <option value="Beruf: Seemann"></option>
                            <option value="Beruf: Fischer"></option>
                            <option value="Beruf: Farmer"></option>
                            <option value="Beruf: Künstler"></option>
                            <option value="Beruf: Viehzüchter"></option>
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
                            <option value="Kunde: Wetter"></option>
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
                        <div class="mb-3 flex flex-wrap items-center gap-2">
                            <x-button type="button" label="Vorteil auswürfeln" class="btn-secondary btn-sm" @click="rollSpecial('advantage')" data-testid="roll-advantage-button" />
                            <x-button type="button" label="Nachteil auswürfeln" class="btn-secondary btn-sm" @click="rollSpecial('disadvantage')" data-testid="roll-disadvantage-button" />
                            <p x-show="lastRoll" x-cloak class="text-xs text-base-content/70" aria-live="polite" data-testid="char-editor-roll-result" x-text="'W66 ' + lastRoll.value + ' (' + lastRoll.dice + '): ' + lastRoll.message"></p>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <div class="flex flex-wrap items-baseline justify-between gap-2 mb-2">
                                    <h3 id="advantages-heading" class="text-sm font-medium text-base-content">Vorteile</h3>
                                    <p class="text-xs text-base-content/70" aria-live="polite" x-text="'Freie Vorteile: ' + freeAdvantagePoints()"></p>
                                </div>

                                <template x-for="disabledAdvantage in selectedDisabledAdvantages()" :key="'disabled-advantage-' + disabledAdvantage">
                                    <input type="hidden" name="advantages[]" :value="disabledAdvantage">
                                </template>

                                <div class="max-h-96 space-y-2 overflow-y-auto rounded-md border border-base-300 bg-base-200/40 p-2" role="group" aria-labelledby="advantages-heading" data-testid="char-editor-advantages-list">
                                    @foreach($advantages as $advantage)
                                        <div
                                            class="rounded-md border border-base-300 bg-base-100 text-sm transition"
                                            :class="{ 'border-primary/60 bg-primary/5': selectedAdvantages.includes(@js($advantage)), 'opacity-60': isAdvantageDisabled(@js($advantage)), 'hover:border-primary/50': !isAdvantageDisabled(@js($advantage)) }"
                                            :title="advantageTooltip(@js($advantage))"
                                        >
                                            <label for="advantage-{{ $loop->index }}" class="flex min-h-12 items-start gap-3 px-3 py-2">
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
                                                <span class="badge badge-ghost shrink-0" x-text="advantageRollLabel(@js($advantage))"></span>
                                                <template x-if="advantageCost(@js($advantage)) > 1">
                                                    <span class="badge badge-warning badge-outline shrink-0" x-text="'Kosten ' + advantageCost(@js($advantage))"></span>
                                                </template>
                                                <template x-if="advantageLockLabel(@js($advantage))">
                                                    <span class="badge badge-primary badge-outline shrink-0" x-text="advantageLockLabel(@js($advantage))"></span>
                                                </template>
                                            </label>
                                            <template x-if="isAdvantageSelected(@js($advantage)) && advantageIsRepeatable(@js($advantage))">
                                                <div class="border-t border-base-300 px-3 py-2">
                                                    <label for="advantage-count-{{ $loop->index }}" class="text-xs font-medium text-base-content/70">Anzahl</label>
                                                    <input
                                                        type="number"
                                                        id="advantage-count-{{ $loop->index }}"
                                                        name="advantage_counts[{{ $advantage }}]"
                                                        min="1"
                                                        step="1"
                                                        class="input input-bordered input-sm mt-1 w-24"
                                                        x-model.number="advantageCounts[@js($advantage)]"
                                                        @input="setAdvantageCount(@js($advantage), advantageCounts[@js($advantage)])"
                                                        @change="setAdvantageCount(@js($advantage), advantageCounts[@js($advantage)])"
                                                    >
                                                </div>
                                            </template>
                                            <template x-if="advantageRequiresDetail(@js($advantage))">
                                                <div class="border-t border-base-300 px-3 py-2">
                                                    <input
                                                        type="text"
                                                        name="advantage_details[{{ $advantage }}]"
                                                        class="input input-bordered input-sm w-full"
                                                        x-model="advantageDetails[@js($advantage)]"
                                                        :placeholder="advantageDetailPlaceholder(@js($advantage))"
                                                    >
                                                </div>
                                            </template>
                                        </div>
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

                                <div class="max-h-96 space-y-2 overflow-y-auto rounded-md border border-base-300 bg-base-200/40 p-2" role="group" aria-labelledby="disadvantages-heading" data-testid="char-editor-disadvantages-list">
                                    @foreach($disadvantages as $disadvantage)
                                        <div
                                            class="rounded-md border border-base-300 bg-base-100 text-sm transition"
                                            :class="{ 'border-primary/60 bg-primary/5': selectedDisadvantages.includes(@js($disadvantage)), 'opacity-60': isDisadvantageDisabled(@js($disadvantage)), 'hover:border-primary/50': !isDisadvantageDisabled(@js($disadvantage)) }"
                                            :title="disadvantageTooltip(@js($disadvantage))"
                                        >
                                            <label for="disadvantage-{{ $loop->index }}" class="flex min-h-12 items-start gap-3 px-3 py-2">
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
                                                <span class="badge badge-ghost shrink-0" x-text="disadvantageRollLabel(@js($disadvantage))"></span>
                                                <template x-if="disadvantageLockLabel(@js($disadvantage))">
                                                    <span class="badge badge-primary badge-outline shrink-0" x-text="disadvantageLockLabel(@js($disadvantage))"></span>
                                                </template>
                                            </label>
                                            <template x-if="disadvantageRequiresDetail(@js($disadvantage))">
                                                <div class="border-t border-base-300 px-3 py-2">
                                                    <input
                                                        type="text"
                                                        name="disadvantage_details[{{ $disadvantage }}]"
                                                        class="input input-bordered input-sm w-full"
                                                        x-model="disadvantageDetails[@js($disadvantage)]"
                                                        :placeholder="disadvantageDetailPlaceholder(@js($disadvantage))"
                                                    >
                                                </div>
                                            </template>
                                        </div>
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
