<x-app-layout title="Arbeitsgruppen – Offizieller MADDRAX Fanclub e. V." description="Tabellarische Übersicht aller Arbeitsgruppen.">
    <x-member-page>
        @php
            $isLeaderIndex = request()->routeIs('ag.index');
            $headerDescription = $isLeaderIndex
                ? 'Verwalte deine eigenen Arbeitsgruppen und halte öffentliche Angaben wie Beschreibung, Kontaktadresse und Regeltermine aktuell.'
                : 'Zentrale Übersicht aller Arbeitsgruppen mit Leitungen, Kontaktadressen und direkten Verwaltungsaktionen.';
        @endphp

        <div class="space-y-6">
            <x-ui.page-header
                eyebrow="Mitgliederbereich"
                title="Arbeitsgruppen"
                description="{{ $headerDescription }}"
            >
                <x-slot:actions>
                    <div class="flex flex-col gap-3 sm:flex-row">
                        <x-button label="Öffentliche Übersicht" link="{{ route('arbeitsgruppen') }}" wire:navigate icon="o-globe-europe-africa" class="btn-outline" />
                        @if(Auth::user()->hasRole(\App\Enums\Role::Admin))
                            <x-button label="AG erstellen" link="{{ route('arbeitsgruppen.create') }}" wire:navigate icon="o-plus" class="btn-primary" />
                        @endif
                    </div>
                </x-slot:actions>
            </x-ui.page-header>

            <x-ui.panel>
                @if($isLeaderIndex && Auth::user()->ownedTeams()->where('personal_team', false)->exists())
                    <div class="mb-6 rounded-3xl bg-base-200/70 p-4 text-sm leading-relaxed text-base-content/78">
                        Als AG-Leiter kannst du hier deine AG verwalten. Die Beschreibung, das Logo, der Termin für das regelmäßige AG-Treffen und die angegebene E-Mail-Adresse werden auch im öffentlichen Bereich für Nicht-Mitglieder angezeigt. Bitte halte diese Informationen daher stets aktuell.
                    </div>
                @endif

                <div class="overflow-x-auto">
                <table class="table table-zebra">
                    <thead class="text-base-content">
                        <tr>
                            <th>Name</th>
                            <th>Leitung</th>
                            <th>E-Mail</th>
                            <th>Termin</th>
                            <th>Aktionen</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($ags as $ag)
                        <tr class="hover:bg-base-200">
                            <td>{{ $ag->name }}</td>
                            <td>{{ $ag->owner?->name ?? '-' }}</td>
                            <td>{{ $ag->email ?? '-' }}</td>
                            <td>{{ $ag->meeting_schedule ?? '-' }}</td>
                            <td>
                                <x-button label="Bearbeiten" link="{{ route('arbeitsgruppen.edit', $ag) }}" wire:navigate icon="o-pencil" class="btn-ghost btn-sm text-primary" />
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-8 text-center text-base-content/50">
                                <x-icon name="o-user-group" class="mx-auto h-12 w-12 opacity-30" />
                                <p class="mt-2">Keine Arbeitsgruppen vorhanden.</p>
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
                </div>
            </x-ui.panel>
        </div>
    </x-member-page>
</x-app-layout>
