@php
    $specialRules ??= \App\Services\RpgCharacterSheetService::specialRuleConfig();
    $advantages = $specialRules['advantages'];
    $disadvantages = $specialRules['disadvantages'];
    $attributeRules = $specialRules['attributeRules']['attributes'] ?? \App\Services\RpgCharacterSheetService::attributeRuleConfig()['attributes'];
    $skillSuggestions = $specialRules['skillRules']['suggestions'] ?? \App\Services\RpgCharacterSheetService::skillRuleConfig()['suggestions'];
    $slotSummary ??= null;
    $editorOldInput = \Illuminate\Support\Arr::only(session()->getOldInput(), [
        'player_name',
        'character_name',
        'gender',
        'race',
        'culture',
        'figurenstaerke',
        'description',
        'portrait_data_url',
        'attributes',
        'skills',
        'trainings',
        'training_allocations',
        'techno_skill_points',
        'praekristofluu_skill_points',
        'bunkermensch_bonus_skill',
        'mensch_21_first_bonus_skill',
        'mensch_21_second_bonus_skill',
        'advantages',
        'disadvantages',
        'advantage_details',
        'disadvantage_details',
        'advantage_counts',
        'attribute_adjustments',
        'extra_ap_attribute',
        'advantage_compensation_attributes',
        'negated_racial_disadvantages',
        'advantage_effects',
        'languages',
        'barbar_attribute_bonus',
        'clothing',
        'equipment_items',
        'active_armor_id',
        'active_shield_id',
        'equipment',
    ]);

    $sessionErrors = session('errors');
    $hasPortraitValidationError = (isset($errors) && ($errors->has('portrait_data_url') || $errors->has('portrait')))
        || ($sessionErrors instanceof \Illuminate\Support\ViewErrorBag && ($sessionErrors->has('portrait_data_url') || $sessionErrors->has('portrait')));

    if ($hasPortraitValidationError) {
        unset($editorOldInput['portrait_data_url']);
    }
@endphp

@push('scripts')
    <script>
        window.rpgCharEditorRules = @js($specialRules);
        window.rpgCharacterSlots = @js($slotSummary);
        window.rpgCharEditorOldInput = @js($editorOldInput);
    </script>
@endpush
<x-member-layout>
    <x-member-page class="max-w-6xl">
        <x-ui.page-header
            eyebrow="Adminbereich"
            title="Charakter-Editor"
            description="Erstelle und exportiere Charakterbögen mit Basisdaten, Attributen, Fertigkeiten und Ausrüstung in einer zusammenhängenden Editoransicht."
            data-testid="page-header"
        />

        <div class="mt-4 flex justify-end">
            <a href="{{ route('rpg.characters.index') }}" class="btn btn-ghost btn-sm" data-testid="rpg-characters-link">
                <x-icon name="o-document-text" class="h-4 w-4" />
                Meine Charaktere
            </a>
        </div>
        <x-ui.panel title="Editorfluss" description="Basisdaten, Regeln, Ausrüstung und Export bleiben in einem zusammenhängenden Arbeitsbereich gebündelt.">
            @if($errors->any())
                <div class="mb-4 rounded-md border border-error/40 bg-error/10 p-4 text-sm text-error" role="alert" data-testid="char-editor-errors">
                    <p class="font-semibold">Der Charakter konnte nicht verarbeitet werden.</p>
                    <ul class="mt-2 list-disc space-y-1 pl-5">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            <form action="{{ route('rpg.characters.store') }}" method="POST" enctype="multipart/form-data" x-data="charEditor()" @submit="handleFormSubmit($event)" data-testid="char-editor-form">
                @csrf

                <input type="hidden" name="purchase_slot_if_needed" :value="purchaseSlotIfNeeded ? '1' : '0'">
                <input type="hidden" name="player_name" :value="playerName" x-bind:disabled="!shouldMirrorBaseFields()">
                <input type="hidden" name="character_name" :value="characterName" x-bind:disabled="!shouldMirrorBaseFields()">
                <input type="hidden" name="gender" :value="gender" x-bind:disabled="!shouldMirrorBaseFields()">
                <input type="hidden" name="race" :value="race" x-bind:disabled="!shouldMirrorBaseFields()">
                <input type="hidden" name="culture" :value="culture" x-bind:disabled="!shouldMirrorBaseFields()">
                <input type="hidden" name="portrait_data_url" :value="portraitPreview || ''" x-bind:disabled="!shouldSubmitPortraitPreview()">

                <input type="hidden" name="barbar_attribute_bonus" :value="barbarAttributeBonus || ''" x-bind:disabled="race !== 'Barbar' || !advancedUnlocked">
                <input type="hidden" name="extra_ap_attribute" :value="extraApAttribute" x-bind:disabled="!extraApAttribute || !advancedUnlocked">
                @foreach($attributeRules as $attribute)
                    <input type="hidden" name="attribute_adjustments[{{ $attribute['id'] }}]" :value="attributeCreationAdjustment(@js($attribute['id']))" x-bind:disabled="!advancedUnlocked">
                @endforeach
                <template x-for="attribute in advantageCompensationAttributes" :key="'advantage-compensation-' + attribute">
                    <input type="hidden" name="advantage_compensation_attributes[]" :value="attribute" x-bind:disabled="!advancedUnlocked">
                </template>
                <template x-for="disadvantage in negatedRacialDisadvantages" :key="'negated-racial-disadvantage-' + disadvantage">
                    <input type="hidden" name="negated_racial_disadvantages[]" :value="disadvantage" x-bind:disabled="!advancedUnlocked">
                </template>
                <template x-for="(effect, index) in advantageEffects" :key="'advantage-effect-hidden-' + index + '-' + effect.name">
                    <span>
                        <input type="hidden" :name="'advantage_effects[' + index + '][name]'" :value="effect.name" x-bind:disabled="!advancedUnlocked">
                        <input type="hidden" :name="'advantage_effects[' + index + '][target]'" :value="effect.target" x-bind:disabled="!advancedUnlocked">
                        <input type="hidden" :name="'advantage_effects[' + index + '][justification]'" :value="effect.justification" x-bind:disabled="!advancedUnlocked">
                    </span>
                </template>
                <template x-for="(language, index) in languages" :key="'language-hidden-' + index + '-' + language">
                    <input type="hidden" name="languages[]" :value="language" x-bind:disabled="!advancedUnlocked">
                </template>

                <nav class="mb-6 flex flex-wrap gap-2 text-sm" aria-label="Editorbereiche" data-testid="char-editor-section-nav">
                    <a href="#char-editor-basics" class="btn btn-ghost btn-sm">Charakterdaten</a>
                    <a href="#char-editor-attributes" class="btn btn-ghost btn-sm" :class="{ 'btn-disabled': !advancedUnlocked }" x-bind:aria-disabled="advancedUnlocked ? null : 'true'" x-bind:tabindex="advancedUnlocked ? null : -1" @click="if (!advancedUnlocked) $event.preventDefault()" @keydown.enter="if (!advancedUnlocked) $event.preventDefault()">Attribute</a>
                    <a href="#char-editor-skills" class="btn btn-ghost btn-sm" :class="{ 'btn-disabled': !advancedUnlocked }" x-bind:aria-disabled="advancedUnlocked ? null : 'true'" x-bind:tabindex="advancedUnlocked ? null : -1" @click="if (!advancedUnlocked) $event.preventDefault()" @keydown.enter="if (!advancedUnlocked) $event.preventDefault()">Fertigkeiten</a>
                    <a href="#char-editor-trainings" class="btn btn-ghost btn-sm" :class="{ 'btn-disabled': !advancedUnlocked }" x-bind:aria-disabled="advancedUnlocked ? null : 'true'" x-bind:tabindex="advancedUnlocked ? null : -1" @click="if (!advancedUnlocked) $event.preventDefault()" @keydown.enter="if (!advancedUnlocked) $event.preventDefault()">Ausbildungen</a>
                    <a href="#char-editor-specials" class="btn btn-ghost btn-sm" :class="{ 'btn-disabled': !advancedUnlocked }" x-bind:aria-disabled="advancedUnlocked ? null : 'true'" x-bind:tabindex="advancedUnlocked ? null : -1" @click="if (!advancedUnlocked) $event.preventDefault()" @keydown.enter="if (!advancedUnlocked) $event.preventDefault()">Besonderheiten</a>
                    <a href="#char-editor-equipment" class="btn btn-ghost btn-sm" :class="{ 'btn-disabled': !advancedUnlocked }" x-bind:aria-disabled="advancedUnlocked ? null : 'true'" x-bind:tabindex="advancedUnlocked ? null : -1" @click="if (!advancedUnlocked) $event.preventDefault()" @keydown.enter="if (!advancedUnlocked) $event.preventDefault()">Ausrüstung</a>
                    <a href="#char-editor-export" class="btn btn-ghost btn-sm" :class="{ 'btn-disabled': !advancedUnlocked }" x-bind:aria-disabled="advancedUnlocked ? null : 'true'" x-bind:tabindex="advancedUnlocked ? null : -1" @click="if (!advancedUnlocked) $event.preventDefault()" @keydown.enter="if (!advancedUnlocked) $event.preventDefault()">Export</a>
                </nav>

                <section id="char-editor-basics" class="space-y-5" data-testid="char-editor-basics-section">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-base-content/45">Schritt 1</p>
                            <h2 class="mt-1 text-xl font-semibold text-primary">Charakterdaten</h2>
                        </div>
                        <span class="badge badge-outline" x-text="basicsFilled() ? 'Bereit für den nächsten Schritt' : 'Pflichtfelder offen'"></span>
                    </div>

                    <div class="grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(20rem,24rem)] lg:items-start">
                        <div class="min-w-0">
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div :class="{ 'opacity-50': advancedUnlocked }">
                                    <x-input label="Spielername" name="player_name" aria-label="Spielername" x-model="playerName" x-bind:disabled="advancedUnlocked" />
                                </div>

                                <div :class="{ 'opacity-50': advancedUnlocked }">
                                    <x-input label="Charaktername" name="character_name" aria-label="Charaktername" x-model="characterName" x-bind:disabled="advancedUnlocked" />
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
                                    <select name="race" id="race" class="select select-bordered w-full" x-model="race" x-bind:disabled="advancedUnlocked" @focus="setRaceInfoPreview(race)" @input="setRaceInfoPreview($event.target.value)" @change="setRaceInfoPreview($event.target.value)" @blur="clearRaceInfoPreview()" x-bind:aria-describedby="selectionInfoAvailable() ? 'race-info-panel' : null">
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
                                </div>

                                <div :class="{ 'opacity-50': advancedUnlocked }">
                                    <label for="culture" class="block text-sm font-medium text-base-content mb-1">Kultur</label>
                                    <select name="culture" id="culture" class="select select-bordered w-full" x-model="culture" x-bind:disabled="advancedUnlocked" x-bind:aria-describedby="selectionInfoAvailable() ? 'race-info-panel' : null">
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

                                <div>
                                    <label for="figurenstaerke" class="block text-sm font-medium text-base-content mb-1">Figurenstärke</label>
                                    <select
                                        name="figurenstaerke"
                                        id="figurenstaerke"
                                        class="select select-bordered w-full"
                                        x-model.number="creationLevel"
                                        x-init="$nextTick(() => initializeCreationLevelSelect($el))"
                                        data-testid="creation-level-select"
                                    >
                                        @foreach(($specialRules['creation']['levels'] ?? []) as $creationLevelRule)
                                            <option
                                                value="{{ $creationLevelRule['level'] }}"
                                                @selected($creationLevelRule['level'] === ($specialRules['creation']['defaultLevel'] ?? 3))
                                            >Stufe {{ $creationLevelRule['level'] }}</option>
                                        @endforeach
                                    </select>
                                    <p class="mt-1 text-xs text-base-content/65" x-text="base.AP + ' AP · ' + base.FP + ' FP · maximal FW ' + base.maxFW + ' · ' + base.freeAdvantages + ' freie Vorteilswerte'"></p>
                                </div>

                                <div class="sm:col-span-2">
                                    <label for="portrait" class="block text-sm font-medium text-base-content mb-1">Porträt/Symbol</label>
                                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-[minmax(0,1fr)_6rem] sm:items-start">
                                        <input type="file" name="portrait" id="portrait" accept="image/*" class="file-input file-input-bordered w-full" @change="handlePortraitUpload($event)">
                                        <div class="flex h-24 w-24 items-center justify-center overflow-hidden rounded-md border border-base-content/20 bg-base-200/50 text-xs text-base-content/60">
                                            <span x-show="!portraitPreview">Vorschau</span>
                                            <img x-show="portraitPreview" x-cloak :src="portraitPreview" class="h-full w-full object-cover" alt="Porträt Vorschau" data-testid="char-editor-portrait-preview">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-5">
                                <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                                    <h3 class="text-lg font-semibold text-primary">Beschreibung</h3>
                                    <span class="badge badge-ghost" x-text="descriptionUserEdited ? 'Manuell bearbeitet' : 'Automatisch aus Auswahl'"></span>
                                </div>
                                <x-textarea name="description" id="description" rows="5" x-model="description" @input="descriptionUserEdited = true" data-testid="char-editor-description" />
                            </div>
                        </div>

                        <aside id="race-info-panel" class="rounded-md border border-base-300 bg-base-200/40 p-4 text-sm lg:sticky lg:top-24" data-testid="race-info-panel" aria-live="polite">
                            <div class="mb-3 flex items-start justify-between gap-3">
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-base-content/45">Auswahlwirkung</p>
                                    <h3 class="mt-1 font-semibold text-base-content">Rasse und Kultur</h3>
                                </div>
                                <span class="badge badge-outline" x-show="selectionInfoAvailable()" x-cloak>aktiv</span>
                            </div>

                            <template x-if="!selectionInfoAvailable()">
                                <p class="text-base-content/70">Wähle Rasse und Kultur, um Regelboni und Beschreibungsvorschläge zu sehen.</p>
                            </template>

                            <template x-if="raceInfo()">
                                <div class="border-t border-base-300 pt-3 first:border-t-0 first:pt-0" data-testid="race-summary">
                                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                                        <h4 class="font-semibold text-base-content" x-text="raceInfo().name"></h4>
                                        <span class="text-xs text-base-content/70" x-text="raceInfo().attributes"></span>
                                    </div>
                                    <p class="mt-2 leading-5 text-base-content/80" x-text="raceShortDescription()"></p>
                                    <dl class="mt-3 grid grid-cols-1 gap-2">
                                        <template x-for="row in raceInfoRows()" :key="row.label">
                                            <div class="grid grid-cols-1 gap-1 sm:grid-cols-[7rem_1fr]">
                                                <dt class="font-medium text-base-content" x-text="row.label"></dt>
                                                <dd class="text-base-content/80" x-text="row.value"></dd>
                                            </div>
                                        </template>
                                    </dl>
                                    <details class="mt-3">
                                        <summary class="cursor-pointer text-xs font-semibold uppercase tracking-[0.12em] text-base-content/60">Rassentext anzeigen</summary>
                                        <p class="mt-2 leading-5 text-base-content/75" x-text="raceInfo().description"></p>
                                    </details>
                                </div>
                            </template>

                            <template x-if="cultureInfo()">
                                <div class="mt-4 border-t border-base-300 pt-3" data-testid="culture-summary">
                                    <h4 class="font-semibold text-base-content" x-text="cultureInfo().name"></h4>
                                    <p class="mt-2 leading-5 text-base-content/80" x-text="cultureShortDescription()"></p>
                                    <dl class="mt-3 grid grid-cols-1 gap-2">
                                        <template x-for="row in cultureInfoRows()" :key="row.label">
                                            <div class="grid grid-cols-1 gap-1 sm:grid-cols-[7rem_1fr]">
                                                <dt class="font-medium text-base-content" x-text="row.label"></dt>
                                                <dd class="text-base-content/80" x-text="row.value"></dd>
                                            </div>
                                        </template>
                                    </dl>
                                    <details class="mt-3">
                                        <summary class="cursor-pointer text-xs font-semibold uppercase tracking-[0.12em] text-base-content/60">Kulturtext anzeigen</summary>
                                        <p class="mt-2 leading-5 text-base-content/75" x-text="cultureInfo().description"></p>
                                    </details>
                                </div>
                            </template>
                        </aside>
                    </div>

                    <div class="flex justify-end" x-show="basicsFilled() && !advancedUnlocked" x-cloak>
                        <x-button type="button" label="Weiter, bei Wudan" class="btn-primary" @click="unlockAdvanced()" data-testid="char-editor-continue-button" />
                    </div>
                </section>
                <fieldset class="mt-8 space-y-8" x-bind:disabled="!advancedUnlocked" :class="{ 'opacity-50': !advancedUnlocked }">
                    <section id="char-editor-attributes" class="border-t border-base-300/70 pt-6" data-testid="char-editor-attributes-section">
                        <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-base-content/45">Schritt 2</p>
                                <h2 class="mt-1 text-xl font-semibold text-primary">Attribute</h2>
                            </div>
                            <span class="badge badge-outline" aria-live="polite" x-text="'AP: ' + apRemaining()"></span>
                        </div>
                        <div x-show="race === 'Barbar'" class="mb-3">
                            <label for="barbar-attribute-select" class="text-sm font-medium text-base-content mb-1">Barbar Attributbonus</label>
                            <select id="barbar-attribute-select" class="select select-bordered w-full sm:w-auto" x-model="barbarAttributeBonus" @change="setBarbarAttributeBonus(barbarAttributeBonus)">
                                <template x-for="attributeOption in attributeOptions" :key="'barbar-attribute-' + attributeOption.id">
                                    <option :value="attributeOption.id" x-text="attributeOption.label + ' (+1)'"></option>
                                </template>
                            </select>
                        </div>
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                            @foreach($attributeRules as $attribute)
                                @php
                                    $attrId = $attribute['id'];
                                    $label = $attribute['label'];
                                    $descriptionId = 'attribute-description-'.$attrId;
                                @endphp
                                <div x-data="{ attributeHelpOpen: false }" class="space-y-2 rounded-md border border-base-300 bg-base-100 p-3">
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
                                    <div class="flex flex-wrap items-center gap-2 text-xs text-base-content/60">
                                        <span x-text="'Bereich ' + attributeRangeLabel(@js($attrId))"></span>
                                        <span x-show="attributeModifier(@js($attrId)) !== 0" x-cloak class="badge badge-primary badge-outline" x-text="'Rasse ' + (attributeModifier(@js($attrId)) > 0 ? '+' : '') + attributeModifier(@js($attrId))"></span>
                                        <span class="badge badge-ghost" x-text="'Erschaffung ' + (attributeCreationAdjustment(@js($attrId)) > 0 ? '+' : '') + attributeCreationAdjustment(@js($attrId))"></span>
                                        <span x-show="attributeAdvantageBonus(@js($attrId)) > 0" x-cloak class="badge badge-secondary badge-outline" x-text="'Vorteil +' + attributeAdvantageBonus(@js($attrId))"></span>
                                    </div>
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
                        <div class="mt-4 grid grid-cols-1 gap-3 lg:grid-cols-2" data-testid="attribute-trade-controls">
                            <div class="rounded-md border border-base-300 bg-base-200/40 p-3">
                                <label for="extra-ap-attribute" class="text-sm font-medium text-base-content">Zusätzlichen AP erwerben</label>
                                <p class="mt-1 text-xs leading-5 text-base-content/70" x-text="'Senkt genau ein Attribut freiwillig um 1 und erhöht das AP-Budget von ' + base.AP + ' auf ' + (base.AP + 1) + '.'"></p>
                                <select id="extra-ap-attribute" class="select select-bordered select-sm mt-2 w-full" :value="extraApAttribute" @change="setExtraApAttribute($event.target.value)">
                                    <option value="">Kein zusätzlicher AP</option>
                                    <template x-for="attributeOption in attributeOptions" :key="'extra-ap-option-' + attributeOption.id">
                                        <option :value="attributeOption.id" x-text="attributeOption.label + ' um 1 senken'"></option>
                                    </template>
                                </select>
                            </div>
                            <div class="rounded-md border border-base-300 bg-base-200/40 p-3">
                                <p class="text-sm font-medium text-base-content">Zusatzvorteile durch Attributsenkung ausgleichen</p>
                                <p class="mt-1 text-xs leading-5 text-base-content/70">Bis zu zwei andere Attribute können je einen Ausgleich liefern. Eine Senkung kann nicht doppelt verwendet werden.</p>
                                <div class="mt-2 flex flex-wrap gap-2">
                                    <template x-for="attributeOption in attributeOptions" :key="'advantage-compensation-option-' + attributeOption.id">
                                        <label class="flex items-center gap-2 rounded border border-base-300 bg-base-100 px-2 py-1 text-xs" :class="{ 'opacity-50': extraApAttribute === attributeOption.id }">
                                            <input type="checkbox" class="checkbox checkbox-xs" :checked="advantageCompensationAttributes.includes(attributeOption.id)" :disabled="extraApAttribute === attributeOption.id" @change="toggleAdvantageCompensationAttribute(attributeOption.id, $event.target.checked)">
                                            <span x-text="attributeOption.label"></span>
                                        </label>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section id="char-editor-skills" class="border-t border-base-300/70 pt-6" data-testid="char-editor-skills-section">
                        <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-base-content/45">Schritt 3</p>
                                <h2 class="mt-1 text-xl font-semibold text-primary">Fertigkeiten</h2>
                            </div>
                            <span class="badge badge-outline" aria-live="polite" x-text="'FP: ' + fpRemaining()"></span>
                        </div>
                        <div class="mb-4 grid grid-cols-1 gap-3 lg:grid-cols-2" data-testid="char-editor-bonus-controls">
                            <div x-show="race === 'Barbar'" class="rounded-md border border-base-300 bg-base-100 p-3">
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
                                        <input type="number" min="0" x-bind:max="base.maxFW" step="1" class="input input-bordered input-sm w-20" x-bind:name="'techno_skill_points[' + skillName + ']'" x-bind:disabled="race !== 'Techno' || !advancedUnlocked" x-model.number="technoSkillPoints[skillName]" @input="setTechnoSkillPoints(skillName, technoSkillPoints[skillName])" @change="setTechnoSkillPoints(skillName, technoSkillPoints[skillName])" data-testid="techno-skill-points-input">
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
                                        <input type="number" min="0" x-bind:max="base.maxFW" step="1" class="input input-bordered input-sm w-20" x-bind:name="'praekristofluu_skill_points[' + skillName + ']'" x-bind:disabled="race !== 'Präkristofluu' || !advancedUnlocked" x-model.number="praekristofluuSkillPoints[skillName]" @input="setPraekristofluuSkillPoints(skillName, praekristofluuSkillPoints[skillName])" @change="setPraekristofluuSkillPoints(skillName, praekristofluuSkillPoints[skillName])" data-testid="praekristofluu-skill-points-input">
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
                            <select id="bunkermensch-bonus-select" name="bunkermensch_bonus_skill" class="select select-bordered w-full sm:w-auto" x-bind:disabled="culture !== 'Bunkermensch' || !advancedUnlocked" x-model="bunkermenschBonusSkill" @change="setBunkermenschBonusSkill(bunkermenschBonusSkill)">
                                <option value="Feuerwaffen">Feuerwaffen (+1)</option>
                                <option value="Pilot">Pilot (+1)</option>
                                <option value="Wissenschaftler">Wissenschaftler (+1)</option>
                            </select>
                        </div>
                        <div x-show="culture === 'Mensch des 21. Jahrhunderts'" class="mb-2 grid grid-cols-1 gap-2 sm:grid-cols-2">
                            <div>
                                <label for="mensch-21-first-bonus-select" class="text-sm font-medium text-base-content mb-1">21. Jahrhundert Bonus 1</label>
                                <select id="mensch-21-first-bonus-select" name="mensch_21_first_bonus_skill" class="select select-bordered w-full" x-bind:disabled="culture !== 'Mensch des 21. Jahrhunderts' || !advancedUnlocked" x-model="mensch21FirstBonusSkill" @change="setMensch21FirstBonusSkill(mensch21FirstBonusSkill)">
                                    <option value="Bildung" x-bind:disabled="mensch21SecondBonusSkill === 'Bildung'">Bildung (+1)</option>
                                    <option value="Pilot" x-bind:disabled="mensch21SecondBonusSkill === 'Pilot'">Pilot (+1)</option>
                                    <option value="Techniker" x-bind:disabled="mensch21SecondBonusSkill === 'Techniker'">Techniker (+1)</option>
                                    <option value="Wissenschaftler" x-bind:disabled="mensch21SecondBonusSkill === 'Wissenschaftler'">Wissenschaftler (+1)</option>
                                </select>
                            </div>
                            <div>
                                <label for="mensch-21-second-bonus-select" class="text-sm font-medium text-base-content mb-1">21. Jahrhundert Bonus 2</label>
                                <select id="mensch-21-second-bonus-select" name="mensch_21_second_bonus_skill" class="select select-bordered w-full" x-bind:disabled="culture !== 'Mensch des 21. Jahrhunderts' || !advancedUnlocked" x-model="mensch21SecondBonusSkill" @change="setMensch21SecondBonusSkill(mensch21SecondBonusSkill)">
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
                        </div>
                        <div class="space-y-2 rounded-md border border-base-300 bg-base-200/40 p-2">
                            <template x-for="(skill, index) in skills" :key="skill.uid">
                                <div x-data="{ skillHelpOpen: false }" class="grid grid-cols-1 items-start gap-2 rounded-md border border-base-300 bg-base-100 p-3 sm:grid-cols-[minmax(0,2fr)_6rem_auto_auto]">
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
                                        class="input input-bordered w-full"
                                        placeholder="Fertigkeit"
                                        x-model="skill.name"
                                        x-bind:disabled="skill.nameDisabled"
                                        x-bind:title="skillTooltip(skill.name)"
                                        x-bind:aria-describedby="skillTooltip(skill.name) ? 'skill-description-' + index : null"
                                        @change="clampSkillValue(skill)"
                                    >
                                    <input type="number"
                                        :name="'skills[' + index + '][value]'"
                                        class="input input-bordered w-full"
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
                                        <button type="button" class="btn btn-circle btn-error btn-sm h-9 min-h-0 w-9" aria-label="Fertigkeit entfernen" @click="removeSkill(index)">-</button>
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
                        <x-button type="button" label="Fertigkeit hinzufügen" class="btn-primary btn-sm mt-3" @click="addSkill()" x-bind:disabled="fpRemaining() <= 0" />
                        <datalist id="skills-list">
                            @foreach($skillSuggestions as $skillSuggestion)
                                <option value="{{ $skillSuggestion }}"></option>
                            @endforeach
                        </datalist>
                        <div class="mt-4 rounded-md border border-base-300 bg-base-200/40 p-3" x-show="languageSkillValue() > 0 || languages.length" x-cloak data-testid="language-list-editor">
                            <div class="flex flex-wrap items-baseline justify-between gap-2">
                                <div>
                                    <h3 class="text-sm font-medium text-base-content">Beherrschte Sprachen und Dialekte</h3>
                                    <p class="mt-1 text-xs text-base-content/70">Sprachen werden einmal als Fertigkeit geführt; hier stehen die konkreten Sprachen.</p>
                                </div>
                                <span class="badge" :class="languagesComplete() ? 'badge-success' : 'badge-warning badge-outline'" x-text="languages.length + ' / ' + languageMinimum() + (languageMaximum() !== languageMinimum() ? '–' + languageMaximum() : '')"></span>
                            </div>
                            <div class="mt-3 flex gap-2">
                                <input type="text" class="input input-bordered input-sm min-w-0 flex-1" x-model="languageDraft" @keydown.enter.prevent="addLanguage()" aria-label="Sprache oder Dialekt" placeholder="Sprache oder Dialekt">
                                <button type="button" class="btn btn-primary btn-sm" @click="addLanguage()" :disabled="!String(languageDraft || '').trim() || languages.length >= languageMaximum()">Hinzufügen</button>
                            </div>
                            <div class="mt-3 flex flex-wrap gap-2">
                                <template x-for="(language, index) in languages" :key="'language-chip-' + language">
                                    <span class="badge badge-outline gap-2">
                                        <span x-text="language"></span>
                                        <button type="button" class="font-bold" :aria-label="language + ' entfernen'" @click="removeLanguage(index)">×</button>
                                    </span>
                                </template>
                            </div>
                        </div>
                    </section>

                    <section id="char-editor-trainings" class="border-t border-base-300/70 pt-6" data-testid="char-editor-trainings-section">
                        <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-base-content/45">Schritt 4</p>
                                <h2 class="mt-1 text-xl font-semibold text-primary">Ausbildungen</h2>
                            </div>
                            <span class="badge badge-outline" aria-live="polite" x-text="selectedTrainings.length + ' gewählt · ' + trainingTotalCost() + ' FP gebunden'"></span>
                        </div>

                        <p class="mb-4 text-sm leading-6 text-base-content/75">
                            Ausbildungen verteilen einen Teil der regulären <span x-text="base.FP"></span> Fertigkeitspunkte auf passende Fertigkeiten. Sie gewähren keine zusätzlichen FP.
                        </p>

                        <div class="grid grid-cols-1 gap-3 md:grid-cols-2" role="group" aria-label="Ausbildungen wählen">
                            <template x-for="rule in trainingRules()" :key="'training-choice-' + rule.name">
                                <label class="rounded-md border border-base-300 bg-base-100 p-3 transition" :class="{ 'border-primary/60 bg-primary/5': isTrainingSelected(rule.name), 'opacity-55': isTrainingDisabled(rule.name) }">
                                    <span class="flex items-start gap-3">
                                        <input
                                            type="checkbox"
                                            name="trainings[]"
                                            class="checkbox checkbox-primary checkbox-sm mt-1"
                                            :value="rule.name"
                                            x-model="selectedTrainings"
                                            :disabled="isTrainingDisabled(rule.name)"
                                            @change="$nextTick(() => handleTrainingSelection(rule.name))"
                                        >
                                        <span class="min-w-0 flex-1">
                                            <span class="flex flex-wrap items-center justify-between gap-2">
                                                <strong x-text="rule.name"></strong>
                                                <span class="badge badge-ghost" x-text="rule.cost + ' FP'"></span>
                                            </span>
                                            <span class="mt-1 block text-sm leading-5 text-base-content/70" x-text="rule.description"></span>
                                            <span class="mt-2 block text-xs text-base-content/60" x-text="'Fertigkeiten: ' + rule.skills.join(', ')"></span>
                                            <template x-if="trainingRequiredAdvantages(rule).length">
                                                <span class="mt-2 block text-xs" :class="trainingPrerequisitesMet(rule) ? 'text-success' : 'text-error'" x-text="'Voraussetzung: ' + trainingRequiredAdvantages(rule).join(', ')"></span>
                                            </template>
                                        </span>
                                    </span>
                                </label>
                            </template>
                        </div>

                        <template x-for="(entry, index) in trainingAllocationEntries()" :key="'training-allocation-hidden-' + entry.training + '-' + entry.skill">
                            <span>
                                <input type="hidden" :name="'training_allocations[' + index + '][training]'" :value="entry.training">
                                <input type="hidden" :name="'training_allocations[' + index + '][skill]'" :value="entry.skill">
                                <input type="hidden" :name="'training_allocations[' + index + '][points]'" :value="entry.points">
                            </span>
                        </template>

                        <div class="mt-4 space-y-4" x-show="selectedTrainingRules().length" x-cloak>
                            <template x-for="rule in selectedTrainingRules()" :key="'training-allocation-' + rule.name">
                                <div class="rounded-md border border-base-300 bg-base-200/40 p-3">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <div>
                                            <h3 class="font-semibold text-base-content" x-text="rule.name"></h3>
                                            <p class="text-xs text-base-content/65">FP auf die erlaubten Fertigkeiten verteilen</p>
                                        </div>
                                        <span class="badge" :class="trainingAllocationRemaining(rule.name) === 0 && trainingPrerequisitesMet(rule) ? 'badge-success' : 'badge-warning badge-outline'" x-text="trainingAllocationRemaining(rule.name) === 0 ? 'vollständig' : Math.abs(trainingAllocationRemaining(rule.name)) + ' FP offen'"></span>
                                    </div>

                                    <div class="mt-3 grid grid-cols-1 gap-2 lg:grid-cols-2">
                                        <template x-for="baseSkill in rule.skills" :key="rule.name + '-' + baseSkill">
                                            <div class="grid grid-cols-[minmax(0,1fr)_5.5rem] items-end gap-2 rounded border border-base-300 bg-base-100 p-2">
                                                <div>
                                                    <label class="block text-xs font-medium text-base-content/70" x-text="baseSkill"></label>
                                                    <template x-if="trainingSkillIsSpecializable(baseSkill)">
                                                        <input
                                                            type="text"
                                                            list="skills-list"
                                                            class="input input-bordered input-sm mt-1 w-full"
                                                            :value="trainingSkillTarget(rule.name, baseSkill)"
                                                            :aria-label="'Konkrete Fertigkeit für ' + baseSkill"
                                                            @change="setTrainingSkillTarget(rule.name, baseSkill, $event.target.value); $event.target.value = trainingSkillTarget(rule.name, baseSkill)"
                                                        >
                                                    </template>
                                                    <template x-if="!trainingSkillIsSpecializable(baseSkill)">
                                                        <p class="mt-2 text-sm" x-text="baseSkill"></p>
                                                    </template>
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-medium text-base-content/70">FP</label>
                                                    <input
                                                        type="number"
                                                        min="0"
                                                        :max="trainingAllocationMax(rule.name, baseSkill)"
                                                        step="1"
                                                        class="input input-bordered input-sm mt-1 w-full text-center"
                                                        :value="trainingAllocationPoints(rule.name, baseSkill)"
                                                        :aria-label="'Ausbildungspunkte für ' + baseSkill"
                                                        @input="setTrainingAllocation(rule.name, baseSkill, $event.target.value); $event.target.value = trainingAllocationPoints(rule.name, baseSkill)"
                                                        @change="setTrainingAllocation(rule.name, baseSkill, $event.target.value); $event.target.value = trainingAllocationPoints(rule.name, baseSkill)"
                                                    >
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </section>

                    <section id="char-editor-specials" class="border-t border-base-300/70 pt-6" data-testid="char-editor-specials-section">
                        <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-base-content/45">Schritt 5</p>
                                <h2 class="mt-1 text-xl font-semibold text-primary">Besonderheiten</h2>
                            </div>
                            <span class="badge" :class="missingAdvantageCompensations() === 0 ? 'badge-success badge-outline' : 'badge-warning badge-outline'" aria-live="polite" x-text="'Vorteilswerte ' + chosenAdvantagesCount() + ' / ' + advantageUnitLimit() + ' · Ausgleich ' + availableAdvantageCompensations() + ' / ' + extraAdvantageUnits()"></span>
                        </div>
                        <div class="mb-3 grid grid-cols-1 gap-2 sm:grid-cols-3" data-testid="advantage-budget-summary">
                            <div class="rounded-md border border-base-300 bg-base-200/40 px-3 py-2 text-sm"><strong x-text="base.freeAdvantages + ' frei'"></strong><span class="block text-xs text-base-content/65" x-text="'auf Figurenstärke ' + creationLevel"></span></div>
                            <div class="rounded-md border border-base-300 bg-base-200/40 px-3 py-2 text-sm"><strong x-text="extraAdvantageUnits() + ' zusätzlich'"></strong><span class="block text-xs text-base-content/65">maximal 2</span></div>
                            <div class="rounded-md border border-base-300 bg-base-200/40 px-3 py-2 text-sm"><strong x-text="missingAdvantageCompensations() === 0 ? 'Ausgeglichen' : missingAdvantageCompensations() + ' offen'"></strong><span class="block text-xs text-base-content/65">freiwillige Nachteile oder Senkungen</span></div>
                        </div>
                        <div class="mb-3 flex flex-wrap items-center gap-2 rounded-md border border-base-300 bg-base-200/40 p-3">
                            <x-button type="button" label="Vorteil auswürfeln" class="btn-secondary btn-sm" @click="rollSpecial('advantage')" data-testid="roll-advantage-button" />
                            <x-button type="button" label="Nachteil auswürfeln" class="btn-secondary btn-sm" @click="rollSpecial('disadvantage')" data-testid="roll-disadvantage-button" />
                            <p x-show="lastRoll" x-cloak class="text-xs text-base-content/70" aria-live="polite" data-testid="char-editor-roll-result" x-text="lastRoll ? 'W66 ' + lastRoll.value + ' (' + lastRoll.dice + '): ' + lastRoll.message : ''"></p>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <div class="flex flex-wrap items-baseline justify-between gap-2 mb-2">
                                    <h3 id="advantages-heading" class="text-sm font-medium text-base-content">Vorteile</h3>
                                    <p class="text-xs text-base-content/70" aria-live="polite" x-text="'Automatisch: ' + automaticAdvantages().join(', ')"></p>
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
                                            <template x-if="isAdvantageSelected(@js($advantage)) && advantageEffectNeedsEditor(@js($advantage)) && !raceLocked.advantages.includes(@js($advantage))">
                                                <div class="space-y-2 border-t border-base-300 px-3 py-2">
                                                    <template x-for="effect in advantageEffectEntries(@js($advantage))" :key="'advantage-effect-editor-' + effect.index">
                                                        <div class="grid grid-cols-1 gap-2 rounded border border-base-300 bg-base-200/40 p-2">
                                                            <p class="text-xs font-medium text-base-content/70" x-show="advantageEffectEntries(@js($advantage)).length > 1" x-text="'Instanz ' + (advantageEffectEntries(@js($advantage)).findIndex(item => item.index === effect.index) + 1)"></p>
                                                            <select
                                                                x-show="advantageTargetOptions(@js($advantage)).length"
                                                                class="select select-bordered select-sm w-full"
                                                                :value="effect.target"
                                                                :disabled="@js($advantage) === 'Psychische Kraft' && culture === 'Volk der 13 Inseln' && gender === 'weiblich'"
                                                                @change="setAdvantageEffectField(effect.index, 'target', $event.target.value)"
                                                            >
                                                                <option value="">Ziel wählen</option>
                                                                <template x-for="target in advantageTargetOptions(@js($advantage))" :key="'advantage-target-' + effect.index + '-' + target">
                                                                    <option :value="target" x-text="attributeOptions.find(item => item.id === target)?.label || target"></option>
                                                                </template>
                                                            </select>
                                                            <input
                                                                x-show="advantageRule(@js($advantage))?.requiresJustification || advantageRule(@js($advantage))?.requiresDetail"
                                                                type="text"
                                                                class="input input-bordered input-sm w-full"
                                                                :value="effect.justification"
                                                                @input="setAdvantageEffectField(effect.index, 'justification', $event.target.value)"
                                                                :aria-label="@js($advantage) + ': ' + advantageDetailPlaceholder(@js($advantage))"
                                                                :placeholder="advantageDetailPlaceholder(@js($advantage))"
                                                            >
                                                        </div>
                                                    </template>
                                                </div>
                                            </template>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                            <div>
                                <div class="flex flex-wrap items-baseline justify-between gap-2 mb-2">
                                    <h3 id="disadvantages-heading" class="text-sm font-medium text-base-content">Nachteile</h3>
                                    <p class="text-xs text-base-content/70" aria-live="polite" x-text="'Frei gewählt: ' + voluntaryDisadvantages().length + ' · automatisch: ' + automaticDisadvantages().length + ' · rassengegeben: ' + (raceLocked.disadvantages.length - negatedRacialDisadvantages.length)"></p>
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
                                            <template x-if="raceLocked.disadvantages.includes(@js($disadvantage))">
                                                <label class="flex items-center gap-2 border-t border-base-300 px-3 py-2 text-xs text-base-content/75">
                                                    <input
                                                        type="checkbox"
                                                        class="checkbox checkbox-secondary checkbox-xs"
                                                        :checked="negatedRacialDisadvantages.includes(@js($disadvantage))"
                                                        @change="toggleRacialDisadvantageNegation(@js($disadvantage), $event.target.checked)"
                                                    >
                                                    <span>Mit einem Vorteilswert negieren</span>
                                                </label>
                                            </template>
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
                    </section>
                    <section id="char-editor-equipment" class="border-t border-base-300/70 pt-6" data-testid="char-editor-equipment-section">
                        <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-base-content/45">Schritt 6</p>
                                <h2 id="equipment-heading" class="mt-1 text-xl font-semibold text-primary">Ausrüstung</h2>
                            </div>
                            <span class="badge badge-outline" aria-live="polite" x-text="'Gegenstände: ' + equipmentCount() + ' / ' + equipmentLimit() + ' · High-Tech: ' + highTechEquipmentCount() + ' / ' + highTechEquipmentLimit()"></span>
                        </div>

                        <input type="hidden" name="clothing" :value="clothing">
                        <input type="hidden" name="active_armor_id" :value="activeArmorId">
                        <input type="hidden" name="active_shield_id" :value="activeShieldId">
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
                                        <option :value="item.id" x-bind:selected="clothing === item.id" x-text="item.name + ' · TW ' + item.tw + ' · B ' + item.bucks"></option>
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

                        <div class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-2" x-show="selectedArmorEntries().length || selectedShieldEntries().length" x-cloak data-testid="active-protection-selection">
                            <fieldset class="rounded-md border border-base-300 bg-base-200/40 p-3 text-sm">
                                <legend class="px-1 font-medium text-base-content">Aktive Rüstung</legend>
                                <label class="mt-1 flex items-center gap-2">
                                    <input type="radio" class="radio radio-primary radio-sm" name="active-armor-choice" value="" :checked="activeArmorId === ''" @change="setActiveArmor('')">
                                    <span>Keine Rüstung angelegt</span>
                                </label>
                                <template x-for="entry in selectedArmorEntries()" :key="'active-armor-' + entry.id">
                                    <label class="mt-2 flex items-center justify-between gap-3">
                                        <span class="flex items-center gap-2">
                                            <input type="radio" class="radio radio-primary radio-sm" name="active-armor-choice" :value="entry.id" :checked="activeArmorId === entry.id" @change="setActiveArmor(entry.id)">
                                            <span x-text="entry.item.name"></span>
                                        </span>
                                        <span class="text-xs text-base-content/60" x-text="'SF ' + entry.item.combat.protection + ' · BM ' + entry.item.combat.movementModifier"></span>
                                    </label>
                                </template>
                            </fieldset>

                            <fieldset class="rounded-md border border-base-300 bg-base-200/40 p-3 text-sm">
                                <legend class="px-1 font-medium text-base-content">Aktiver Schild</legend>
                                <label class="mt-1 flex items-center gap-2">
                                    <input type="radio" class="radio radio-primary radio-sm" name="active-shield-choice" value="" :checked="activeShieldId === ''" @change="setActiveShield('')">
                                    <span>Keinen Schild geführt</span>
                                </label>
                                <template x-for="entry in selectedShieldEntries()" :key="'active-shield-' + entry.id">
                                    <label class="mt-2 flex items-center justify-between gap-3">
                                        <span class="flex items-center gap-2">
                                            <input type="radio" class="radio radio-primary radio-sm" name="active-shield-choice" :value="entry.id" :checked="activeShieldId === entry.id" @change="setActiveShield(entry.id)">
                                            <span x-text="entry.item.name"></span>
                                        </span>
                                        <span class="text-xs text-base-content/60" x-text="'Abwehr +' + entry.item.combat.defenseBonus"></span>
                                    </label>
                                </template>
                            </fieldset>
                        </div>

                        <div class="mt-4">
                            <label for="equipment" class="block text-sm font-medium text-base-content mb-1">Notizen zur Ausrüstung</label>
                            <x-textarea name="equipment" id="equipment" rows="3" x-model="equipment" aria-labelledby="equipment-heading" />
                        </div>
                    </section>

                    <section id="char-editor-export" class="border-t border-base-300/70 pt-6" data-testid="char-editor-export-section">
                        <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-base-content/45">Schritt 7</p>
                                <h2 class="mt-1 text-xl font-semibold text-primary">Export</h2>
                            </div>
                            <span class="badge" :class="formValid() ? 'badge-success' : 'badge-outline'" aria-live="polite" x-text="formValid() ? 'Bereit' : completionIssues().length + ' offen'"></span>
                        </div>
                        <template x-if="!formValid()">
                            <ul class="mb-4 grid grid-cols-1 gap-2 text-sm text-base-content/75 sm:grid-cols-2" data-testid="char-editor-completion-issues">
                                <template x-for="issue in completionIssues()" :key="issue">
                                    <li class="rounded-md border border-base-300 bg-base-200/40 px-3 py-2" x-text="issue"></li>
                                </template>
                            </ul>
                        </template>
                        @if($slotSummary)
                            <div class="mb-4 rounded-md border border-base-300 bg-base-200/40 p-3 text-sm" data-testid="char-editor-slot-status">
                                <div class="flex flex-wrap items-center justify-between gap-3">
                                    <p class="font-medium text-base-content">Speicher: {{ $slotSummary['used_slots'] }} / {{ $slotSummary['total_slots'] }} Slots belegt</p>
                                    <p class="text-base-content/70">Freie Slots: {{ $slotSummary['free_slots'] }} - Slotkauf: {{ $slotSummary['slot_cost_baxx'] }} Baxx</p>
                                </div>
                                @if($slotSummary['wallet_warning'])
                                    <p class="mt-2 text-warning">{{ $slotSummary['wallet_warning'] }}</p>
                                @endif
                            </div>
                        @endif

                        <div class="flex flex-wrap justify-end gap-2">
                            <x-button id="pdf-button" type="submit" formaction="{{ route('rpg.char-editor.pdf') }}" formtarget="_blank" x-bind:disabled="!formValid()" label="PDF drucken" icon="o-document-text" class="btn-ghost" data-testid="pdf-button" />
                            <x-button id="submit-button" type="submit" formaction="{{ route('rpg.characters.store') }}" x-bind:disabled="!formValid()" label="Speichern" icon="o-check" class="btn-primary" data-testid="submit-button" />
                        </div>
                    </section>
                </fieldset>
            </form>
        </x-ui.panel>
    </x-member-page>
</x-member-layout>
