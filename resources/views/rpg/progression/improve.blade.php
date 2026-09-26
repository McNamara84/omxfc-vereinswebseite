<x-member-layout>
    <x-member-page class="max-w-5xl">
        <x-ui.page-header title="Charakter verbessern" eyebrow="Rollenspiel" :description="$character->displayName()" />
        <div class="flex flex-wrap gap-2 my-4"><a class="btn btn-ghost" href="{{ route('rpg.characters.index') }}">Meine Charaktere</a><a class="btn btn-ghost" href="{{ route('rpg.characters.history', $character) }}">EP und Verlauf</a></div>
        @include('rpg.progression.partials.messages')
        <p class="font-semibold">Erhalten: {{ $history['received'] }} EP · Ausgegeben: {{ $history['spent'] }} EP · Verfügbar: {{ $history['balance'] }} EP</p>
        @include('rpg.progression.partials.state')
        @if($pending)
            <div class="alert">Ein Antrag liegt bereits zur Prüfung vor. <a class="link" href="{{ route('rpg.advancements.show', $pending) }}">Antrag ansehen oder zurückziehen</a></div>
        @else
            <p class="my-4">Attribute kosten 30 EP je Punkt, Fertigkeiten 6/12/18 EP je Zielstufe; psychische Fertigkeiten das Doppelte. Vorteile und das Ablegen von Nachteilen kosten 20 EP, Gestaltwandler 60 EP. Die Änderungen gelten erst nach Genehmigung der AG-Leitung.</p>
            <form x-data="rpgProgression({{ \Illuminate\Support\Js::from($config) }})" @submit.prevent="preview()" @input="invalidate()" @change="invalidate()" class="space-y-5">
                @csrf
                <div x-cloak :style="error ? {} : { display: 'none' }" x-ref="error" tabindex="-1" role="alert" class="alert alert-error whitespace-pre-wrap" x-text="error"></div>
                <fieldset :disabled="busy" class="space-y-4">
                    <legend class="font-semibold">Gewünschte Verbesserungen</legend>
                    <template x-for="(operation, index) in operations" :key="operation.uid">
                        <section class="border border-base-300 rounded-xl p-4 space-y-3">
                            <h2 class="font-semibold" x-text="'Änderung ' + (index + 1)"></h2>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <label class="fieldset">Art der Änderung<select class="select w-full" x-model="operation.type" @change="changeType(operation)"><option value="skill">Fertigkeit steigern</option><option value="attribute">Attribut steigern</option><option value="advantage">Vorteil erwerben</option><option value="disadvantage">Nachteil ablegen</option></select></label>
                                <label class="fieldset">Eigenschaft<select class="select w-full" x-model="operation.name" @change="operation.target = ''; operation.specialization = ''"><template x-for="name in config.names[operation.type]" :key="name"><option :value="name" x-text="name"></option></template></select></label>
                                <label class="fieldset" x-show="['attribute', 'skill'].includes(operation.type)">Steigerung um Punkte<input class="input w-full" type="number" min="1" max="100" x-model="operation.steps" /></label>
                                <label class="fieldset" x-show="operation.type === 'skill' && ['Beruf', 'Kunde', 'Unterhalten', 'Wissenschaftler'].includes(operation.name)">Spezialisierung (falls zutreffend)<input class="input w-full" maxlength="100" x-model="operation.specialization" placeholder="Zum Beispiel Wetter oder Geschichte" /></label>
                                <label class="fieldset" x-show="operation.type === 'advantage' && config.advantages[operation.name]?.targets.length">Ziel des Vorteils<select class="select w-full" x-model="operation.target"><option value="">Bitte wählen</option><template x-for="target in config.advantages[operation.name]?.targets ?? []" :key="target"><option :value="target" x-text="target"></option></template></select></label>
                            </div>
                            <p class="text-sm" x-show="operation.type === 'advantage'" x-text="config.advantages[operation.name]?.description ?? ''"></p>
                            <label class="fieldset" x-show="operation.name === 'Sprachen' || operation.name === 'Sprachbegabt'">Alle beherrschten Sprachen / Dialekte, eine pro Zeile<textarea class="textarea w-full" x-model="operation.languageText"></textarea></label>
                            <div x-show="operation.type === 'advantage' && operation.name === 'High-Tech-Ausrüstung'" class="grid gap-3 sm:grid-cols-2">
                                <template x-for="slot in [0, 1, 2, 3]" :key="slot"><label class="fieldset"><span x-text="'High-Tech-Gegenstand ' + (slot + 1)"></span><select class="select w-full" x-model="operation.items[slot]"><option value="">Bitte wählen</option><template x-for="item in config.equipment" :key="item.id"><option :value="item.id" x-text="item.name"></option></template></select></label></template>
                            </div>
                            <label class="fieldset">Begründung aus dem Abenteuer / erforderliche Details<textarea class="textarea w-full" required maxlength="4000" x-model="operation.reason" placeholder="Wie wurde die Verbesserung möglich? Bei Tiergefährte auch das Tier benennen."></textarea></label>
                            <button type="button" class="btn btn-ghost" @click="operations.splice(index, 1); invalidate()">Änderung entfernen</button>
                        </section>
                    </template>
                    <div class="flex flex-wrap gap-3"><button type="button" class="btn" @click="addOperation()" :disabled="operations.length >= 30">Weitere Änderung</button><button type="submit" class="btn btn-primary" :disabled="!operations.length">Verbesserung prüfen</button></div>
                </fieldset>
                <section x-cloak x-show="previewResult" aria-live="polite" class="border border-primary rounded-xl p-5 space-y-3">
                    <h2 class="font-semibold">Vorschau der Verbesserung</h2>
                    <template x-for="(change, index) in previewResult?.changes ?? []" :key="index"><p><strong x-text="change.name + (change.target ? ' · ' + change.target : '')"></strong>: <span x-text="change.before"></span> → <span x-text="change.after"></span> · <span x-text="change.cost"></span> EP</p></template>
                    <p>Gesamt: <strong x-text="previewResult?.cost"></strong> EP · Nach Genehmigung verbleiben <strong x-text="previewResult?.remaining"></strong> EP.</p>
                    <button type="button" class="btn btn-primary" @click="save()" :disabled="busy">Verbesserung beantragen</button>
                </section>
                <p x-show="busy" role="status">Anfrage wird verarbeitet …</p>
            </form>
        @endif
    </x-member-page>
</x-member-layout>
