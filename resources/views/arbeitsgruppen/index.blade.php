<x-app-layout title="Arbeitsgruppen – Offizieller MADDRAX Fanclub e. V." description="Tabellarische Übersicht aller Arbeitsgruppen.">
    <x-member-page>
        <x-card shadow>
            <div class="flex justify-between items-center {{ request()->routeIs('ag.index') ? 'mb-4' : 'mb-6' }}">
                <x-header title="Arbeitsgruppen" class="!mb-0" />
                @if(Auth::user()->hasRole(\App\Enums\Role::Admin))
                    <x-button label="AG erstellen" link="{{ route('arbeitsgruppen.create') }}" icon="o-plus" class="btn-primary" />
                @endif
            </div>
            @if(request()->routeIs('ag.index') && Auth::user()->ownedTeams()->where('personal_team', false)->exists())
                <p class="mb-6 text-base-content">Als AG-Leiter kannst du hier deine AG verwalten. Die Beschreibung, das Logo, der Termin für das regelmäßige AG-Treffen und die angegebene E-Mail-Adresse werden auch im öffentlichen Bereich für Nicht-Mitglieder angezeigt. Bitte halte diese Informationen daher stets aktuell.</p>
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
                            <x-button label="Bearbeiten" link="{{ route('arbeitsgruppen.edit', $ag) }}" icon="o-pencil" class="btn-ghost btn-sm text-primary" />
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center py-8 text-base-content/50">
                            <x-icon name="o-user-group" class="w-12 h-12 opacity-30 mx-auto" />
                            <p class="mt-2">Keine Arbeitsgruppen vorhanden.</p>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
            </div>
        </x-card>
    </x-member-page>
</x-app-layout>
