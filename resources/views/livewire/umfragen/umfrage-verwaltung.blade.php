<div class="py-12">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Umfrage verwalten</h1>
            <p class="mt-2 text-gray-600 dark:text-gray-400">Erstellen, bearbeiten und auswerten (nur Admin/Vorstand).</p>
        </div>

        @if (session()->has('success'))
            <div class="mb-6 bg-green-100 dark:bg-green-900/30 border border-green-400 dark:border-green-700 text-green-800 dark:text-green-200 px-4 py-3 rounded" role="status" aria-live="polite" aria-label="Statusmeldung zur Umfrage">
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-6 bg-red-100 dark:bg-red-900/30 border border-red-400 dark:border-red-700 text-red-800 dark:text-red-200 px-4 py-3 rounded" role="alert" aria-live="assertive">
                <p class="font-semibold">Bitte prüfe die Eingaben:</p>
                <ul class="list-disc pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 mb-8">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div class="w-full sm:max-w-md">
                    <label for="poll-select" class="block text-sm font-medium text-gray-700 dark:text-gray-200">Umfrage auswählen</label>
                    <select id="poll-select" wire:model="selectedPollId" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">(Neue Umfrage)</option>
                        @foreach ($polls as $poll)
                            <option value="{{ $poll->id }}">
                                #{{ $poll->id }} – {{ \Illuminate\Support\Str::limit($poll->question, 60) }} ({{ $poll->status }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="flex gap-2">
                    <button type="button" wire:click="newPoll" class="inline-flex items-center px-4 py-2 rounded-md bg-gray-900 text-white hover:bg-gray-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500">
                        Neue Umfrage
                    </button>
                </div>
            </div>

            <hr class="my-6 border-gray-200 dark:border-gray-700" />

            <form wire:submit.prevent="save" class="space-y-6">
                <div>
                    <label for="question" class="block text-sm font-medium text-gray-700 dark:text-gray-200">Frage</label>
                    <textarea id="question" wire:model.defer="question" rows="3" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                </div>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <div>
                        <label for="menuLabel" class="block text-sm font-medium text-gray-700 dark:text-gray-200">Link-Name im Menü</label>
                        <input id="menuLabel" type="text" wire:model.defer="menuLabel" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 focus:border-indigo-500 focus:ring-indigo-500" />
                    </div>

                    <div>
                        <span class="block text-sm font-medium text-gray-700 dark:text-gray-200">Sichtbarkeit</span>
                        <div class="mt-2 space-y-2" role="radiogroup" aria-label="Sichtbarkeit">
                            <label class="flex items-start gap-2">
                                <input type="radio" wire:model.defer="visibility" value="internal" class="mt-1" />
                                <span>
                                    <span class="font-medium">Intern</span>
                                    <span class="block text-sm text-gray-600 dark:text-gray-400">Nur Vereinsmitglieder (1 Stimme pro Mitglied).</span>
                                </span>
                            </label>
                            <label class="flex items-start gap-2">
                                <input type="radio" wire:model.defer="visibility" value="public" class="mt-1" />
                                <span>
                                    <span class="font-medium">Öffentlich</span>
                                    <span class="block text-sm text-gray-600 dark:text-gray-400">Gäste + Mitglieder (1 Stimme pro IP).</span>
                                </span>
                            </label>
                        </div>
                    </div>

                    <div>
                        <div class="text-sm font-medium text-gray-700 dark:text-gray-200">Status</div>
                        <div class="mt-2 inline-flex items-center px-3 py-1 rounded-full text-sm bg-gray-100 dark:bg-gray-900 border border-gray-200 dark:border-gray-700">
                            {{ $status }}
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <label for="startsAt" class="block text-sm font-medium text-gray-700 dark:text-gray-200">Start</label>
                        <input id="startsAt" type="datetime-local" wire:model.defer="startsAt" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 focus:border-indigo-500 focus:ring-indigo-500" />
                    </div>
                    <div>
                        <label for="endsAt" class="block text-sm font-medium text-gray-700 dark:text-gray-200">Ende</label>
                        <input id="endsAt" type="datetime-local" wire:model.defer="endsAt" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 focus:border-indigo-500 focus:ring-indigo-500" />
                    </div>
                </div>

                <div>
                    <div class="flex items-center justify-between gap-4">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Antwortmöglichkeiten (max. 13)</h2>
                        <button type="button" wire:click="addOption" class="inline-flex items-center px-3 py-2 rounded-md bg-indigo-600 text-white hover:bg-indigo-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500" @disabled(count($options) >= 13)>
                            Antwort hinzufügen
                        </button>
                    </div>

                    <div class="mt-4 space-y-3">
                        @foreach ($options as $index => $option)
                            <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                                <div class="grid grid-cols-1 gap-3 md:grid-cols-12 md:items-end">
                                    <div class="md:col-span-5">
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">Antwort</label>
                                        <input type="text" wire:model.defer="options.{{ $index }}.label" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 focus:border-indigo-500 focus:ring-indigo-500" />
                                    </div>
                                    <div class="md:col-span-3">
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">Bild-URL (optional)</label>
                                        <p class="mt-1 text-xs text-gray-600 dark:text-gray-400">Empfohlen: querformatiges Bild (z. B. 1200×630). Große Bilder können die Seite verlangsamen (Bildgröße wird nicht automatisch geprüft).</p>
                                        <input type="url" wire:model.defer="options.{{ $index }}.image_url" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 focus:border-indigo-500 focus:ring-indigo-500" />
                                    </div>
                                    <div class="md:col-span-3">
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">Link-URL (optional)</label>
                                        <input type="url" wire:model.defer="options.{{ $index }}.link_url" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 focus:border-indigo-500 focus:ring-indigo-500" />
                                    </div>
                                    <div class="md:col-span-1">
                                        <button type="button" wire:click="removeOption({{ $index }})" aria-label="Antwort {{ $index + 1 }} entfernen" class="w-full inline-flex items-center justify-center px-3 py-2 rounded-md bg-gray-100 dark:bg-gray-900 text-gray-900 dark:text-gray-100 border border-gray-200 dark:border-gray-700 hover:bg-gray-200 dark:hover:bg-gray-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500">
                                            Entfernen
                                        </button>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                    <button type="submit" class="inline-flex items-center px-4 py-2 rounded-md bg-indigo-600 text-white hover:bg-indigo-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500">
                        Speichern
                    </button>

                    <button type="button" wire:click="activate" class="inline-flex items-center px-4 py-2 rounded-md bg-gray-900 text-white hover:bg-gray-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500">
                        Aktivieren
                    </button>

                    <button type="button" wire:click="archive" class="inline-flex items-center px-4 py-2 rounded-md bg-gray-100 dark:bg-gray-900 text-gray-900 dark:text-gray-100 border border-gray-200 dark:border-gray-700 hover:bg-gray-200 dark:hover:bg-gray-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500">
                        Archivieren
                    </button>
                </div>
            </form>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6" aria-labelledby="results-heading">
            <h2 id="results-heading" class="text-2xl font-bold text-gray-900 dark:text-white">Auswertung</h2>
            <p class="mt-2 text-gray-600 dark:text-gray-400">Diagramme dienen der Übersicht; die Tabelle bleibt die barrierearme Detailansicht.</p>

            <div class="hidden" aria-hidden="true">
                <span data-omxfc-poll-color="members" class="text-indigo-600">.</span>
                <span data-omxfc-poll-color="guests" class="text-gray-600">.</span>
            </div>

            <div class="hidden" aria-hidden="true" data-omxfc-poll-chart-data='@json($chartData)'></div>

            @if (empty($chartData['options']['labels'] ?? []))
                <div class="mt-6 text-gray-600 dark:text-gray-400">Noch keine Umfrage ausgewählt.</div>
            @else
                @php($totalVotes = (int) ($chartData['totals']['totalVotes'] ?? 0))

                @if ($totalVotes > 0)
                    <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
                        <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4" wire:ignore>
                            <h3 class="font-semibold text-gray-900 dark:text-white">Stimmen je Antwort</h3>
                            <canvas id="poll-options-chart" class="mt-3 h-64 w-full" aria-label="Balkendiagramm: Stimmen je Antwort" aria-describedby="poll-results-table" role="img"></canvas>
                        </div>
                        <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4" wire:ignore>
                            <h3 class="font-semibold text-gray-900 dark:text-white">Zeitverlauf</h3>
                            <canvas id="poll-timeline-chart" class="mt-3 h-64 w-full" aria-label="Liniendiagramm: Stimmen im Zeitverlauf" aria-describedby="poll-results-table" role="img"></canvas>
                        </div>
                        <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4" wire:ignore>
                            <h3 class="font-semibold text-gray-900 dark:text-white">Segmentierung</h3>
                            <canvas id="poll-segment-chart" class="mt-3 h-64 w-full" aria-label="Diagramm: Mitglieder vs Gäste" aria-describedby="poll-results-table" role="img"></canvas>
                        </div>
                    </div>
                @else
                    <div class="mt-6 text-gray-600 dark:text-gray-400">Noch keine Stimmen abgegeben. Die Diagramme werden angezeigt, sobald die erste Stimme eingegangen ist.</div>
                @endif

                <div class="mt-8 overflow-x-auto">
                    <table id="poll-results-table" class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <caption class="sr-only">Tabellarische Auswertung der Umfrage</caption>
                        <thead class="bg-gray-50 dark:bg-gray-900">
                            <tr>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-semibold text-gray-700 dark:text-gray-200">Antwort</th>
                                <th scope="col" class="px-4 py-3 text-right text-xs font-semibold text-gray-700 dark:text-gray-200">Stimmen</th>
                                <th scope="col" class="px-4 py-3 text-right text-xs font-semibold text-gray-700 dark:text-gray-200">%</th>
                                <th scope="col" class="px-4 py-3 text-right text-xs font-semibold text-gray-700 dark:text-gray-200">Mitglieder</th>
                                <th scope="col" class="px-4 py-3 text-right text-xs font-semibold text-gray-700 dark:text-gray-200">Gäste</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach (($chartData['options']['labels'] ?? []) as $i => $label)
                                @php($votes = (int) (($chartData['options']['total'][$i] ?? 0)))
                                @php($members = (int) (($chartData['options']['members'][$i] ?? 0)))
                                @php($guests = (int) (($chartData['options']['guests'][$i] ?? 0)))
                                @php($pct = $totalVotes > 0 ? round(($votes / $totalVotes) * 100, 1) : 0)
                                <tr>
                                    <th scope="row" class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">{{ $label }}</th>
                                    <td class="px-4 py-3 text-sm text-right text-gray-900 dark:text-gray-100">{{ $votes }}</td>
                                    <td class="px-4 py-3 text-sm text-right text-gray-900 dark:text-gray-100">{{ $pct }}</td>
                                    <td class="px-4 py-3 text-sm text-right text-gray-900 dark:text-gray-100">{{ $members }}</td>
                                    <td class="px-4 py-3 text-sm text-right text-gray-900 dark:text-gray-100">{{ $guests }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-gray-50 dark:bg-gray-900">
                            <tr>
                                <th scope="row" class="px-4 py-3 text-sm font-semibold text-gray-900 dark:text-gray-100">Gesamt</th>
                                <td class="px-4 py-3 text-sm text-right font-semibold text-gray-900 dark:text-gray-100">{{ $totalVotes }}</td>
                                <td class="px-4 py-3"></td>
                                <td class="px-4 py-3 text-sm text-right font-semibold text-gray-900 dark:text-gray-100">{{ (int) ($chartData['totals']['members'] ?? 0) }}</td>
                                <td class="px-4 py-3 text-sm text-right font-semibold text-gray-900 dark:text-gray-100">{{ (int) ($chartData['totals']['guests'] ?? 0) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div class="sr-only" aria-live="polite">
                    Auswertung aktualisiert. Gesamtstimmen: {{ $totalVotes }}. Mitglieder: {{ (int) ($chartData['totals']['members'] ?? 0) }}. Gäste: {{ (int) ($chartData['totals']['guests'] ?? 0) }}.
                </div>

                <div class="mt-2 text-xs text-gray-600 dark:text-gray-400">
                    Hinweis: Die Auswertung ist nur für Admin/Vorstand sichtbar.
                </div>
            @endif
        </div>
    </div>
</div>
