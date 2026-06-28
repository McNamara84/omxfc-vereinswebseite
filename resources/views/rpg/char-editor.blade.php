@php
    $specialRules ??= \App\Http\Controllers\RpgCharEditorController::specialRuleConfig();
    $advantages = $specialRules['advantages'];
    $disadvantages = $specialRules['disadvantages'];
    $attributeRules = $specialRules['attributeRules']['attributes'] ?? \App\Http\Controllers\RpgCharEditorController::attributeRuleConfig()['attributes'];
    $skillSuggestions = $specialRules['skillRules']['suggestions'] ?? \App\Http\Controllers\RpgCharEditorController::skillRuleConfig()['suggestions'];
@endphp

@push('scripts')
    <script>
        window.rpgCharEditorRules = @js($specialRules);
    </script>
@endpush
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

                <input type="hidden" name="player_name" :value="playerName" x-bind:disabled="!shouldMirrorBaseFields()">
                <input type="hidden" name="character_name" :value="characterName" x-bind:disabled="!shouldMirrorBaseFields()">
                <input type="hidden" name="gender" :value="gender" x-bind:disabled="!shouldMirrorBaseFields()">
                <input type="hidden" name="race" :value="race" x-bind:disabled="!shouldMirrorBaseFields()">
                <input type="hidden" name="culture" :value="culture" x-bind:disabled="!shouldMirrorBaseFields()">
                <input type="hidden" name="portrait_data_url" :value="portraitPreview || ''" x-bind:disabled="!shouldSubmitPortraitPreview()">

                <input type="hidden" name="available_advantage_points" :value="freeAdvantagePoints()">
                <input type="hidden" name="figurenstaerke" value="1">
                <input type="hidden" name="barbar_attribute_bonus" :value="barbarAttributeBonus || ''" x-bind:disabled="race !== 'Barbar' || !advancedUnlocked">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                    <div :class="{ 'opacity-50': advancedUnlocked }">
                        <x-input label="Spielername" name="player_name" x-model="playerName" x-bind:disabled="advancedUnlocked" />
                    </div>

                    <div :class="{ 'opacity-50': advancedUnlocked }">
                        <x-input label="Charaktername" name="character_name" x-model="characterName" x-bind:disabled="advancedUnlocked" />
                    </div>

                    <div :class="{ 'opacity-50': advancedUnlocked }">
                        <label for="gender" class="block text-sm font-medium text-base-content mb-1">Geschlecht</label>
                        <select name="gender" id="gender" class="select select-bordered w-full" x-model="gender" x-bind:disabled="advancedUnlocked">
                            <option value="" disabled>Geschlecht wählen</option>
                            <option value="weiblich">Weiblich</option>
                            <option value="maennlich">Männlich</option>
                            <option value="divers">Divers / keine Angabe</option>
                        </select>
                    </div>

                    <div :class="{ 'opacity-50': advancedUnlocked }">
                        <label for="race" class="block text-sm font-medium text-base-content mb-1">Rasse</label>
                        <select name="race" id="race" class="select select-bordered w-full" x-model="race" x-bind:disabled="advancedUnlocked" @focus="setRaceInfoPreview(race)" @input="setRaceInfoPreview($event.target.value)" @change="setRaceInfoPreview($event.target.value)" @blur="clearRaceInfoPreview()" x-bind:aria-describedby="raceInfo() ? 'race-info-panel' : null">
                            <option value="" disabled>Rasse wählen</option>
                            <option value="Barbar" x-bind:disabled="!isRaceSelectable('Barbar')">Barbar</option>
                            <option value="Guul" x-bind:disabled="!isRaceSelectable('Guul')">Guul</option>
                            <option value="Hydrit" x-bind:disabled="!isRaceSelectable('Hydrit')">Hydrit</option>
                            <option value="Nosfera" x-bind:disabled="!isRaceSelectable('Nosfera')">Nosfera</option>
                            <option value="Taratze" x-bind:disabled="!isRaceSelectable('Taratze')">Taratze</option>
                            <option value="Wulfane" x-bind:disabled="!isRaceSelectable('Wulfane')">Wulfane</option>
                            <option value="Techno" x-bind:disabled="!isRaceSelectable('Techno')">Techno</option>
                            <option value="Präkristofluu" x-bind:disabled="!isRaceSelectable('Präkristofluu')">Präkristofluu</option>
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
                        <select name="culture" id="culture" class="select select-bordered w-full" x-model="culture" x-bind:disabled="advancedUnlocked">
                            <option value="" disabled>Kultur wählen</option>
                            <option value="Landbewohner" x-bind:disabled="!isCultureSelectable('Landbewohner')">Landbewohner</option>
                            <option value="Stadtbewohner" x-bind:disabled="!isCultureSelectable('Stadtbewohner')">Stadtbewohner</option>
                            <option value="Meeresbewohner" x-bind:disabled="!isCultureSelectable('Meeresbewohner')">Meeresbewohner</option>
                            <option value="Bunkermensch" x-bind:disabled="!isCultureSelectable('Bunkermensch')">Bunkermensch</option>
                            <option value="Mensch des 21. Jahrhunderts" x-bind:disabled="!isCultureSelectable('Mensch des 21. Jahrhunderts')">Mensch des 21. Jahrhunderts</option>
                            <option value="Nomade" x-bind:disabled="!isCultureSelectable('Nomade')">Nomade</option>
                            <option value="Disuuslachter (Nordmann)" x-bind:disabled="!isCultureSelectable('Disuuslachter (Nordmann)')">Disuuslachter (Nordmann)</option>
                            <option value="Ruinenbewohner" x-bind:disabled="!isCultureSelectable('Ruinenbewohner')">Ruinenbewohner</option>
                            <option value="Untergrundbewohner" x-bind:disabled="!isCultureSelectable('Untergrundbewohner')">Untergrundbewohner</option>
                            <option value="Volk der 13 Inseln" x-bind:disabled="!isCultureSelectable('Volk der 13 Inseln')">Volk der 13 Inseln</option>
                        </select>
                    </div>

                    <div class="md:col-span-2" :class="{ 'opacity-50': advancedUnlocked }">
                        <label for="portrait" class="block text-sm font-medium text-base-content mb-1">Porträt/Symbol</label>
                        <input type="file" name="portrait" id="portrait" accept="image/*" class="file-input file-input-bordered w-full" @change="handlePortraitUpload($event)" x-bind:disabled="advancedUnlocked">
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

                <fieldset x-bind:disabled="!advancedUnlocked" :class="{ 'opacity-50': !advancedUnlocked }">
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
                        <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                            @foreach($attributeRules as $attribute)
                                @php
                                    $attrId = $attribute['id'];
                                    $label = $attribute['label'];
                                    $descriptionId = 'attribute-description-'.$attrId;
                                @endphp
                                <div x-data="{ attributeHelpOpen: false }" class="space-y-1">
                                    <div class="flex items-center gap-1">
                                        <label for="{{ $attrId }}" class="block text-sm font-medium text-base-content">{{ $label }}</label>
                                        <button
                                            type="button"
                                            class="btn btn-circle btn-ghost btn-xs h-6 min-h-0 w-6"
                                            aria-label="Regelhinweis zu {{ $label }}"
                                            aria-controls="{{ $descriptionId }}"
                                            x-bind:aria-expanded="attributeHelpOpen.toString()"
                                            x-bind:title="attributeTooltip(@js($attrId))"
                                            @mouseenter="attributeHelpOpen = true"
                                            @mouseleave="attributeHelpOpen = false"
                                            @focus="attributeHelpOpen = true"
                                            @blur="attributeHelpOpen = false"
                                            @click="attributeHelpOpen = !attributeHelpOpen"
                                            data-testid="attribute-help-{{ $attrId }}"
                                        >
                                            <x-icon name="o-information-circle" class="h-4 w-4" aria-hidden="true" />
                                        </button>
                                    </div>
                                    <input
                                        type="number"
                                        name="attributes[{{ $attrId }}]"
                                        id="{{ $attrId }}"
                                        x-bind:min="getAttributeMin(@js($attrId))"
                                        x-bind:max="getAttributeMax(@js($attrId))"
                                        x-bind:title="attributeTooltip(@js($attrId))"
                                        step="1"
                                        x-model.number="attributes.{{ $attrId }}"
                                        @change="clampAttribute(@js($attrId))"
                                        aria-describedby="{{ $descriptionId }}"
                                        class="input input-bordered w-full"
                                    >
                                    <p
                                        id="{{ $descriptionId }}"
                                        class="text-xs leading-5 text-base-content/70"
                                        x-cloak
                                        x-bind:class="{ 'sr-only': !attributeHelpOpen }"
                                        x-text="attributeTooltip(@js($attrId))"
                                        data-testid="attribute-description-{{ $attrId }}"
                                    ></p>
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
                                    <option value="Bildung" x-bind:disabled="mensch21SecondBonusSkill === 'Bildung'">Bildung (+1)</option>
                                    <option value="Pilot" x-bind:disabled="mensch21SecondBonusSkill === 'Pilot'">Pilot (+1)</option>
                                    <option value="Techniker" x-bind:disabled="mensch21SecondBonusSkill === 'Techniker'">Techniker (+1)</option>
                                    <option value="Wissenschaftler" x-bind:disabled="mensch21SecondBonusSkill === 'Wissenschaftler'">Wissenschaftler (+1)</option>
                                </select>
                            </div>
                            <div>
                                <label for="mensch-21-second-bonus-select" class="text-sm font-medium text-base-content mb-1">21. Jahrhundert Bonus 2</label>
                                <select id="mensch-21-second-bonus-select" class="select select-bordered w-full" x-model="mensch21SecondBonusSkill" @change="setMensch21SecondBonusSkill(mensch21SecondBonusSkill)">
                                    <option value="Bildung" x-bind:disabled="mensch21FirstBonusSkill === 'Bildung'">Bildung (+1)</option>
                                    <option value="Pilot" x-bind:disabled="mensch21FirstBonusSkill === 'Pilot'">Pilot (+1)</option>
                                    <option value="Techniker" x-bind:disabled="mensch21FirstBonusSkill === 'Techniker'">Techniker (+1)</option>
                                    <option value="Wissenschaftler" x-bind:disabled="mensch21FirstBonusSkill === 'Wissenschaftler'">Wissenschaftler (+1)</option>
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
                            <template x-for="(skill, index) in skills" :key="skill.uid">
                                <div x-data="{ skillHelpOpen: false }" class="grid grid-cols-1 gap-2 sm:grid-cols-[minmax(0,2fr)_6rem_auto_auto] items-start">
                                    <input type="hidden"
                                        :name="'skills[' + index + '][name]'"
                                        :value="skill.name"
                                        x-bind:disabled="!shouldMirrorSkillName(skill)"
                                    >
                                    <input type="hidden"
                                        :name="'skills[' + index + '][value]'"
                                        :value="skill.value"
                                        x-bind:disabled="!shouldMirrorSkillValue(skill)"
                                    >
                                    <input type="text" list="skills-list"
                                        :name="'skills[' + index + '][name]'"
                                        class="skill-name w-full rounded-md shadow-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:border-[#8B0116] dark:focus:border-[#FF6B81] focus:ring focus:ring-[#8B0116] dark:focus:ring-[#FF6B81] focus:ring-opacity-50"
                                        placeholder="Fertigkeit"
                                        x-model="skill.name"
                                        x-bind:disabled="skill.nameDisabled"
                                        x-bind:title="skillTooltip(skill.name)"
                                        x-bind:aria-describedby="skillTooltip(skill.name) ? 'skill-description-' + index : null"
                                        @change="clampSkillValue(skill)"
                                    >
                                    <input type="number"
                                        :name="'skills[' + index + '][value]'"
                                        class="w-full rounded-md shadow-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:border-[#8B0116] dark:focus:border-[#FF6B81] focus:ring focus:ring-[#8B0116] dark:focus:ring-[#FF6B81] focus:ring-opacity-50"
                                        placeholder="FW" step="1"
                                        x-model.number="skill.value"
                                        :min="getSkillMin(skill.name)"
                                        :max="getSkillMax(skill.name)"
                                        x-bind:disabled="isSkillDisabled(skill)"
                                        x-bind:title="skillTooltip(skill.name)"
                                        x-bind:aria-describedby="skillTooltip(skill.name) ? 'skill-description-' + index : null"
                                        @change="clampSkillValue(skill)"
                                    >
                                    <button
                                        type="button"
                                        class="btn btn-circle btn-ghost btn-sm h-9 min-h-0 w-9"
                                        x-bind:class="{ 'opacity-40': !skillTooltip(skill.name) }"
                                        x-bind:disabled="!skillTooltip(skill.name)"
                                        x-bind:title="skillTooltip(skill.name)"
                                        x-bind:aria-controls="'skill-description-' + index"
                                        x-bind:aria-expanded="skillHelpOpen.toString()"
                                        aria-label="Regelhinweis zur Fertigkeit"
                                        @mouseenter="skillHelpOpen = true"
                                        @mouseleave="skillHelpOpen = false"
                                        @focus="skillHelpOpen = true"
                                        @blur="skillHelpOpen = false"
                                        @click.stop="skillHelpOpen = true"
                                        data-testid="skill-help-button"
                                    >
                                        <x-icon name="o-information-circle" class="h-4 w-4" aria-hidden="true" />
                                    </button>
                                    <template x-if="!skill.locked">
                                        <button type="button" class="px-2 py-1 bg-red-500 text-white rounded-md" @click="removeSkill(index)">-</button>
                                    </template>
                                    <template x-if="skill.badge">
                                        <span class="text-xs px-2 py-0.5 rounded bg-blue-200 dark:bg-blue-700 text-blue-800 dark:text-blue-200" x-text="skill.badge"></span>
                                    </template>
                                    <p
                                        x-bind:id="'skill-description-' + index"
                                        class="text-xs leading-5 text-base-content/70 sm:col-span-4"
                                        x-cloak
                                        x-bind:class="{ 'sr-only': !skillHelpOpen }"
                                        x-text="skillTooltip(skill.name)"
                                        data-testid="skill-description"
                                    ></p>
                                </div>
                            </template>
                        </div>
                        <x-button type="button" label="Fertigkeit hinzufügen" class="btn-primary btn-sm mt-2" @click="addSkill()" x-bind:disabled="fpRemaining() <= 0" />
                        <datalist id="skills-list">
                            @foreach($skillSuggestions as $skillSuggestion)
                                <option value="{{ $skillSuggestion }}"></option>
                            @endforeach
                        </datalist>
                    </div>

                    <div class="mb-6">
                        <h2 class="text-xl font-semibold text-primary mb-2">Besonderheiten</h2>
                        <div class="mb-3 flex flex-wrap items-center gap-2">
                            <x-button type="button" label="Vorteil auswürfeln" class="btn-secondary btn-sm" @click="rollSpecial('advantage')" data-testid="roll-advantage-button" />
                            <x-button type="button" label="Nachteil auswürfeln" class="btn-secondary btn-sm" @click="rollSpecial('disadvantage')" data-testid="roll-disadvantage-button" />
                            <p x-show="lastRoll" x-cloak class="text-xs text-base-content/70" aria-live="polite" data-testid="char-editor-roll-result" x-text="lastRoll ? 'W66 ' + lastRoll.value + ' (' + lastRoll.dice + '): ' + lastRoll.message : ''"></p>
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
                                        @php($advantageDescriptionId = 'advantage-description-'.$loop->index)
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
                                                    x-bind:disabled="isAdvantageDisabled(@js($advantage))"
                                                    aria-describedby="{{ $advantageDescriptionId }}"
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
                                            <span id="{{ $advantageDescriptionId }}" class="sr-only" x-text="advantageTooltip(@js($advantage))"></span>
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
                                        @php($disadvantageDescriptionId = 'disadvantage-description-'.$loop->index)
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
                                                    x-bind:disabled="isDisadvantageDisabled(@js($disadvantage))"
                                                    aria-describedby="{{ $disadvantageDescriptionId }}"
                                                >
                                                <span class="min-w-0 flex-1 leading-5">{{ $disadvantage }}</span>
                                                <span class="badge badge-ghost shrink-0" x-text="disadvantageRollLabel(@js($disadvantage))"></span>
                                                <template x-if="disadvantageLockLabel(@js($disadvantage))">
                                                    <span class="badge badge-primary badge-outline shrink-0" x-text="disadvantageLockLabel(@js($disadvantage))"></span>
                                                </template>
                                            </label>
                                            <span id="{{ $disadvantageDescriptionId }}" class="sr-only" x-text="disadvantageTooltip(@js($disadvantage))"></span>
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
                    <div class="mb-6" data-testid="char-editor-equipment-section">
                        <div class="mb-3 flex flex-wrap items-baseline justify-between gap-2">
                            <h2 id="equipment-heading" class="text-xl font-semibold text-primary">Ausrüstung</h2>
                            <p class="text-sm text-base-content/70" aria-live="polite" x-text="'Gegenst&auml;nde: ' + equipmentCount() + ' / ' + equipmentLimit() + ' &middot; High-Tech: ' + highTechEquipmentCount() + ' / ' + highTechEquipmentLimit()"></p>
                        </div>

                        <input type="hidden" name="clothing" :value="clothing">
                        <template x-for="(entry, index) in selectedEquipmentEntries()" :key="'equipment-hidden-' + entry.id">
                            <span>
                                <input type="hidden" :name="'equipment_items[' + index + '][id]'" :value="entry.id">
                                <input type="hidden" :name="'equipment_items[' + index + '][quantity]'" :value="entry.quantity">
                            </span>
                        </template>

                        <div class="mb-4 grid grid-cols-1 gap-3 md:grid-cols-[minmax(0,1fr)_14rem]">
                            <div>
                                <label for="clothing" class="block text-sm font-medium text-base-content mb-1">Kleidung</label>
                                <select id="clothing" class="select select-bordered w-full" x-model="clothing" data-testid="equipment-clothing-select">
                                    <option value="">Kleidung wählen</option>
                                    <template x-for="item in clothingOptions()" :key="item.id">
                                        <option :value="item.id" x-text="item.name + ' · TW ' + item.tw + ' · B ' + item.bucks"></option>
                                    </template>
                                </select>
                            </div>
                            <div class="rounded-md border border-base-300 bg-base-200/40 px-3 py-2 text-sm">
                                <p class="font-medium text-base-content">Startausrüstung</p>
                                <p class="text-base-content/70" x-text="equipmentRemaining() === 0 ? 'Auswahl vollständig' : Math.abs(equipmentRemaining()) + (equipmentRemaining() > 0 ? ' Gegenstände fehlen' : ' zu viel gewählt')"></p>
                            </div>
                        </div>

                        <div class="mb-3 grid grid-cols-1 gap-2 md:grid-cols-[minmax(0,1fr)_14rem]">
                            <label class="sr-only" for="equipment-search">Ausrüstung suchen</label>
                            <input id="equipment-search" type="search" class="input input-bordered w-full" placeholder="Ausrüstung suchen" x-model.debounce.150ms="equipmentSearch" data-testid="equipment-search">
                            <label class="sr-only" for="equipment-category-filter">Kategorie filtern</label>
                            <select id="equipment-category-filter" class="select select-bordered w-full" x-model="equipmentCategoryFilter" data-testid="equipment-category-filter">
                                <option value="all">Alle Kategorien</option>
                                <template x-for="category in equipmentCategoryOptions()" :key="category.id">
                                    <option :value="category.id" x-text="category.label"></option>
                                </template>
                            </select>
                        </div>

                        <div class="max-h-[32rem] overflow-y-auto rounded-md border border-base-300 bg-base-200/40" role="group" aria-labelledby="equipment-heading" data-testid="equipment-list">
                            <template x-for="item in filteredEquipmentItems()" :key="item.id">
                                <div class="grid grid-cols-1 gap-3 border-b border-base-300 bg-base-100 px-3 py-3 last:border-b-0 md:grid-cols-[minmax(0,1fr)_9rem]">
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <h3 class="font-medium leading-5 text-base-content" x-text="item.name"></h3>
                                            <span class="badge badge-ghost" x-text="equipmentCategoryLabel(item)"></span>
                                            <template x-if="equipmentRequiresHighTechAdvantage(item)">
                                                <span class="badge badge-warning badge-outline">High-Tech</span>
                                            </template>
                                        </div>
                                        <p class="mt-1 text-sm leading-5 text-base-content/75" x-text="equipmentRuleLine(item)"></p>
                                        <p class="mt-1 text-xs text-warning" x-show="equipmentDisabledReason(item)" x-cloak x-text="equipmentDisabledReason(item)"></p>
                                    </div>
                                    <div class="flex items-center justify-start gap-2 md:justify-end">
                                        <button type="button" class="btn btn-circle btn-ghost btn-sm h-9 min-h-0 w-9" :disabled="equipmentQuantity(item.id) <= 0" :aria-label="item.name + ' entfernen'" @click="decrementEquipment(item.id)">-</button>
                                        <input type="number" min="0" x-bind:max="maxEquipmentQuantity(item)" step="1" class="input input-bordered input-sm w-16 text-center" :value="equipmentQuantity(item.id)" :aria-label="'Anzahl ' + item.name" @input="setEquipmentQuantity(item.id, $event.target.value)" @change="setEquipmentQuantity(item.id, $event.target.value)">
                                        <button type="button" class="btn btn-circle btn-primary btn-sm h-9 min-h-0 w-9" :disabled="!canIncrementEquipment(item)" :aria-label="item.name + ' hinzufügen'" @click="incrementEquipment(item.id)">+</button>
                                    </div>
                                </div>
                            </template>
                        </div>

                        <div class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-2">
                            <div class="rounded-md border border-base-300 bg-base-200/40 p-3 text-sm">
                                <h3 class="font-medium text-base-content">Gewählte Ausrüstung</h3>
                                <template x-if="selectedEquipmentEntries().length === 0">
                                    <p class="mt-2 text-base-content/70">Noch keine Gegenstände gewählt.</p>
                                </template>
                                <ul class="mt-2 space-y-1">
                                    <template x-for="entry in selectedEquipmentEntries()" :key="'equipment-summary-' + entry.id">
                                        <li class="flex items-start justify-between gap-3">
                                            <span x-text="entry.quantity + 'x ' + entry.item.name"></span>
                                            <span class="text-xs text-base-content/60" x-text="equipmentCategoryLabel(entry.item)"></span>
                                        </li>
                                    </template>
                                </ul>
                            </div>
                            <div class="rounded-md border border-base-300 bg-base-200/40 p-3 text-sm">
                                <h3 class="font-medium text-base-content">Automatische Munition</h3>
                                <template x-if="includedAmmunition().length === 0">
                                    <p class="mt-2 text-base-content/70">Keine Munitionszugaben.</p>
                                </template>
                                <ul class="mt-2 space-y-1">
                                    <template x-for="entry in includedAmmunition()" :key="'ammo-' + entry.source">
                                        <li x-text="entry.source + ': ' + entry.quantity + ' ' + entry.unit"></li>
                                    </template>
                                </ul>
                            </div>
                        </div>

                        <div class="mt-4">
                            <label for="equipment" class="block text-sm font-medium text-base-content mb-1">Notizen zur Ausrüstung</label>
                            <x-textarea name="equipment" id="equipment" rows="3" x-model="equipment" aria-labelledby="equipment-heading" />
                        </div>
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
