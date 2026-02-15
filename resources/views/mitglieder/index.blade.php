<x-app-layout>
    <x-member-page>
    @if(session('status'))
    <x-alert class="alert-success mb-4">
        {{ session('status') }}
    </x-alert>
    @endif

    @if(session('error'))
    <x-alert class="alert-error mb-4">
        {{ session('error') }}
    </x-alert>
    @endif

    <x-card shadow>
        <x-header title="Mitgliederliste" data-members-heading class="!mb-0" />

    @php
        $sortLabels = [
            'nachname' => 'Nachname',
            'mitglied_seit' => 'Mitglied seit',
            'role' => 'Rolle',
            'last_activity' => 'Zuletzt online',
            'mitgliedsbeitrag' => 'Beitrag',
        ];
        $sortLabel = $sortLabels[$sortBy] ?? $sortLabels['nachname'];
        $directionLabel = $sortDir === 'desc' ? 'absteigender' : 'aufsteigender';
        $filterOnlineActive = in_array('online', $filters ?? []);
        $filterSummary = $filterOnlineActive
            ? 'Es werden nur Mitglieder angezeigt, die aktuell online sind.'
            : 'Es werden alle aktiven Mitglieder angezeigt.';
        $memberCount = $members->count();
        $memberCountSummary = $memberCount === 1
            ? '1 Mitglied'
            : $memberCount . ' Mitglieder';
        $fallbackSummary = sprintf(
            'Mitgliederliste, sortiert nach %s in %s Reihenfolge. %s Insgesamt sind %s sichtbar.',
            $sortLabel,
            $directionLabel,
            $filterSummary,
            $memberCountSummary
        );

        // Sort-Link-Helper
        $sortLink = fn(string $col, string $defaultDir = 'asc') => route('mitglieder.index', array_merge(
            request()->query(),
            ['sort' => $col, 'dir' => ($sortBy === $col && $sortDir === $defaultDir) ? ($defaultDir === 'asc' ? 'desc' : 'asc') : $defaultDir]
        ));


    @endphp
    <p id="members-table-summary" data-members-summary class="sr-only" aria-live="polite">{{ $fallbackSummary }}</p>

    <!-- Filter -->
    <form method="GET" action="{{ route('mitglieder.index') }}" class="mb-6" x-data>
        <input type="hidden" name="sort" value="{{ $sortBy }}">
        <input type="hidden" name="dir" value="{{ $sortDir }}">
        <div class="flex flex-wrap gap-4 items-center">
            <x-checkbox
                label="Nur online"
                name="filters[]"
                value="online"
                :checked="in_array('online', $filters ?? [])"
                @change="$root.submit()"
                data-testid="mitglieder-filter-online"
            />
        </div>
    </form>

    <!-- Export-Funktionen für berechtigte Benutzer -->
    @if($canViewDetails)
    <div class="mb-6">
        <div x-data="{ showExportOptions: false, showEmailCopy: false, emailsCopied: false }" class="bg-base-200 rounded-lg p-4">
            <div class="flex flex-wrap gap-4 items-center justify-between">
                <h3 class="text-lg font-medium text-base-content">Datenexport & Funktionen</h3>

                <div class="flex space-x-2">
                    <!-- CSV Export Button -->
                    <x-button icon="o-arrow-down-tray" label="CSV Export" @click="showExportOptions = !showExportOptions" class="btn-primary" data-testid="mitglieder-csv-export-btn" />

                    <!-- E-Mail-Adressen kopieren -->
                    <x-button
                        icon="o-clipboard-document"
                        label="E-Mail-Adressen kopieren"
                        class="btn-info"
                        @click="
                            showEmailCopy = true;
                            fetch('{{ route('mitglieder.all-emails') }}')
                                .then(response => response.json())
                                .then(data => {
                                    if (data.emails) {
                                        navigator.clipboard.writeText(data.emails)
                                            .then(() => {
                                                emailsCopied = true;
                                                setTimeout(() => emailsCopied = false, 3000);
                                            })
                                            .catch(err => console.error('Fehler beim Kopieren: ', err));
                                    }
                                })
                                .catch(error => console.error('Fehler beim Abrufen der E-Mails: ', error));
                        "
                    />
                </div>
            </div>

            <!-- CSV Export Optionen -->
            <div x-show="showExportOptions" class="mt-4">
                <form action="{{ route('mitglieder.export-csv') }}" method="POST" class="bg-base-100 p-4 rounded-md shadow">
                    @csrf
                    <div class="mb-3">
                        <h4 class="font-medium text-base-content mb-2">Zu exportierende Daten auswählen:</h4>
                        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
                            <x-checkbox label="Name (Vor-/Nachname)" name="export_fields[]" value="name" checked />
                            <x-checkbox label="E-Mail-Adresse" name="export_fields[]" value="email" checked />
                            <x-checkbox label="Postadresse" name="export_fields[]" value="adresse" />
                            <x-checkbox label="Bezahlt bis" name="export_fields[]" value="bezahlt_bis" />
                        </div>
                    </div>
                    <div class="flex justify-end">
                        <x-button type="submit" icon="o-arrow-down-tray" label="CSV herunterladen" class="btn-primary" />
                    </div>
                </form>
            </div>

            <!-- Erfolgsmeldung für E-Mail-Kopieren -->
            <div x-show="emailsCopied" x-transition class="mt-3 text-sm text-success">
                <x-icon name="o-check-circle" class="h-5 w-5 inline-block mr-1" />
                E-Mail-Adressen wurden in die Zwischenablage kopiert!
            </div>
        </div>
    </div>
    @endif

    <!-- Desktop-Ansicht (versteckt auf Mobilgeräten) -->
    <div class="hidden md:block">
    @php
        $nachnameSortState = $sortBy === 'nachname' ? ($sortDir === 'desc' ? 'descending' : 'ascending') : 'none';
        $mitgliedSeitSortState = $sortBy === 'mitglied_seit' ? ($sortDir === 'desc' ? 'descending' : 'ascending') : 'none';
        $roleSortState = $sortBy === 'role' ? ($sortDir === 'desc' ? 'descending' : 'ascending') : 'none';
        $lastActivitySortState = $sortBy === 'last_activity' ? ($sortDir === 'desc' ? 'descending' : 'ascending') : 'none';
        $beitragSortState = $sortBy === 'mitgliedsbeitrag' ? ($sortDir === 'desc' ? 'descending' : 'ascending') : 'none';
    @endphp

    <div class="overflow-x-auto">
    <table class="table table-zebra"
        data-members-table
        data-members-sort="{{ $sortBy }}"
        data-members-dir="{{ $sortDir }}"
        data-members-filter-online="{{ $filterOnlineActive ? 'true' : 'false' }}"
        data-members-total="{{ $memberCount }}"
        data-members-summary-id="members-table-summary"
        aria-describedby="members-table-summary">
    <thead class="text-base-content">
    <tr>
        <th data-members-sort-column="nachname" aria-sort="{{ $nachnameSortState }}">
            <a href="{{ $sortLink('nachname') }}"
               class="flex items-center group text-base-content hover:text-primary">
                Name
                @if($sortBy === 'nachname')
                    <x-icon name="{{ $sortDir === 'asc' ? 'o-chevron-up' : 'o-chevron-down' }}" class="h-4 w-4 ml-1" />
                @endif
            </a>
        </th>

        <th data-members-sort-column="mitglied_seit" aria-sort="{{ $mitgliedSeitSortState }}">
            <a href="{{ $sortLink('mitglied_seit') }}"
               class="flex items-center group text-base-content hover:text-primary">
                Mitglied seit
                @if($sortBy === 'mitglied_seit')
                    <x-icon name="{{ $sortDir === 'asc' ? 'o-chevron-up' : 'o-chevron-down' }}" class="h-4 w-4 ml-1" />
                @endif
            </a>
        </th>

        <th data-members-sort-column="role" aria-sort="{{ $roleSortState }}">
            <a href="{{ $sortLink('role') }}"
               class="flex items-center group text-base-content hover:text-primary">
                Rolle
                @if($sortBy === 'role')
                    <x-icon name="{{ $sortDir === 'asc' ? 'o-chevron-up' : 'o-chevron-down' }}" class="h-4 w-4 ml-1" />
                @endif
            </a>
        </th>

        @if($canViewDetails)
        <th data-members-sort-column="last_activity" aria-sort="{{ $lastActivitySortState }}">
            <a href="{{ $sortLink('last_activity', 'desc') }}"
               class="flex items-center group text-base-content hover:text-primary">
                Zuletzt online
                @if($sortBy === 'last_activity')
                    <x-icon name="{{ $sortDir === 'asc' ? 'o-chevron-up' : 'o-chevron-down' }}" class="h-4 w-4 ml-1" />
                @endif
            </a>
        </th>

        <th data-members-sort-column="mitgliedsbeitrag" aria-sort="{{ $beitragSortState }}">
            <a href="{{ $sortLink('mitgliedsbeitrag') }}"
               class="flex items-center group text-base-content hover:text-primary">
                Beitrag
                @if($sortBy === 'mitgliedsbeitrag')
                    <x-icon name="{{ $sortDir === 'asc' ? 'o-chevron-up' : 'o-chevron-down' }}" class="h-4 w-4 ml-1" />
                @endif
            </a>
        </th>

        <th class="hidden lg:table-cell">Details</th>
        @endif

        <th class="text-center">Aktionen</th>
    </tr>
    </thead>
    <tbody>
    @forelse($members as $member)
    <tr class="hover:bg-base-200">
        {{-- Name --}}
        <td>
            <a href="{{ route('profile.view', $member->id) }}" class="flex items-center">
                <x-avatar :image="$member->profile_photo_url" class="!w-10 !h-10" />
                <div class="ml-4">
                    <div class="font-medium text-base-content flex items-center">
                        <span class="inline-block w-2 h-2 rounded-full mr-2 {{ in_array($member->id, $onlineUserIds) ? 'bg-success' : 'bg-base-content/40' }}" title="{{ in_array($member->id, $onlineUserIds) ? 'Online' : 'Offline' }}"></span>
                        {{ $member->name }}
                    </div>
                    @if($canViewDetails)
                    <div class="text-sm text-base-content">{{ $member->vorname }} {{ $member->nachname }}</div>
                    @endif
                </div>
            </a>
        </td>

        {{-- Mitglied seit --}}
        <td class="text-sm text-base-content">
            {{ $member->mitglied_seit ? $member->mitglied_seit->format('d.m.Y') : '-' }}
        </td>

        {{-- Rolle --}}
        <td class="text-sm text-base-content">
            {{ $member->membership->role }}
        </td>

        @if($canViewDetails)
        {{-- Zuletzt online --}}
        <td class="text-sm text-base-content">
            {{ $member->last_activity ? \Carbon\Carbon::createFromTimestamp($member->last_activity, config('app.timezone'))->format('d.m.Y H:i') : '-' }}
        </td>

        {{-- Beitrag --}}
        <td class="text-sm text-base-content">
            {{ $member->mitgliedsbeitrag }}
        </td>

        {{-- Details --}}
        <td class="text-sm text-base-content hidden lg:table-cell">
            <div x-data="{ showDetails: false }" class="relative">
                <x-button
                    icon="o-information-circle"
                    label="Info"
                    @click="showDetails = !showDetails"
                    class="btn-ghost btn-xs text-info"
                />

                <div x-show="showDetails" @click.away="showDetails = false"
                     class="absolute left-0 mt-2 w-64 bg-base-100 rounded-md shadow-lg z-10 p-4">
                    <h4 class="font-semibold text-base-content mb-2">Kontaktdaten</h4>
                    <div class="mb-3">
                        <div class="text-sm">{{ $member->email }}</div>
                        <div class="text-sm">{{ $member->telefon }}</div>
                    </div>

                    <h4 class="font-semibold text-base-content mb-2">Adresse</h4>
                    <div class="mb-3">
                        <div class="text-sm">{{ $member->strasse }} {{ $member->hausnummer }}</div>
                        <div class="text-sm">{{ $member->plz }} {{ $member->stadt }}</div>
                        <div class="text-sm">{{ $member->land }}</div>
                    </div>
                </div>
            </div>
        </td>
        @endif

        {{-- Aktionen --}}
        <td class="text-center">
            <div class="flex justify-center items-center space-x-1">
                <x-button
                    icon="o-eye"
                    link="{{ route('profile.view', $member->id) }}"
                    class="btn-info btn-xs"
                    title="Profil ansehen"
                >
                    <span class="hidden xl:inline">Profil</span>
                </x-button>

                @if($canViewDetails)
                    <x-copy-email-button :email="$member->email" variant="desktop" />
                @endif

                @if($canViewDetails && $currentUser->id !== $member->id)
                    @php
                        $memberRole = $member->membership->role;
                        $memberRank = $roleRanks[$memberRole] ?? 0;
                    @endphp

                    @if($currentUserRank > $memberRank)
                        {{-- Rolle ändern (Dropdown) --}}
                        <x-dropdown>
                            <x-slot:trigger>
                                <x-button
                                    icon="o-pencil-square"
                                    class="btn-warning btn-xs"
                                    title="Rolle ändern"
                                >
                                    <span class="hidden xl:inline">Rolle</span>
                                </x-button>
                            </x-slot:trigger>
                            @foreach($roleRanks as $role => $rank)
                                @if($rank <= $currentUserRank && $role !== $memberRole)
                                    <x-menu-item>
                                        <form action="{{ route('mitglieder.change-role', $member->id) }}" method="POST">
                                            @csrf
                                            @method('PUT')
                                            <input type="hidden" name="role" value="{{ $role }}">
                                            <button type="submit" class="w-full text-left">
                                                Zu {{ $role }} ändern
                                            </button>
                                        </form>
                                    </x-menu-item>
                                @endif
                            @endforeach
                        </x-dropdown>

                        {{-- Mitgliedschaft beenden --}}
                        <form action="{{ route('mitglieder.remove', $member->id) }}" method="POST"
                              onsubmit="return confirm('Willst du die Mitgliedschaft von {{ $member->name }} wirklich beenden? Dies löscht den Benutzer aus der Datenbank!');">
                            @csrf
                            @method('DELETE')
                            <x-button
                                type="submit"
                                icon="o-trash"
                                class="btn-error btn-xs"
                                title="Mitgliedschaft beenden"
                            >
                                <span class="hidden xl:inline">Löschen</span>
                            </x-button>
                        </form>
                    @endif
                @endif
            </div>
        </td>
    </tr>
    @empty
    <tr>
        <td colspan="{{ $canViewDetails ? 7 : 4 }}" class="text-center py-8 text-base-content/50">
            <x-icon name="o-users" class="w-12 h-12 opacity-30 mx-auto" />
            <p class="mt-2">Keine Mitglieder gefunden.</p>
        </td>
    </tr>
    @endforelse
    </tbody>
    </table>
    </div>
    </div>

    <!-- Mobile-Ansicht (nur auf Mobilgeräten sichtbar) -->
    <div class="md:hidden space-y-6">
        <!-- Sortieroptionen für Mobile -->
        <div class="mb-4 bg-base-200 rounded-lg p-3">
            <h3 class="text-sm font-medium text-base-content mb-2">Sortieren nach:</h3>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('mitglieder.index', array_merge(request()->query(), ['sort' => 'nachname', 'dir' => ($sortBy === 'nachname' && $sortDir === 'asc') ? 'desc' : 'asc'])) }}"
                   class="px-3 py-1 text-xs rounded-full {{ $sortBy === 'nachname' ? 'bg-primary text-white' : 'bg-base-200 text-base-content' }}">
                    Name {{ $sortBy === 'nachname' ? ($sortDir === 'asc' ? '↑' : '↓') : '' }}
                </a>
                <a href="{{ route('mitglieder.index', array_merge(request()->query(), ['sort' => 'mitglied_seit', 'dir' => ($sortBy === 'mitglied_seit' && $sortDir === 'asc') ? 'desc' : 'asc'])) }}"
                   class="px-3 py-1 text-xs rounded-full {{ $sortBy === 'mitglied_seit' ? 'bg-primary text-white' : 'bg-base-200 text-base-content' }}">
                    Mitglied seit {{ $sortBy === 'mitglied_seit' ? ($sortDir === 'asc' ? '↑' : '↓') : '' }}
                </a>
                <a href="{{ route('mitglieder.index', array_merge(request()->query(), ['sort' => 'role', 'dir' => ($sortBy === 'role' && $sortDir === 'asc') ? 'desc' : 'asc'])) }}"
                   class="px-3 py-1 text-xs rounded-full {{ $sortBy === 'role' ? 'bg-primary text-white' : 'bg-base-200 text-base-content' }}">
                    Rolle {{ $sortBy === 'role' ? ($sortDir === 'asc' ? '↑' : '↓') : '' }}
                </a>
                @if($canViewDetails)
                <a href="{{ route('mitglieder.index', array_merge(request()->query(), ['sort' => 'last_activity', 'dir' => ($sortBy === 'last_activity' && $sortDir === 'desc') ? 'asc' : 'desc'])) }}"
                   class="px-3 py-1 text-xs rounded-full {{ $sortBy === 'last_activity' ? 'bg-primary text-white' : 'bg-base-200 text-base-content' }}">
                    Zuletzt online {{ $sortBy === 'last_activity' ? ($sortDir === 'asc' ? '↑' : '↓') : '' }}
                </a>
                <a href="{{ route('mitglieder.index', array_merge(request()->query(), ['sort' => 'mitgliedsbeitrag', 'dir' => ($sortBy === 'mitgliedsbeitrag' && $sortDir === 'asc') ? 'desc' : 'asc'])) }}"
                   class="px-3 py-1 text-xs rounded-full {{ $sortBy === 'mitgliedsbeitrag' ? 'bg-primary text-white' : 'bg-base-200 text-base-content' }}">
                    Beitrag {{ $sortBy === 'mitgliedsbeitrag' ? ($sortDir === 'asc' ? '↑' : '↓') : '' }}
                </a>
                @endif
            </div>
        </div>

        @foreach($members as $member)
        <x-card shadow class="!p-4">
            <a href="{{ route('profile.view', $member->id) }}" class="flex items-center mb-4">
                <x-avatar :image="$member->profile_photo_url" class="!w-12 !h-12" />
                <div class="ml-4">
                    <div class="font-medium text-base-content flex items-center">
                        <span class="inline-block w-2 h-2 rounded-full mr-2 {{ in_array($member->id, $onlineUserIds) ? 'bg-success' : 'bg-base-content/40' }}" title="{{ in_array($member->id, $onlineUserIds) ? 'Online' : 'Offline' }}"></span>
                        {{ $member->name }}
                    </div>
                    <div class="text-xs text-base-content">
                        {{ $member->membership->role }} •
                        Mitglied seit {{ $member->mitglied_seit ? $member->mitglied_seit->format('d.m.Y') : 'k.A.' }}
                    </div>
                </div>
            </a>

            <div class="mb-4">
                <div class="grid grid-cols-2 gap-4">
                    @if($canViewDetails)
                    <div>
                        <h4 class="text-xs uppercase tracking-wide text-base-content font-semibold mb-1">Zuletzt online</h4>
                        <div class="text-sm text-base-content">
                            {{ $member->last_activity ? \Carbon\Carbon::createFromTimestamp($member->last_activity, config('app.timezone'))->format('d.m.Y H:i') : '-' }}
                        </div>
                    </div>

                    <div>
                        <h4 class="text-xs uppercase tracking-wide text-base-content font-semibold mb-1">Beitrag</h4>
                        <div class="text-sm text-base-content">{{ $member->mitgliedsbeitrag }}</div>
                    </div>
                    @endif
                </div>
            </div>

            @if($canViewDetails)
            <div x-data="{ open: false }" class="border-t border-base-200 pt-2">
                <button @click="open = !open" type="button" class="flex items-center justify-between w-full text-sm font-medium text-base-content hover:text-primary">
                    <span>Weitere Details anzeigen</span>
                    <x-icon name="o-chevron-down" class="h-4 w-4 transition-transform" ::class="open && 'rotate-180'" />
                </button>
                <div x-show="open" x-transition class="mt-3 space-y-3">
                    <div>
                        <h4 class="text-xs uppercase tracking-wide text-base-content font-semibold mb-1">Kontaktdaten</h4>
                        <div class="text-sm text-base-content">{{ $member->email }}</div>
                        <div class="text-sm text-base-content">{{ $member->telefon }}</div>
                    </div>

                    <div>
                        <h4 class="text-xs uppercase tracking-wide text-base-content font-semibold mb-1">Adresse</h4>
                        <div class="text-sm text-base-content">{{ $member->strasse }} {{ $member->hausnummer }}</div>
                        <div class="text-sm text-base-content">{{ $member->plz }} {{ $member->stadt }}</div>
                        <div class="text-sm text-base-content">{{ $member->land }}</div>
                    </div>
                </div>
            </div>
            @endif

            <div class="flex flex-row gap-2 mt-4">
                <x-button
                    icon="o-eye"
                    label="Profil"
                    link="{{ route('profile.view', $member->id) }}"
                    class="btn-info btn-sm flex-1"
                />

                @if($canViewDetails)
                    <x-copy-email-button :email="$member->email" variant="mobile" />
                @endif

                @if($canViewDetails && $currentUser->id !== $member->id)
                    @php
                        $memberRole = $member->membership->role;
                        $memberRank = $roleRanks[$memberRole] ?? 0;
                    @endphp

                    @if($currentUserRank > $memberRank)
                        {{-- Rolle ändern (Mobile) --}}
                        <x-dropdown class="flex-1">
                            <x-slot:trigger>
                                <x-button icon="o-pencil-square" label="Rolle" class="btn-warning btn-sm w-full" />
                            </x-slot:trigger>
                            @foreach($roleRanks as $role => $rank)
                                @if($rank <= $currentUserRank && $role !== $memberRole)
                                    <x-menu-item>
                                        <form action="{{ route('mitglieder.change-role', $member->id) }}" method="POST">
                                            @csrf
                                            @method('PUT')
                                            <input type="hidden" name="role" value="{{ $role }}">
                                            <button type="submit" class="w-full text-left">
                                                Zu {{ $role }} ändern
                                            </button>
                                        </form>
                                    </x-menu-item>
                                @endif
                            @endforeach
                        </x-dropdown>

                        <form action="{{ route('mitglieder.remove', $member->id) }}" method="POST"
                              class="flex-1"
                              onsubmit="return confirm('Willst du die Mitgliedschaft von {{ $member->name }} wirklich beenden? Dies löscht den Benutzer aus der Datenbank!');">
                            @csrf
                            @method('DELETE')
                            <x-button
                                type="submit"
                                icon="o-trash"
                                label="Löschen"
                                class="btn-error btn-sm w-full"
                            />
                        </form>
                    @endif
                @endif
            </div>
        </x-card>
        @endforeach
    </div>
    </x-card>
    </x-member-page>
</x-app-layout>