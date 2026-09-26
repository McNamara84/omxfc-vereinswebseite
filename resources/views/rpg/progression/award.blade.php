<x-member-layout>
    <x-member-page class="max-w-5xl">
        <x-ui.page-header title="Erfahrungspunkte vergeben" eyebrow="AG Rollenspiel" description="Bewerte das abgeschlossene Abenteuer nach Seite 54 des Regelwerks. Prüfe die Vorschau vor der verbindlichen Vergabe." />
        <a href="{{ route('rpg.adventures.index') }}" class="btn btn-ghost my-3">Zur Vergabeübersicht</a>
        <form x-data="rpgProgression({{ \Illuminate\Support\Js::from($config) }})" @submit.prevent="preview()" @input="invalidate()" @change="invalidate()" class="space-y-5">
            @csrf
            <div x-cloak :style="error ? {} : { display: 'none' }" x-ref="error" tabindex="-1" role="alert" class="alert alert-error whitespace-pre-wrap" x-text="error"></div>
            <fieldset :disabled="busy" class="space-y-5">
                <legend class="font-semibold">Abenteuer</legend>
                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="fieldset">Titel<input class="input w-full" required maxlength="255" x-model="title" /></label>
                    <label class="fieldset">Abschlussdatum<input class="input w-full" type="date" required :max="config.today" x-model="completedOn" /></label>
                    <label class="fieldset">Spielzeit: Stunden<input class="input w-full" type="number" required min="0" max="166666" x-model="hours" /></label>
                    <label class="fieldset">Zusätzliche Minuten<input class="input w-full" type="number" required min="0" max="59" x-model="minutes" /></label>
                    <label class="fieldset">Zyklusabschluss<select class="select w-full" x-model="cycleBonus"><option value="0">Kein Zyklusabschluss</option><option value="1">Nicht erfolgreich · 1 EP</option><option value="2">Neutral · 2 EP</option><option value="4">Sieg · 4 EP</option></select></label>
                </div>
                <div class="flex flex-wrap items-end gap-3">
                    <label class="fieldset grow">Charakter auswählen<select class="select w-full" x-model="selectedCharacter"><option value="">Bitte wählen</option><template x-for="character in config.characters" :key="character.id"><option :value="character.id" x-text="character.name"></option></template></select></label>
                    <button type="button" class="btn" @click="addParticipant()">Charakter hinzufügen</button>
                </div>
                <template x-for="(participant, index) in participants" :key="participant.character_id">
                    <section class="rounded-xl border border-base-300 p-4 space-y-3">
                        <h2 class="font-semibold" x-text="participant.label"></h2>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <label class="flex gap-2"><input class="checkbox" type="checkbox" x-model="participant.survived" />Abenteuer überlebt · 1 EP</label>
                            <label class="flex gap-2"><input class="checkbox" type="checkbox" x-model="participant.humor" />Runde zum Lachen gebracht · 1 EP</label>
                            <label class="flex gap-2"><input class="checkbox" type="checkbox" x-model="participant.rescue" />Leben für andere riskiert · 1 EP</label>
                            <label class="flex gap-2"><input class="checkbox" type="checkbox" x-model="participant.unwounded" />Durchgehend unverwundet · 1 EP</label>
                            <label class="fieldset">Rollenspiel<select class="select w-full" x-model="participant.roleplay"><option value="0">Normal · 0 EP</option><option value="1">Gut · 1 EP</option><option value="2">Brillant · 2 EP</option></select></label>
                            <label class="fieldset">Abweichende EP (optional)<input class="input w-full" type="number" min="0" max="1000000" placeholder="Automatisch berechnen" x-model="participant.points" /></label>
                        </div>
                        <label class="fieldset">Begründung der Abweichung<textarea class="textarea w-full" maxlength="4000" x-model="participant.reason"></textarea></label>
                        <button type="button" class="btn btn-ghost" @click="participants.splice(index, 1); invalidate()">Charakter entfernen</button>
                    </section>
                </template>
                <p x-show="!participants.length">Wähle mindestens einen teilnehmenden Charakter.</p>
                <button class="btn btn-primary" type="submit" :disabled="!participants.length">Vergabe prüfen</button>
            </fieldset>
            <section x-cloak x-show="previewResult" class="rounded-xl border border-primary p-5 space-y-3" aria-live="polite">
                <h2 class="font-semibold">Vorschau der Vergabe</h2>
                <template x-for="award in previewResult?.awards ?? []" :key="award.character_id">
                    <div><p><strong x-text="award.character_name"></strong>: <span x-text="award.points"></span> EP (Regelwert: <span x-text="award.calculated_points"></span> EP)</p><p x-text="award.reason" class="whitespace-pre-wrap"></p></div>
                </template>
                <p>Die bestätigte Vergabe wird dauerhaft dokumentiert. Positive Vergaben erscheinen im Dashboard.</p>
                <button type="button" class="btn btn-primary" @click="save()" :disabled="busy">EP verbindlich vergeben</button>
            </section>
            <p x-show="busy" role="status">Anfrage wird verarbeitet …</p>
        </form>
    </x-member-page>
</x-member-layout>
