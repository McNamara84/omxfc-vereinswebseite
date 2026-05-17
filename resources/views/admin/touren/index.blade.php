<x-app-layout title="Touren verwalten" description="Weise Einführungstouren neu zu und behalte den Status je Mitglied zentral im Blick.">
    <div class="mx-auto flex max-w-7xl flex-col gap-6 px-4 py-8 sm:px-6 lg:px-8" data-testid="tour-admin-page">
        <x-ui.page-header
            eyebrow="Vorstand & Admin"
            title="Touren verwalten"
            description="Einführungstouren für Mitglieder neu zuweisen, Fortschritt prüfen und bei Änderungen gezielt erneut anstoßen."
        />

        @if (session('success'))
            <x-alert icon="o-check-circle" class="alert-success" dismissible>
                {{ session('success') }}
            </x-alert>
        @endif

        <x-ui.panel title="Mitglieder filtern" description="Suche nach Name oder E-Mail, um Tour-Zuweisungen gezielt zu verwalten.">
            <form method="GET" action="{{ route('admin.touren.index') }}" class="flex flex-col gap-3 lg:flex-row lg:items-end">
                <label class="form-control w-full lg:max-w-md">
                    <span class="label-text text-sm font-semibold text-base-content">Mitglied suchen</span>
                    <input
                        type="search"
                        name="suche"
                        value="{{ $filters['suche'] }}"
                        placeholder="Name oder E-Mail"
                        class="input input-bordered w-full"
                    >
                </label>

                <div class="flex flex-wrap gap-2">
                    <button type="submit" class="btn btn-primary">Filtern</button>
                    @if ($filters['suche'] !== '')
                        <a href="{{ route('admin.touren.index') }}" class="btn btn-ghost">Zurücksetzen</a>
                    @endif
                </div>
            </form>
        </x-ui.panel>

        @if ($members->isEmpty())
            <div class="rounded-3xl border border-dashed border-base-300 bg-base-100/70 px-6 py-10 text-sm text-base-content/70">
                Für die aktuelle Suche wurden keine Mitglieder gefunden.
            </div>
        @else
            <div class="grid gap-5 xl:grid-cols-2">
                @foreach ($members as $member)
                    <x-ui.panel :title="$member->name" :description="$member->email">
                        <div class="space-y-4">
                            <div class="flex flex-wrap gap-2 text-sm">
                                <x-badge :value="$member->members_team_role" class="badge-outline" />
                                @if ($member->mitglied_seit)
                                    <x-badge :value="'Mitglied seit '.$member->mitglied_seit->locale('de')->isoFormat('MMM YYYY')" class="badge-ghost" />
                                @endif
                            </div>

                            <div class="space-y-3">
                                @foreach ($tourDefinitions as $tourDefinition)
                                    @php
                                        $assignment = $member->tourAssignments->first(
                                            fn ($tourAssignment) => $tourAssignment->tour_key === $tourDefinition->key
                                                && $tourAssignment->tour_version === $tourDefinition->version
                                        );
                                        $statusValue = $assignment?->status?->value;
                                        $statusLabel = match ($statusValue) {
                                            'completed' => 'Abgeschlossen',
                                            'in_progress' => 'In Bearbeitung',
                                            'pending' => 'Offen',
                                            default => 'Nicht zugewiesen',
                                        };
                                        $statusClass = match ($statusValue) {
                                            'completed' => 'badge-success',
                                            'in_progress' => 'badge-warning',
                                            'pending' => 'badge-primary',
                                            default => 'badge-outline',
                                        };
                                    @endphp

                                    <article class="rounded-2xl border border-base-300 bg-base-100/80 p-4">
                                        <div class="flex flex-wrap items-start justify-between gap-3">
                                            <div class="space-y-1">
                                                <h3 class="text-base font-semibold text-base-content">{{ $tourDefinition->title }}</h3>
                                                <p class="text-sm leading-relaxed text-base-content/70">{{ $tourDefinition->description }}</p>
                                            </div>

                                            <x-badge :value="$statusLabel" class="{{ $statusClass }}" />
                                        </div>

                                        <dl class="mt-4 grid gap-3 text-sm text-base-content/70 sm:grid-cols-2">
                                            <div>
                                                <dt class="font-semibold text-base-content">Version</dt>
                                                <dd>{{ $tourDefinition->version }}</dd>
                                            </div>
                                            <div>
                                                <dt class="font-semibold text-base-content">Schritte</dt>
                                                <dd>{{ count($tourDefinition->steps) }}</dd>
                                            </div>
                                            <div>
                                                <dt class="font-semibold text-base-content">Zugewiesen</dt>
                                                <dd>{{ $assignment?->assigned_at?->locale('de')->isoFormat('D. MMM YYYY, HH:mm') ?? 'Noch nicht' }}</dd>
                                            </div>
                                            <div>
                                                <dt class="font-semibold text-base-content">Von</dt>
                                                <dd>{{ $assignment?->assignedBy?->name ?? ($assignment ? 'System' : 'Noch niemand') }}</dd>
                                            </div>
                                        </dl>

                                        <div class="mt-4 flex flex-wrap gap-2">
                                            <form method="POST" action="{{ route('admin.touren.assign') }}">
                                                @csrf
                                                <input type="hidden" name="user_id" value="{{ $member->id }}">
                                                <input type="hidden" name="tour_key" value="{{ $tourDefinition->key }}">
                                                @if ($filters['suche'] !== '')
                                                    <input type="hidden" name="suche" value="{{ $filters['suche'] }}">
                                                @endif
                                                @if ($members->currentPage() > 1)
                                                    <input type="hidden" name="page" value="{{ $members->currentPage() }}">
                                                @endif

                                                <button type="submit" class="btn btn-primary btn-sm">
                                                    {{ $assignment ? 'Tour neu zuweisen' : 'Tour zuweisen' }}
                                                </button>
                                            </form>
                                        </div>
                                    </article>
                                @endforeach
                            </div>
                        </div>
                    </x-ui.panel>
                @endforeach
            </div>

            <div>
                {{ $members->links() }}
            </div>
        @endif
    </div>
</x-app-layout>