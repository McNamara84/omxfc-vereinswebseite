<x-app-layout>
    <x-member-page class="max-w-6xl">
        <x-ui.page-header
            eyebrow="RPG"
            title="Meine Charaktere"
            description="Verwalte deine gespeicherten RPG-Charaktere und oeffne sie jederzeit wieder als PDF-Charakterbogen."
            data-testid="rpg-characters-header"
        >
            <x-slot:actions>
                <a href="{{ route('rpg.char-editor') }}" class="btn btn-primary">
                    <x-icon name="o-plus" class="h-4 w-4" />
                    Neuer Charakter
                </a>
            </x-slot:actions>
        </x-ui.page-header>

        @if(session('success'))
            <div class="alert alert-success mt-6" data-testid="rpg-character-success">
                <span>{{ session('success') }}</span>
            </div>
        @endif

        @if($errors->any())
            <div class="alert alert-error mt-6" data-testid="rpg-character-errors">
                <ul class="list-disc pl-5">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,1fr)_18rem]">
            <x-ui.panel title="Gespeicherte Charaktere" description="Der erste Speicherplatz ist kostenlos. Geloeschte Charaktere geben ihren belegten Slot wieder frei.">
                @if($characters->isEmpty())
                    <x-ui.empty-state
                        title="Noch keine Charaktere gespeichert"
                        description="Erstelle im Charakter-Editor deinen ersten Charakter und speichere ihn anschliessend hier ab."
                        icon="o-document-text"
                    />
                @else
                    <div class="overflow-x-auto">
                        <table class="table" data-testid="rpg-characters-table">
                            <thead>
                                <tr>
                                    <th>Charakter</th>
                                    <th>Rasse</th>
                                    <th>Kultur</th>
                                    <th>Gespeichert</th>
                                    <th class="text-right">Aktionen</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($characters as $character)
                                    @php($characterPayload = $character->payload['character'] ?? [])
                                    <tr data-testid="rpg-character-row">
                                        <td>
                                            <div class="font-medium text-base-content">{{ $character->displayName() }}</div>
                                            <div class="text-xs text-base-content/60">{{ $characterPayload['player_name'] ?? '' }}</div>
                                        </td>
                                        <td>{{ $characterPayload['race'] ?? '' }}</td>
                                        <td>{{ $characterPayload['culture'] ?? '' }}</td>
                                        <td>{{ $character->created_at?->format('d.m.Y H:i') }}</td>

                                        <td>
                                            <div class="flex justify-end gap-2">
                                                <a href="{{ route('rpg.characters.pdf', $character) }}" target="_blank" rel="noopener noreferrer" class="btn btn-ghost btn-sm" data-testid="rpg-character-pdf-button">
                                                    <x-icon name="o-document-text" class="h-4 w-4" />
                                                    PDF
                                                </a>
                                                <form method="POST" action="{{ route('rpg.characters.destroy', $character) }}" onsubmit="return confirm('Diesen Charakter wirklich loeschen?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-ghost btn-sm text-error" data-testid="rpg-character-delete-button">
                                                        <x-icon name="o-trash" class="h-4 w-4" />
                                                        Loeschen
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-ui.panel>

            <aside class="space-y-4">
                <x-ui.panel title="Speicher-Slots" description="Zusaetzliche Slots kosten jeweils {{ $slotSummary['slot_cost_baxx'] }} Baxx.">
                    <dl class="space-y-3 text-sm" data-testid="rpg-character-slot-summary">
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-base-content/70">Belegt</dt>
                            <dd class="font-semibold">{{ $slotSummary['used_slots'] }} / {{ $slotSummary['total_slots'] }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-base-content/70">Freie Slots</dt>
                            <dd class="font-semibold">{{ $slotSummary['free_slots'] }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-base-content/70">Gekauft</dt>
                            <dd class="font-semibold">{{ $slotSummary['purchased_slots'] }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-base-content/70">Verfuegbare Baxx</dt>
                            <dd class="font-semibold">{{ $slotSummary['available_baxx'] ?? '-' }}</dd>
                        </div>
                    </dl>

                    @if($slotSummary['wallet_warning'])
                        <p class="mt-4 rounded-md border border-warning/30 bg-warning/10 p-3 text-sm text-warning">
                            {{ $slotSummary['wallet_warning'] }}
                        </p>
                    @endif

                    <form method="POST" action="{{ route('rpg.characters.slots.purchase') }}" class="mt-5">
                        @csrf
                        <button
                            type="submit"
                            class="btn btn-primary w-full"
                            @disabled(! $slotSummary['can_purchase_slot'])
                            onclick="return confirm('Einen weiteren Speicher-Slot fuer {{ $slotSummary['slot_cost_baxx'] }} Baxx kaufen?');"
                            data-testid="rpg-character-buy-slot-button"
                        >
                            <x-icon name="o-circle-stack" class="h-4 w-4" />
                            Slot kaufen
                        </button>
                    </form>
                </x-ui.panel>
            </aside>
        </div>
    </x-member-page>
</x-app-layout>
