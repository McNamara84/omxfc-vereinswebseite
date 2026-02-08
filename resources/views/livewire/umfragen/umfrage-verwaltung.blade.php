<div>
    {{-- Header --}}
    <x-header title="Umfrage verwalten" subtitle="Erstellen, bearbeiten und auswerten (nur Admin/Vorstand)." separator data-testid="page-header" />

    {{-- Success/Error Messages --}}
    @if (session()->has('success'))
        <x-alert icon="o-check-circle" class="alert-success mb-6">
            {{ session('success') }}
        </x-alert>
    @endif

    @if ($errors->any())
        <x-alert icon="o-exclamation-triangle" class="alert-error mb-6">
            <p class="font-semibold">Bitte prüfe die Eingaben:</p>
            <ul class="list-disc pl-5 mt-2">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </x-alert>
    @endif

    {{-- Umfrage-Auswahl Card --}}
    <x-card class="mb-8" data-testid="poll-selection-card">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div class="w-full sm:max-w-md">
                <x-select
                    label="Umfrage auswählen"
                    wire:model.live="selectedPollId"
                    placeholder="(Neue Umfrage)"
                    :options="$this->polls->map(fn($poll) => [
                        'id' => $poll->id,
                        'name' => '#' . $poll->id . ' – ' . \Illuminate\Support\Str::limit($poll->question, 60) . ' (' . $poll->status->value . ')'
                    ])"
                    option-value="id"
                    option-label="name"
                    data-testid="poll-select"
                />
            </div>

            <x-button wire:click="newPoll" icon="o-plus">
                Neue Umfrage
            </x-button>
        </div>

        <hr class="my-6 border-base-300" />

        <form wire:submit.prevent="save" class="space-y-6">
            {{-- Frage --}}
            <x-textarea
                label="Frage"
                wire:model="question"
                rows="3"
                placeholder="Gib hier die Umfrage-Frage ein..."
                data-testid="question-textarea"
            />

            {{-- Grid: Menu Label, Sichtbarkeit, Status --}}
            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <x-input
                    label="Link-Name im Menü"
                    wire:model="menuLabel"
                    placeholder="z.B. Abstimmung"
                    data-testid="menu-label-input"
                />

                <div data-testid="visibility-section">
                    <div class="label">
                        <span class="label-text font-medium">Sichtbarkeit</span>
                    </div>
                    <div class="mt-2 space-y-2">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" wire:model="visibility" name="visibility" value="internal" class="radio radio-primary" data-testid="visibility-internal" />
                            <span class="label-text">Intern – Nur Vereinsmitglieder (1 Stimme pro Mitglied)</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" wire:model="visibility" name="visibility" value="public" class="radio radio-primary" data-testid="visibility-public" />
                            <span class="label-text">Öffentlich – Gäste + Mitglieder (1 Stimme pro IP)</span>
                        </label>
                    </div>
                </div>

                <div>
                    <div class="label">
                        <span class="label-text font-medium">Status</span>
                    </div>
                    <x-badge class="mt-2" :value="$status" />
                </div>
            </div>

            {{-- Start/Ende Datum --}}
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div>
                    <label for="startsAt" class="fieldset-legend">Start</label>
                    <input id="startsAt" type="datetime-local" wire:model="startsAt" class="input input-bordered w-full" data-testid="starts-at-input" />
                </div>
                <div>
                    <label for="endsAt" class="fieldset-legend">Ende</label>
                    <input id="endsAt" type="datetime-local" wire:model="endsAt" class="input input-bordered w-full" data-testid="ends-at-input" />
                </div>
            </div>

            {{-- Antwortmöglichkeiten --}}
            <div data-testid="options-section">
                <div class="flex items-center justify-between gap-4 mb-4">
                    <h2 class="text-lg font-semibold">Antwortmöglichkeiten (max. 13)</h2>
                    <x-button
                        wire:click="addOption"
                        icon="o-plus"
                        class="btn-sm btn-primary"
                        :disabled="count($options) >= 13"
                        data-testid="add-option-button"
                    >
                        Antwort hinzufügen
                    </x-button>
                </div>

                <div class="space-y-3">
                    @foreach ($options as $index => $option)
                        <x-card class="bg-base-200" data-testid="answer-option-{{ $index }}">
                            <div class="grid grid-cols-1 gap-3 md:grid-cols-12 md:items-end">
                                <div class="md:col-span-5">
                                    <x-input
                                        label="Antwort"
                                        wire:model="options.{{ $index }}.label"
                                        placeholder="Antworttext"
                                        data-testid="option-{{ $index }}-label"
                                    />
                                </div>
                                <div class="md:col-span-3">
                                    <div class="flex items-end gap-1">
                                        <div class="flex-1">
                                            <x-input
                                                label="Bild-URL (optional)"
                                                wire:model="options.{{ $index }}.image_url"
                                                placeholder="https://..."
                                            />
                                        </div>
                                        <x-button
                                            icon="o-information-circle"
                                            class="btn-ghost btn-sm mb-0.5"
                                            tooltip="Empfohlen: querformatiges Bild (z. B. 1200×630)"
                                        />
                                    </div>
                                </div>
                                <div class="md:col-span-3">
                                    <x-input
                                        label="Link-URL (optional)"
                                        wire:model="options.{{ $index }}.link_url"
                                        placeholder="https://..."
                                    />
                                </div>
                                <div class="md:col-span-1">
                                    <x-button
                                        wire:click="removeOption({{ $index }})"
                                        icon="o-trash"
                                        class="btn-ghost btn-error w-full"
                                        tooltip="Antwort {{ $index + 1 }} entfernen"
                                    />
                                </div>
                            </div>
                        </x-card>
                    @endforeach
                </div>
            </div>

            {{-- Action Buttons --}}
            <div class="flex flex-wrap gap-2">
                <x-button type="submit" icon="o-check" class="btn-primary">
                    Speichern
                </x-button>
                <x-button wire:click="activate" icon="o-play">
                    Aktivieren
                </x-button>
                <x-button wire:click="archive" icon="o-archive-box" class="btn-ghost">
                    Archivieren
                </x-button>
            </div>
        </form>
    </x-card>

    {{-- Auswertung Card --}}
    <x-card title="Auswertung" subtitle="Diagramme dienen der Übersicht; die Tabelle bleibt die barrierearme Detailansicht." data-testid="evaluation-card">
        {{-- Hidden data for Chart.js --}}
        <div class="hidden" aria-hidden="true">
            <span data-omxfc-poll-color="members" class="text-indigo-600">.</span>
            <span data-omxfc-poll-color="guests" class="text-gray-600">.</span>
        </div>
        <div class="hidden" aria-hidden="true" data-omxfc-poll-chart-data='@json($this->chartData)'></div>

        @if (empty($this->chartData['options']['labels'] ?? []))
            <x-alert icon="o-information-circle" class="alert-info mt-4">
                Noch keine Umfrage ausgewählt.
            </x-alert>
        @else
            @php($totalVotes = (int) ($this->chartData['totals']['totalVotes'] ?? 0))

            @if ($totalVotes > 0)
                {{-- Charts Grid --}}
                <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
                    <x-card class="bg-base-200" wire:ignore>
                        <h3 class="font-semibold mb-3">Stimmen je Antwort</h3>
                        <canvas id="poll-options-chart" class="h-64 w-full" aria-label="Balkendiagramm: Stimmen je Antwort" role="img"></canvas>
                    </x-card>
                    <x-card class="bg-base-200" wire:ignore>
                        <h3 class="font-semibold mb-3">Zeitverlauf</h3>
                        <canvas id="poll-timeline-chart" class="h-64 w-full" aria-label="Liniendiagramm: Stimmen im Zeitverlauf" role="img"></canvas>
                    </x-card>
                    <x-card class="bg-base-200" wire:ignore>
                        <h3 class="font-semibold mb-3">Segmentierung</h3>
                        <canvas id="poll-segment-chart" class="h-64 w-full" aria-label="Diagramm: Mitglieder vs Gäste" role="img"></canvas>
                    </x-card>
                </div>
            @else
                <x-alert icon="o-chart-bar" class="alert-warning mt-6">
                    Noch keine Stimmen abgegeben. Die Diagramme werden angezeigt, sobald die erste Stimme eingegangen ist.
                </x-alert>
            @endif

            {{-- Results Table --}}
            <div class="mt-8 overflow-x-auto">
                <table id="poll-results-table" class="table">
                    <caption class="sr-only">Tabellarische Auswertung der Umfrage</caption>
                    <thead>
                        <tr>
                            <th>Antwort</th>
                            <th class="text-right">Stimmen</th>
                            <th class="text-right">%</th>
                            <th class="text-right">Mitglieder</th>
                            <th class="text-right">Gäste</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach (($this->chartData['options']['labels'] ?? []) as $i => $label)
                            @php($votes = (int) (($this->chartData['options']['total'][$i] ?? 0)))
                            @php($members = (int) (($this->chartData['options']['members'][$i] ?? 0)))
                            @php($guests = (int) (($this->chartData['options']['guests'][$i] ?? 0)))
                            @php($pct = $totalVotes > 0 ? round(($votes / $totalVotes) * 100, 1) : 0)
                            <tr>
                                <td class="font-medium">{{ $label }}</td>
                                <td class="text-right">{{ $votes }}</td>
                                <td class="text-right">{{ $pct }}%</td>
                                <td class="text-right">{{ $members }}</td>
                                <td class="text-right">{{ $guests }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="font-semibold">
                            <td>Gesamt</td>
                            <td class="text-right">{{ $totalVotes }}</td>
                            <td></td>
                            <td class="text-right">{{ (int) ($this->chartData['totals']['members'] ?? 0) }}</td>
                            <td class="text-right">{{ (int) ($this->chartData['totals']['guests'] ?? 0) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            {{-- Screen Reader Update --}}
            <div class="sr-only" aria-live="polite">
                Auswertung aktualisiert. Gesamtstimmen: {{ $totalVotes }}. Mitglieder: {{ (int) ($this->chartData['totals']['members'] ?? 0) }}. Gäste: {{ (int) ($this->chartData['totals']['guests'] ?? 0) }}.
            </div>

            <p class="mt-2 text-xs text-base-content">
                Hinweis: Die Auswertung ist nur für Admin/Vorstand sichtbar.
            </p>
        @endif
    </x-card>
</div>
