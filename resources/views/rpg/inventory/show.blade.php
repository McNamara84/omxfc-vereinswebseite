<x-member-layout>
    <x-member-page class="max-w-5xl">
        <x-ui.page-header title="Ausrüstung" eyebrow="Rollenspiel" :description="$character->displayName()" />
        <div class="flex flex-wrap gap-2 my-3"><a class="btn btn-ghost" href="{{ route('rpg.characters.index') }}">Meine Charaktere</a>@if($canManage)<a class="btn btn-ghost" href="{{ route('rpg.inventory.index') }}">Ausrüstung verwalten</a>@endif<a class="btn btn-ghost" href="{{ route('rpg.characters.history', $character) }}">EP und Verlauf</a></div>
        @include('rpg.progression.partials.messages')
        @include('rpg.inventory.stock')
        <p class="text-sm my-3">Bucks sind Spielgeld. Gegenstände im laufenden Abenteuer benötigen keine EP; High-Tech-Funde sind auch ohne den gleichnamigen Vorteil möglich. Benutzungsvoraussetzungen gelten weiterhin. Übungskämpfe verwenden eigene Simulationsmunition und verändern diesen Bestand nicht.</p>
        @if($canManage)
        <x-lazy-feature name="rpg-inventory">
        <div x-data="rpgInventory(@js($config))" data-testid="inventory-manager">
            <h2 class="font-semibold text-xl my-4">Neue Buchung</h2>
            <p x-cloak x-show="error" x-text="error" x-ref="error" role="alert" tabindex="-1" class="alert alert-error whitespace-pre-wrap"></p>
            <form @submit.prevent="preview()" @input="invalidate()" @change="invalidate()" class="space-y-4">
                @csrf
                <fieldset :disabled="busy" class="space-y-4 min-w-0">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="fieldset">Vorgang<select aria-label="Vorgang" class="select w-full" x-model="kind" @change="resetKind()">@foreach($kinds as $key => $name)@if(($key === 'initialize') === ! $state['confirmed'])<option value="{{ $key }}">{{ $name }}</option>@endif @endforeach<option x-show="reversalOf !== null" value="reversal">Rückbuchung</option></select></label>
                        <label class="fieldset">Ereignisdatum<input type="date" class="input w-full" x-model="occurredOn" max="{{ $config['today'] }}" required /></label>
                    </div>
                    <label class="fieldset">Begründung aus der Handlung<textarea class="textarea w-full" x-model="reason" x-ref="reason" maxlength="4000" required></textarea></label>
                    <p x-show="kind === 'reversal'">Rückbuchung von Vorgang <span x-text="reversalOf"></span>. Spätere Änderungen bleiben erhalten.</p>
                    <div x-show="['initialize','correct','grant','loss','purchase','sale','swap'].includes(kind)" class="grid gap-3 sm:grid-cols-2">
                        <label class="fieldset">Katalog durchsuchen<input type="search" class="input w-full" x-model="catalogSearch" /></label>
                        <label class="fieldset">Kategorie<select aria-label="Kategorie" class="select w-full" x-model="catalogCategory"><option value="">Alle Kategorien</option>@foreach($config['categories'] as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach</select></label>
                    </div>
                    @foreach(['incoming' => 'Erhaltene Positionen', 'outgoing' => 'Abgegebene Positionen', 'setRows' => 'Sollbestände'] as $side => $title)
                        @php($visibility = match($side) { 'incoming' => "['grant','purchase','swap'].includes(kind)", 'outgoing' => "['loss','sale','swap'].includes(kind)", default => "['initialize','correct'].includes(kind)" })
                        <section x-show="{{ $visibility }}" class="rounded-lg border border-base-300 p-3 min-w-0">
                            <h3 class="font-semibold">{{ $title }}</h3>
                            <template x-for="(row, index) in {{ $side }}" :key="row.uid">
                                <div class="grid gap-2 sm:grid-cols-[minmax(0,1fr)_7rem_auto] my-3">
                                    <label class="fieldset min-w-0">Position<select aria-label="Position" class="select w-full min-w-0" x-model="row.id"><option value="">Bitte wählen</option><template x-for="[id, item] in catalogEntries(row.id)" :key="id"><option :value="id" x-text="item.name + (item.price !== null && item.price !== undefined ? ' · ' + item.price + ' Bucks' : '')"></option></template></select></label>
                                    <label class="fieldset">{{ $side === 'setRows' ? 'Sollmenge' : 'Menge' }}<input class="input w-full" type="number" min="{{ $side === 'setRows' ? 0 : 1 }}" max="1000000" step="1" x-model="row.quantity" /></label>
                                    <button type="button" class="btn btn-ghost self-end" @click="{{ $side }}.splice(index, 1); invalidate()">Entfernen</button>
                                </div>
                            </template>
                            <button type="button" class="btn btn-outline btn-sm mt-2" @click="addRow('{{ $side }}')">{{ $title }} ergänzen</button>
                        </section>
                    @endforeach
                    <div x-show="!['equip','regenerate','reversal'].includes(kind)" class="grid gap-3 sm:grid-cols-2">
                        <label class="fieldset"><span x-text="['initialize','correct'].includes(kind) ? 'Tatsächlicher Bucks-Bestand' : (['purchase','sale'].includes(kind) ? 'Vereinbarter Gesamtpreis in Bucks' : 'Bucks-Betrag')"></span><input class="input w-full" type="number" min="0" max="1000000000" step="1" x-model="amount" /></label>
                        <label class="fieldset" x-show="['money','swap'].includes(kind)">Richtung des Geldes<select aria-label="Richtung des Geldes" class="select w-full" x-model="cashDirection"><option value="income">Einnahme</option><option value="expense">Ausgabe</option></select></label>
                    </div>
                    <section x-show="isTrade()" class="space-y-3">
                        <label class="fieldset">Gegenpartei<select aria-label="Gegenpartei" class="select w-full" x-model="otherId"><option value="">Externe Gegenpartei</option><template x-for="peer in config.peers" :key="peer.id"><option :value="peer.id" x-text="peer.name"></option></template></select></label>
                        <label x-show="!otherId" class="fieldset">Bezeichnung der externen Gegenpartei<input class="input w-full" maxlength="255" x-model="externalParty" /></label>
                        <div x-show="kind !== 'swap'" class="grid gap-3 sm:grid-cols-2"><label class="fieldset">Gewinner der Verhandlung<select class="select w-full" x-model="winner"><option value="none">Keine Preisänderung durch Verhandlung</option><option value="buyer">Käufer</option><option value="seller">Verkäufer</option></select></label><label class="fieldset">Erfolgspunkte<input type="number" class="input w-full" min="0" max="1000" x-model="successPoints" /></label></div>
                        <label class="fieldset">Begründung für abweichenden oder frei festgelegten Preis<textarea class="textarea w-full" x-model="priceReason" maxlength="4000"></textarea></label>
                        <p class="text-sm">Katalogpreise und Tauschwerte sind Richtwerte. Der Gesamtpreis wird durch die Leitung bestätigt. Munition ohne Katalogpreis benötigt eine begründete Preisfestlegung.</p>
                    </section>
                    <label class="fieldset" x-show="kind === 'regenerate'">Vergangene Spielminuten<input type="number" class="input w-full" min="1" max="1000000" x-model="minutes" :disabled="kind !== 'regenerate'" /></label>
                    <section x-show="kind === 'equip'" class="grid gap-3 sm:grid-cols-2">
                        <label class="fieldset">Aktive Kleidung<select class="select w-full" x-model="metadata.clothing"><option value="">Keine</option>@foreach($clothing as $item)@if(($state['stocks']['clothing:'.$item['id']] ?? 0) > 0)<option value="{{ $item['id'] }}">{{ $item['name'] }}</option>@endif @endforeach</select></label>
                        @foreach(['armor' => 'Aktive Rüstung', 'shield' => 'Aktiver Schild'] as $kind => $label)
                            <label class="fieldset">{{ $label }}<select class="select w-full" x-model="metadata.{{ $kind }}"><option value="">Keine</option>@foreach($items as $item)@if(($item['combat']['kind'] ?? '') === $kind && ($state['stocks']['item:'.$item['id']] ?? 0) > 0)<option value="{{ $item['id'] }}">{{ $item['name'] }}</option>@endif @endforeach</select></label>
                        @endforeach
                        <label class="fieldset sm:col-span-2">Ausrüstungsnotizen<textarea class="textarea w-full" maxlength="4000" x-model="metadata.notes"></textarea></label>
                    </section>
                    <section x-show="kind === 'item_version_change'" class="grid gap-3 sm:grid-cols-3">
                        <label class="fieldset">Quellversion<select aria-label="Quellversion" class="select w-full" x-model="versionChange.source"><option value="">Bitte wählen</option>@foreach($catalog as $id => $row)@if(isset($row['type_id']) && ($state['stocks'][$id] ?? 0) > 0)<option value="{{ $id }}">{{ $row['name'] }}</option>@endif @endforeach</select></label>
                        <label class="fieldset">Zielversion<select aria-label="Zielversion" class="select w-full" x-model="versionChange.target"><option value="">Bitte wählen</option>@foreach($catalog as $id => $row)@if(isset($row['type_id']))<option value="{{ $id }}">{{ $row['name'] }}</option>@endif @endforeach</select></label>
                        <label class="fieldset">Anzahl<input class="input w-full" type="number" min="1" max="20" x-model="versionChange.quantity" /></label>
                    </section>
                    <button type="submit" class="btn btn-primary">Buchung prüfen</button>
                </fieldset>
            </form>
            <section x-cloak x-show="previewResult" class="rounded-xl border border-primary p-4 my-4 space-y-3" aria-live="polite" data-testid="inventory-preview">
                <h2 class="font-semibold text-xl">Vorschau der Buchung</h2>
                <template x-for="(entry, id) in previewResult?.entries ?? {}" :key="id"><div><h3 class="font-semibold" x-text="label(id)"></h3><template x-for="change in entry.changes" :key="change.id"><p><span x-text="change.name"></span>: <span x-text="display(change.before)"></span> → <span x-text="display(change.after)"></span></p></template></div></template>
                <p x-show="previewResult?.price">Preis: Basis <span x-text="previewResult?.price?.base ?? 'frei festzulegen'"></span> · Anpassung <span x-text="previewResult?.price?.percent"></span> % · Gerundeter Vorschlag <span x-text="previewResult?.price?.suggested ?? 'unbekannt'"></span> · Vereinbart <span x-text="previewResult?.price?.agreed"></span> Bucks.</p>
                <p x-show="kind === 'swap'">Tauschwerte: erhalten <span x-text="previewResult?.trade_values?.incoming ?? 'unbekannt'"></span> · abgegeben <span x-text="previewResult?.trade_values?.outgoing ?? 'unbekannt'"></span>.</p>
                <p x-show="previewResult?.superseded_requests.length">Offene Verbesserungsanträge werden durch diese Änderung überholt und müssen neu geprüft werden: <span x-text="previewResult?.superseded_requests.join(', ')"></span>.</p>
                <button type="button" class="btn btn-primary" @click="save()" :disabled="busy">Buchung speichern</button>
            </section>
            <p x-show="busy" role="status">Anfrage wird verarbeitet …</p>
            @include('rpg.inventory.journal')
        </div>
        </x-lazy-feature>
        @else
            @include('rpg.inventory.journal')
        @endif
    </x-member-page>
</x-member-layout>
