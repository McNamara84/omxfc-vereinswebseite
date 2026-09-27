<x-member-layout>
    <x-member-page class="max-w-5xl">
        <x-ui.page-header title="Probe anfordern" eyebrow="AG Rollenspiel" description="Wähle Charaktere und Vorgaben. Prüfe die Berechnung vor dem Versand." />
        <a class="btn btn-ghost my-3" href="{{ route('rpg.checks.index') }}">Alle Proben</a>
        <form x-data="rpgCheckForm({{ \Illuminate\Support\Js::from($config) }})" @submit.prevent="preview()" @input="invalidate()" @change="invalidate()" class="space-y-5">
            @csrf
            <div x-cloak x-show="error" role="alert" class="alert alert-error whitespace-pre-wrap" x-text="error"></div>
            <fieldset :disabled="busy" class="space-y-5">
                <legend class="font-semibold">Aufforderung</legend>
                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="fieldset">Vergleich<select class="select w-full" x-model="mode" @change="changeMode()"><option value="fixed">Gegen Schwierigkeit</option><option value="opposed">Widerstandsprobe zwischen Spielercharakteren</option><option disabled value="npc">Gegen Nichtspielercharakter – noch nicht verfügbar</option></select></label>
                    <label class="fieldset">Sichtbarkeit<select class="select w-full" required x-model="visibility"><option value="">Bitte bewusst auswählen</option><option value="open">Offen – Besitzer und Leitung sehen das Ergebnis</option><option value="hidden">Verdeckt – nur die Leitung sieht das Ergebnis</option></select></label>
                </div>
                <label class="fieldset">Beschreibung (für Spieler sichtbar)<textarea class="textarea w-full" required maxlength="2000" x-model="description"></textarea></label>
                <p class="text-sm">Bei verdeckten Proben bleiben Schwierigkeit, zusätzliche Modifikatoren und Ergebnis ausschließlich für die Leitung sichtbar.</p>
                <div x-show="mode === 'fixed'" class="space-y-2">
                    <label class="fieldset">Schwierigkeitsgrad<input class="input w-full" type="number" step="1" min="-1000000" max="1000000" :required="mode === 'fixed'" x-model="difficulty" /></label>
                    <div class="flex flex-wrap gap-2">@foreach($config['difficulties'] as $label => $value)<button type="button" class="btn btn-sm" @click="difficulty = {{ $value }}; invalidate()">{{ $label }} · {{ $value }}</button>@endforeach</div>
                </div>
                <div class="flex flex-wrap items-end gap-3">
                    <label class="fieldset grow">Charakter auswählen<select class="select w-full" x-model="selectedCharacter"><option value="">Bitte wählen</option><template x-for="character in config.characters" :key="character.id"><option :value="character.id" x-text="character.label" :disabled="participants.some(p => p.character_id === character.id)"></option></template></select></label>
                    <button type="button" class="btn" @click="addParticipant()" :disabled="!selectedCharacter || participants.length >= (mode === 'opposed' ? 2 : 100)">Charakter hinzufügen</button>
                </div>
                @if(empty($config['characters']))<p>Es sind keine Charaktere anderer AG-Mitglieder vorhanden.</p>@endif
                <ul class="space-y-2"><template x-for="(participant, index) in participants" :key="participant.character_id"><li class="flex flex-wrap gap-2 items-center"><span x-text="participant.label"></span><button type="button" class="btn btn-ghost btn-sm" @click="participants.splice(index, 1); invalidate()" :aria-label="'Charakter entfernen: ' + participant.label">Entfernen</button></li></template></ul>
                <template x-for="settings in specifications()" :key="settings.uid">
                    <section class="rounded-xl border border-base-300 p-4 space-y-3">
                        <h2 class="font-semibold" x-text="specificationLabel(settings)"></h2>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <label class="fieldset">Probenart<select class="select w-full" x-model="settings.check_type"><option value="attribute">Attributsprobe (3 × Attribut)</option><option value="skill">Fertigkeitsprobe (Fertigkeit + Attribut)</option></select></label>
                            <label class="fieldset">Attribut<select class="select w-full" x-model="settings.attribute_key">@foreach($config['attributes'] as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></label>
                            <label x-show="settings.check_type === 'skill'" class="fieldset">Fertigkeit<select class="select w-full" x-model="settings.skill_name" :required="settings.check_type === 'skill'"><option value="">Bitte wählen</option><template x-for="name in availableSkills(settings)" :key="name"><option :value="name" x-text="name"></option></template></select></label>
                        </div>
                        <p class="text-sm">Zusätzliche Boni/Mali werden auf den Wurf addiert. Berücksichtige denselben Umstand nicht nochmals in der Schwierigkeit. Gespeicherte Charakterboni sind bereits enthalten.</p>
                        <template x-for="(modifier, index) in settings.modifiers" :key="modifier.uid">
                            <div class="grid gap-2 sm:grid-cols-[8rem_1fr_auto] items-end">
                                <label class="fieldset">Bonus / Malus<input class="input w-full" type="number" required min="-1000" max="1000" step="1" x-model="modifier.value" /></label>
                                <label class="fieldset">Grund des Modifikators<input class="input w-full" required maxlength="255" x-model="modifier.description" /></label>
                                <button type="button" class="btn btn-ghost" @click="settings.modifiers.splice(index, 1); invalidate()">Modifikator entfernen</button>
                            </div>
                        </template>
                        <button type="button" class="btn btn-outline btn-sm" @click="addModifier(settings)" :disabled="settings.modifiers.length >= 20">Modifikator hinzufügen</button>
                    </section>
                </template>
                <button type="submit" class="btn btn-primary" :disabled="!participants.length || (mode === 'opposed' && participants.length !== 2)">Vorschau prüfen</button>
            </fieldset>
            <section x-cloak x-show="previewResult" class="rounded-xl border border-primary p-5 space-y-3" aria-live="polite">
                <h2 class="font-semibold">Vorschau der Proben</h2>
                <p>Diese Werte werden beim Anfordern festgehalten. Spätere Charakterverbesserungen ändern diese Proben nicht.</p>
                <template x-for="row in previewResult?.participants ?? []" :key="row.rpg_character_id">
                    <div class="border-b border-base-300 pb-3">
                        <strong x-text="row.character_name"></strong>
                        <p><span x-text="row.check_type === 'attribute' ? '3 × ' + config.attributes[row.attribute_key] + ' (' + row.attribute_value + ')' : row.skill_name + ' (' + row.skill_value + ') + ' + config.attributes[row.attribute_key] + ' (' + row.attribute_value + ')'"></span> + Modifikatoren <span x-text="row.modifier_total"></span> = 2W6 + <span x-text="row.base_total"></span></p>
                        <template x-for="(modifier, index) in row.modifiers" :key="index"><p><span x-text="modifier.description"></span>: <span x-text="modifier.value"></span></p></template>
                    </div>
                </template>
                <p x-text="mode === 'fixed' ? 'Schwierigkeit: ' + difficulty : 'Das höhere Gesamtergebnis gewinnt. Gleichstände entscheidet die Leitung.'"></p>
                <p class="font-semibold" x-text="visibility === 'hidden' ? 'Verdeckte Proben' : 'Offene Proben'"></p>
                <button type="button" class="btn btn-primary" @click="save()" :disabled="busy">Proben anfordern</button>
            </section>
            <p x-show="busy" role="status">Anfrage wird verarbeitet …</p>
        </form>
    </x-member-page>
</x-member-layout>
