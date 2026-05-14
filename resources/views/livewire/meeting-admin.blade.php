<div class="mx-auto max-w-6xl space-y-6">
    <x-ui.page-header
        eyebrow="Adminbereich"
        title="Treffen verwalten"
        description="Pflege hier Zoom-Links, Rhythmus und Sichtbarkeit aller Vereins-Meetings. Die öffentliche Seite /treffen liest diese Daten direkt aus."
    >
        <x-slot:actions>
            <div class="flex flex-wrap gap-2">
                <x-button label="Zur öffentlichen Übersicht" :link="$publicMeetingsUrl" wire:navigate icon="o-arrow-top-right-on-square" class="btn-ghost" />
                @if (!$showForm)
                    <x-button label="Neues Treffen" wire:click="openForm" icon="o-plus" class="btn-primary" />
                @endif
            </div>
        </x-slot:actions>
    </x-ui.page-header>

    @if (session()->has('success'))
        <x-alert icon="o-check-circle" class="alert-success" dismissible>
            {{ session('success') }}
        </x-alert>
    @endif

    <x-ui.panel
        :title="$editingId ? 'Treffen bearbeiten' : 'Treffen anlegen'"
        description="Der Rhythmus wird strukturiert gespeichert, damit /treffen den nächsten Termin automatisch berechnen kann."
    >
        <form wire:submit="save" class="space-y-5">
            <div class="grid gap-4 md:grid-cols-2">
                <x-input label="Titel *" wire:model="title" placeholder="z. B. AG Maddraxikon" />
                <x-input label="Technischer Schlüssel" wire:model="slug" placeholder="wird bei Bedarf aus dem Titel erzeugt" hint="Für Redirect und interne Zuordnung." />
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <x-input label="Zoom-URL" type="url" wire:model="zoom_url" placeholder="https://..." />
                <div class="pt-6">
                    <x-checkbox label="Auf /treffen anzeigen" wire:model="is_active" />
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <x-input label="Beginn" type="time" wire:model="time_from" />
                <x-input label="Ende" type="time" wire:model="time_to" />
            </div>

            <div class="space-y-2">
                <label for="rhythm_type" class="text-sm font-medium text-base-content">Rhythmus *</label>
                <select id="rhythm_type" wire:model.live="rhythm_type" class="select select-bordered w-full" data-testid="meeting-rhythm-type">
                    @foreach ($rhythmTypeOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                @error('rhythm_type')
                    <p class="text-sm text-error">{{ $message }}</p>
                @enderror
            </div>

            @if ($rhythm_type === \App\Enums\MeetingRhythmType::MonthlyNthWeekday->value)
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="space-y-2">
                        <label for="week_of_month" class="text-sm font-medium text-base-content">Woche im Monat *</label>
                        <select id="week_of_month" wire:model="week_of_month" class="select select-bordered w-full">
                            @foreach ($weekOfMonthOptions as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('week_of_month')
                            <p class="text-sm text-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="space-y-2">
                        <label for="weekday" class="text-sm font-medium text-base-content">Wochentag *</label>
                        <select id="weekday" wire:model="weekday" class="select select-bordered w-full">
                            @foreach ($weekdayOptions as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('weekday')
                            <p class="text-sm text-error">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            @endif

            @if ($rhythm_type === \App\Enums\MeetingRhythmType::MonthlyDayOfMonth->value)
                <div class="grid gap-4 md:grid-cols-2">
                    <x-input label="Monatstag *" type="number" min="1" max="31" wire:model="day_of_month" />
                </div>
            @endif

            @if ($rhythm_type === \App\Enums\MeetingRhythmType::EveryNWeeks->value)
                <div class="grid gap-4 md:grid-cols-2">
                    <x-input label="Startdatum *" type="date" wire:model="starts_on" />
                    <x-input label="Alle X Wochen *" type="number" min="1" max="52" wire:model="interval_weeks" />
                </div>
            @endif

            <x-textarea
                wire:model="rhythm_note"
                label="Ergänzender Hinweis"
                placeholder="z. B. zusätzlicher Hinweis für Mitglieder oder Besonderheiten zum Termin"
                hint="Bei Hinweisrhythmus ist dieses Feld verpflichtend."
            />

            <div class="flex justify-end gap-3 border-t border-base-200 pt-4">
                @if ($showForm)
                    <x-button label="Abbrechen" wire:click="closeForm" class="btn-ghost" />
                @endif
                <x-button :label="$editingId ? 'Änderungen speichern' : 'Treffen anlegen'" type="submit" icon="o-check" class="btn-primary" spinner="save" />
            </div>
        </form>
    </x-ui.panel>

    <x-ui.panel
        :title="'Treffenliste ('.$meetings->count().')'"
        description="Aktive Einträge erscheinen auf /treffen. Die Reihenfolge wird direkt hier gesteuert."
        data-testid="meeting-admin-list"
    >
        @if ($meetings->isEmpty())
            <div class="py-12 text-center">
                <x-icon name="o-calendar-days" class="mx-auto mb-4 h-12 w-12 opacity-30" />
                <p class="text-lg font-medium">Noch keine Treffen angelegt</p>
                <p class="mt-1 opacity-60">Lege das erste Format an, um es auf /treffen sichtbar zu machen.</p>
            </div>
        @else
            <div class="divide-y divide-base-200">
                @foreach ($meetings as $meeting)
                    <div class="-mx-4 flex flex-col gap-4 px-4 py-4 transition-colors hover:bg-base-200/50 lg:flex-row lg:items-start lg:justify-between">
                        <div class="min-w-0 flex-1 space-y-2">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="w-10 text-sm font-mono opacity-60">#{{ $meeting->sort_order }}</span>
                                <p class="font-medium text-base-content">{{ $meeting->title }}</p>
                                <x-badge :value="$meeting->is_active ? 'Aktiv' : 'Inaktiv'" class="{{ $meeting->is_active ? 'badge-success' : 'badge-ghost' }} badge-sm" />
                            </div>

                            <div class="grid gap-2 text-sm leading-relaxed text-base-content/70 md:grid-cols-2">
                                <p><strong>Rhythmus:</strong> {{ $meeting->display_rhythm }}</p>
                                <p><strong>Technischer Schlüssel:</strong> {{ $meeting->slug }}</p>
                                @if ($meeting->next_occurrence)
                                    <p><strong>Nächster Termin:</strong> {{ $meeting->next_occurrence->locale('de')->translatedFormat('l, d.m.Y') }}</p>
                                @else
                                    <p><strong>Nächster Termin:</strong> Wird nur als Hinweis dargestellt</p>
                                @endif
                                <p><strong>Zoom:</strong> {{ $meeting->zoom_url ? 'konfiguriert' : 'fehlt' }}</p>
                            </div>
                        </div>

                        <div class="flex shrink-0 flex-wrap items-center gap-1">
                            <x-button
                                wire:click="toggleActive({{ $meeting->id }})"
                                :label="$meeting->is_active ? 'Aktiv' : 'Inaktiv'"
                                class="{{ $meeting->is_active ? 'btn-success' : 'btn-ghost' }} btn-xs"
                                spinner="toggleActive"
                            />
                            <x-button wire:click="moveUp({{ $meeting->id }})" icon="o-chevron-up" class="btn-ghost btn-xs" spinner="moveUp" />
                            <x-button wire:click="moveDown({{ $meeting->id }})" icon="o-chevron-down" class="btn-ghost btn-xs" spinner="moveDown" />
                            <x-button wire:click="edit({{ $meeting->id }})" icon="o-pencil" class="btn-ghost btn-xs text-info" />
                            <x-button
                                wire:click="delete({{ $meeting->id }})"
                                wire:confirm="Möchtest du das Treffen &quot;{{ e($meeting->title) }}&quot; wirklich löschen?"
                                icon="o-trash"
                                class="btn-ghost btn-xs text-error"
                                spinner="delete"
                            />
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </x-ui.panel>
</div>