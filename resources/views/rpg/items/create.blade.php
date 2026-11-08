<x-member-layout>
    <x-member-page class="max-w-5xl">
        <x-ui.page-header title="{{ $itemType ? 'Neue Gegenstandsversion' : 'Neuen Gegenstand entwerfen' }}" eyebrow="AG Rollenspiel" description="Schaden, Schutzfaktor und Tauschwert berechnet die Anwendung nach S. 39–40. Weitere Werte legst du anhand von Vergleichen oder Fachtexten fest." />
        <a class="btn btn-ghost my-3" href="{{ route('rpg.items.index') }}">Gegenstände verwalten</a>
        <x-lazy-feature name="rpg-items">
        <div x-data="rpgItems(@js($config))">
            <p x-show="error" x-text="error" x-ref="error" tabindex="-1" role="alert" class="alert alert-error whitespace-pre-line" x-cloak></p>
            <form @submit.prevent="preview()" @input="invalidate()" @change="invalidate()" class="space-y-5">
                @csrf
                <fieldset :disabled="busy" class="grid gap-4 sm:grid-cols-2">
                    <legend class="font-semibold text-xl mb-3">Eigenschaften</legend>
                    <label class="fieldset">Name<input class="input w-full" required maxlength="120" x-model="input.name" /></label>
                    <label class="fieldset">Kategorie<select aria-label="Kategorie" class="select w-full" x-model="input.category" @change="categoryChanged()" {{ $itemType ? 'disabled' : '' }}><template x-for="(label, id) in config.categories" :key="id"><option :value="id" x-text="label"></option></template></select></label>
                    <label class="fieldset sm:col-span-2">Beschreibung und besondere Wirkungen<textarea class="textarea w-full" required maxlength="4000" x-model="input.description"></textarea></label>
                    <label class="fieldset">Größe<select class="select w-full" x-model="input.size"><option value="small">Klein</option><option value="medium">Mittel</option><option value="large">Groß</option><option value="huge">Riesig (sonstige Gegenstände)</option></select></label>
                    <label class="fieldset">Häufigkeit<select class="select w-full" x-model="input.rarity"><option value="common">Gewöhnlich</option><option value="rare">Selten</option><option value="very_rare">Sehr selten</option></select></label>
                    <label class="fieldset">Materialzuschlag<select class="select w-full" x-model.number="input.material"><option value="0">Keiner</option>@foreach(range(2, 6) as $value)<option value="{{ $value }}">+{{ $value }}</option>@endforeach</select></label>
                    <label class="fieldset">Zusätzliche Nützlichkeit (Faktor)<input class="input w-full" type="number" min="1" max="10" step="1" x-model.number="input.utility" /></label>
                    <label class="flex gap-2 items-center"><input class="checkbox" type="checkbox" x-model="input.beautiful" />Schöne Gestaltung (+2 TW)</label>
                    <label class="flex gap-2 items-center"><input class="checkbox" type="checkbox" x-model="input.high_tech" />High-Tech-/Techno-Gegenstand</label>
                    <label class="fieldset" x-show="input.high_tech">High-Tech-Faktor (5–1000)<input class="input w-full" type="number" min="5" max="1000" x-model.number="input.tech_factor" :disabled="!input.high_tech" /></label>
                    <label class="fieldset">Mindestbildung<input class="input w-full" type="number" min="0" max="100" x-model.number="input.minimum_education" /><span>Schießpulverwaffen: 2, Energiewaffen: 3 (wird automatisch gesetzt).</span></label>
                    <label class="fieldset">Bucks-Richtpreis<input class="input w-full" required type="number" min="0" max="1000000000" x-model.number="input.bucks" /></label>
                    <p class="text-sm">S. 39: 1 Buck ≈ 1 Euro; Waffen mindestens dreifacher Vergleichswert, nützliche funktionsfähige High-Tech-Gegenstände etwa 10–100-fach. S. 38 erlaubt Preisentscheidungen durch die Spielleitung.</p>
                    <label class="fieldset sm:col-span-2">Vergleichsgegenstand oder fachliche Grundlage<textarea class="textarea w-full" required maxlength="2000" x-model="input.reference"></textarea></label>
                </fieldset>
                <fieldset class="grid gap-4 sm:grid-cols-2" x-show="['melee_weapons','ranged_weapons'].includes(input.category)" :disabled="busy">
                    <legend class="font-semibold text-xl mb-3">Waffenwerte (S. 39–41)</legend>
                    <label class="fieldset">Handbelegung<select class="select w-full" x-model.number="input.hands"><option value="1">Eine Hand</option><option value="2">Zwei Hände</option></select></label>
                    <div x-show="input.category === 'melee_weapons'" class="space-y-3"><label class="flex gap-2"><input class="checkbox" type="checkbox" x-model="input.masterwork" />Meisterarbeit (+1 Schaden)</label><label class="flex gap-2"><input class="checkbox" type="checkbox" x-model="input.throwable" />Zusätzlicher Wurfmodus</label><label class="flex gap-2"><input class="checkbox" type="checkbox" value="st" x-model="input.attributes" />ST als Angriffsattribut</label><label class="flex gap-2"><input class="checkbox" type="checkbox" value="ge" x-model="input.attributes" />GE als Angriffsattribut</label></div>
                    <template x-if="has('weapon_type')"><div class="contents">
                        <label class="fieldset" x-show="input.category === 'ranged_weapons'">Fernwaffentyp<select class="select w-full" x-model="input.weapon_type"><option>Wurfgeschoss</option><option>Projektilwaffe</option><option>Schießpulverwaffe</option><option>Energiewaffe</option></select></label>
                        <label class="fieldset">Präzision<input class="input w-full" type="number" min="-2" max="2" x-model.number="input.precision" /></label>
                        <label class="fieldset">Feuerrate<select class="select w-full" x-model="input.fire_rate"><option>E</option><option>H</option><option>S</option><option>A</option></select></label>
                        <label class="fieldset">Magazingröße (0 ohne Magazin)<input class="input w-full" type="number" min="0" max="1000000" x-model.number="input.magazine" /></label>
                        <label class="fieldset">Reichweiteninkrement in Metern<input class="input w-full" type="number" min="1" max="1000000" x-model.number="input.range_increment" /></label>
                        <label class="fieldset">Maximale Reichweite in Metern<input class="input w-full" type="number" min="1" max="1000000" x-model.number="input.max_range" /></label>
                        <label class="fieldset">Munition<select class="select w-full" x-model="input.ammunition_id"><option value="">Neue Munitionsart</option><template x-for="(ammo, id) in config.ammunition" :key="id"><option :value="id" x-text="ammo.name"></option></template></select></label>
                        <div x-show="!input.ammunition_id"><label class="fieldset">Name der neuen Munition<input class="input w-full" maxlength="120" x-model="input.ammunition_name" /></label><label class="fieldset">Einheit<input class="input w-full" maxlength="40" x-model="input.ammunition_unit" /></label></div>
                    </div></template>
                </fieldset>
                <fieldset x-show="input.category === 'armor'" class="grid gap-4 sm:grid-cols-2" :disabled="busy"><legend class="font-semibold text-xl mb-3">Rüstung (S. 39–40)</legend><label class="fieldset">Effektivität<select class="select w-full" x-model.number="input.effectiveness"><option value="0">Gering (SF 0)</option><option value="1">Mittel (SF 1)</option><option value="2">Hoch (SF 2)</option></select></label><label class="fieldset">Beweglichkeitsmodifikator<input class="input w-full" type="number" min="-100" max="0" x-model.number="input.movement_modifier" /></label><p>High-Tech-Rüstungen erhalten +1 SF. Schilde geben immer +1 auf Parade und Ausweichen (S. 49).</p></fieldset>
                <fieldset x-show="input.category === 'transport'" class="grid gap-4 sm:grid-cols-2" :disabled="busy"><legend class="font-semibold text-xl mb-3">Fortbewegungsmittel (S. 44)</legend>
                    @foreach(['persons_min' => 'Personen mindestens', 'persons_max' => 'Personen höchstens', 'protection' => 'Schutzwert', 'land_speed' => 'Landgeschwindigkeit km/h', 'water_speed' => 'Wassergeschwindigkeit km/h', 'air_speed' => 'Luftgeschwindigkeit km/h'] as $field => $label)<label class="fieldset">{{ $label }}<input class="input w-full" type="number" min="0" max="1000000" x-model.number="input.{{ $field }}" /></label>@endforeach
                    <label class="flex gap-2"><input class="checkbox" type="checkbox" x-model="input.hover" />Schwebefahrzeug</label>
                </fieldset>
                <button class="btn btn-primary" type="submit" :disabled="busy">Vorschau prüfen</button>
            </form>
            <section x-show="result" x-cloak class="border border-primary rounded-xl p-5 my-5 space-y-3" aria-live="polite" data-testid="item-preview">
                <h2 class="text-xl font-semibold" x-text="result?.definition.name"></h2><p x-text="result?.definition.summary"></p><p>TW <span x-text="result?.definition.tw"></span> · Bucks <span x-text="result?.definition.bucks"></span></p>
                <ol class="list-decimal pl-6"><template x-for="(step, index) in result?.calculation ?? []" :key="index"><li><span x-text="step.label"></span>: <span x-text="step.value"></span></li></template></ol>
                <p x-show="config.revision === 0">Neue Gegenstände sind zunächst nicht als Startausrüstung freigegeben. Die Vergabe von Exemplaren erfolgt anschließend im Inventar.</p>
                <p x-show="config.revision > 0">Vorhandene Exemplare behalten ihre Werte. Die Startfreigabe des Gegenstandstyps bleibt bestehen.</p>
                <button class="btn btn-primary" type="button" @click="save()" :disabled="busy">Gegenstand speichern</button>
            </section>
            <p role="status" x-show="busy">Anfrage wird verarbeitet …</p>
        </div>
        </x-lazy-feature>
    </x-member-page>
</x-member-layout>
