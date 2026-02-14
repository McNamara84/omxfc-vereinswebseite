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
        <h2 data-members-heading class="text-2xl font-extrabold">Mitgliederliste</h2>
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
    @endphp
    <p id="members-table-summary" data-members-summary class="sr-only" aria-live="polite">{{ $fallbackSummary }}</p>
    <!-- Filter -->
    <form method="GET" action="{{ route('mitglieder.index') }}" class="mb-6" x-data>
        <input type="hidden" name="sort" value="{{ $sortBy }}">
        <input type="hidden" name="dir" value="{{ $sortDir }}">
        <div class="flex flex-wrap gap-4 items-center">
            <label class="inline-flex items-center">
                <input type="checkbox" name="filters[]" value="online" @checked(in_array('online', $filters ?? [])) @change="$root.submit()" class="checkbox checkbox-primary" data-testid="mitglieder-filter-online">
                <span class="ml-2 text-base-content">Nur online</span>
            </label>
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
                    <button @click="
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
                        class="btn btn-info">
                        <x-icon name="o-clipboard-document" class="h-5 w-5 mr-2" />
                        E-Mail-Adressen kopieren
                    </button>
                </div>
            </div>
            
            <!-- CSV Export Optionen -->
            <div x-show="showExportOptions" class="mt-4">
                <form action="{{ route('mitglieder.export-csv') }}" method="POST" class="bg-base-100 p-4 rounded-md shadow">
                    @csrf
                    <div class="mb-3">
                        <h4 class="font-medium text-base-content mb-2">Zu exportierende Daten auswählen:</h4>
                        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
                            <label class="inline-flex items-center">
                                <input type="checkbox" name="export_fields[]" value="name" class="checkbox checkbox-primary" checked>
                                <span class="ml-2 text-base-content">Name (Vor-/Nachname)</span>
                            </label>
                            <label class="inline-flex items-center">
                                <input type="checkbox" name="export_fields[]" value="email" class="checkbox checkbox-primary" checked>
                                <span class="ml-2 text-base-content">E-Mail-Adresse</span>
                            </label>
                            <label class="inline-flex items-center">
                                <input type="checkbox" name="export_fields[]" value="adresse" class="checkbox checkbox-primary">
                                <span class="ml-2 text-base-content">Postadresse</span>
                            </label>
                            <label class="inline-flex items-center">
                                <input type="checkbox" name="export_fields[]" value="bezahlt_bis" class="checkbox checkbox-primary">
                                <span class="ml-2 text-base-content">Bezahlt bis</span>
                            </label>
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
    <div class="hidden md:block overflow-x-auto">
    <table class="min-w-full "
        data-members-table
        data-members-sort="{{ $sortBy }}"
        data-members-dir="{{ $sortDir }}"
        data-members-filter-online="{{ $filterOnlineActive ? 'true' : 'false' }}"
        data-members-total="{{ $memberCount }}"
        data-members-summary-id="members-table-summary"
        aria-describedby="members-table-summary">
    <thead>
    <tr>
    @php
        $nachnameSortState = $sortBy === 'nachname' ? ($sortDir === 'desc' ? 'descending' : 'ascending') : 'none';
        $mitgliedSeitSortState = $sortBy === 'mitglied_seit' ? ($sortDir === 'desc' ? 'descending' : 'ascending') : 'none';
        $roleSortState = $sortBy === 'role' ? ($sortDir === 'desc' ? 'descending' : 'ascending') : 'none';
        $lastActivitySortState = $sortBy === 'last_activity' ? ($sortDir === 'desc' ? 'descending' : 'ascending') : 'none';
        $beitragSortState = $sortBy === 'mitgliedsbeitrag' ? ($sortDir === 'desc' ? 'descending' : 'ascending') : 'none';
    @endphp
    <th scope="col" class="px-4 py-2 text-left" data-members-sort-column="nachname" aria-sort="{{ $nachnameSortState }}">
    <a href="{{ route('mitglieder.index', array_merge(request()->query(), ['sort' => 'nachname', 'dir' => ($sortBy === 'nachname' && $sortDir === 'asc') ? 'desc' : 'asc'])) }}"
    class="flex items-center group text-base-content hover:text-primary ">
    Name
    @if($sortBy === 'nachname')
    <span class="ml-1">
    @if($sortDir === 'asc')
    <x-icon name="o-chevron-up" class="h-4 w-4" />
    @else
    <x-icon name="o-chevron-down" class="h-4 w-4" />
    @endif
    </span>
    @endif
    </a>
    </th>

    <th scope="col" class="px-4 py-2 text-left" data-members-sort-column="mitglied_seit" aria-sort="{{ $mitgliedSeitSortState }}">
    <a href="{{ route('mitglieder.index', array_merge(request()->query(), ['sort' => 'mitglied_seit', 'dir' => ($sortBy === 'mitglied_seit' && $sortDir === 'asc') ? 'desc' : 'asc'])) }}"
    class="flex items-center group text-base-content hover:text-primary ">
    Mitglied seit
    @if($sortBy === 'mitglied_seit')
    <span class="ml-1">
    @if($sortDir === 'asc')
    <x-icon name="o-chevron-up" class="h-4 w-4" />
    @else
    <x-icon name="o-chevron-down" class="h-4 w-4" />
    @endif
    </span>
    @endif
    </a>
    </th>

    <th scope="col" class="px-4 py-2 text-left" data-members-sort-column="role" aria-sort="{{ $roleSortState }}">
    <a href="{{ route('mitglieder.index', array_merge(request()->query(), ['sort' => 'role', 'dir' => ($sortBy === 'role' && $sortDir === 'asc') ? 'desc' : 'asc'])) }}"
    class="flex items-center group text-base-content hover:text-primary ">
    Rolle
    @if($sortBy === 'role')
    <span class="ml-1">
    @if($sortDir === 'asc')
    <x-icon name="o-chevron-up" class="h-4 w-4" />
    @else
    <x-icon name="o-chevron-down" class="h-4 w-4" />
    @endif
    </span>
    @endif
    </a>
    </th>
    
    @if($canViewDetails)
    <th scope="col" class="px-4 py-2 text-left" data-members-sort-column="last_activity" aria-sort="{{ $lastActivitySortState }}">
    <a href="{{ route('mitglieder.index', array_merge(request()->query(), ['sort' => 'last_activity', 'dir' => ($sortBy === 'last_activity' && $sortDir === 'desc') ? 'asc' : 'desc'])) }}"
    class="flex items-center group text-base-content hover:text-primary ">
    Zuletzt online
    @if($sortBy === 'last_activity')
    <span class="ml-1">
    @if($sortDir === 'asc')
    <x-icon name="o-chevron-up" class="h-4 w-4" />
    @else
    <x-icon name="o-chevron-down" class="h-4 w-4" />
    @endif
    </span>
    @endif
    </a>
    </th>

    <th scope="col" class="px-4 py-2 text-left" data-members-sort-column="mitgliedsbeitrag" aria-sort="{{ $beitragSortState }}">
    <a href="{{ route('mitglieder.index', array_merge(request()->query(), ['sort' => 'mitgliedsbeitrag', 'dir' => ($sortBy === 'mitgliedsbeitrag' && $sortDir === 'asc') ? 'desc' : 'asc'])) }}"
    class="flex items-center group text-base-content hover:text-primary ">
    Beitrag
    @if($sortBy === 'mitgliedsbeitrag')
    <span class="ml-1">
    @if($sortDir === 'asc')
    <x-icon name="o-chevron-up" class="h-4 w-4" />
    @else
    <x-icon name="o-chevron-down" class="h-4 w-4" />
    @endif
    </span>
    @endif
    </a>
    </th>
    
    <th scope="col" class="px-4 py-2 text-left text-base-content hidden lg:table-cell">Details</th>
    @endif

    <th scope="col" class="px-4 py-2 text-center text-base-content">Aktionen</th>
    </tr>
    </thead>
    <tbody class="">
    @foreach($members as $member)
    <tr class="hover:bg-base-200">
    <td class="px-4 py-3">
    <a href="{{ route('profile.view', $member->id) }}" class="flex items-center">
    <div class="h-10 w-10 flex-shrink-0">
    <img loading="lazy" class="h-10 w-10 rounded-full" src="{{ $member->profile_photo_url }}" alt="{{ $member->name }}">
    </div>
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
    
    <td class="px-4 py-3 text-sm text-base-content">
    {{ $member->mitglied_seit ? $member->mitglied_seit->format('d.m.Y') : '-' }}
    </td>
    
    <td class="px-4 py-3 text-sm text-base-content">
    {{ $member->membership->role }}
    </td>
    
    @if($canViewDetails)
    <td class="px-4 py-3 text-sm text-base-content">
    {{ $member->last_activity ? \Carbon\Carbon::createFromTimestamp($member->last_activity, config('app.timezone'))->format('d.m.Y H:i') : '-' }}
    </td>
    
    <td class="px-4 py-3 text-sm text-base-content">
    {{ $member->mitgliedsbeitrag }}
    </td>
    
    <td class="px-4 py-3 text-sm text-base-content hidden lg:table-cell">
    <div x-data="{ showDetails: false }" class="relative">
    <button @click="showDetails = !showDetails" type="button"
    class="inline-flex items-center text-info hover:underline">
    <x-icon name="o-information-circle" class="h-4 w-4 mr-1" />
    Info
    </button>
    
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
    
    <td class="px-4 py-3 text-center">
    <div class="flex justify-center items-center space-x-1">
    <a href="{{ route('profile.view', $member->id) }}"
    class="inline-flex items-center justify-center btn btn-info btn-xs"
    title="Profil ansehen">
    <x-icon name="o-eye" class="h-4 w-4" />
    <span class="ml-1 hidden xl:inline">Profil</span>
    </a>

    @if($canViewDetails)
    <x-copy-email-button :email="$member->email" variant="desktop" />
    @endif
    
    @if($canViewDetails && $currentUser->id !== $member->id)
    @php
    $memberRole = $member->membership->role;
    $memberRank = $roleRanks[$memberRole] ?? 0;
    @endphp
    
    @if($currentUserRank > $memberRank)
    <!-- Rolle ändern (Dropdown) -->
    <div class="relative" x-data="{ open: false }">
    <button @click="open = !open" type="button"
    class="inline-flex items-center justify-center btn btn-warning btn-xs"
    title="Rolle ändern">
    <x-icon name="o-pencil-square" class="h-4 w-4" />
    <span class="ml-1 hidden xl:inline">Rolle</span>
    </button>
    
    <div x-show="open" @click.away="open = false"
    class="absolute right-0 mt-2 w-48 bg-base-100 rounded-md shadow-lg z-10">
    <div class="py-1">
    @foreach($roleRanks as $role => $rank)
    @if($rank <= $currentUserRank && $role !== $memberRole)
    <form action="{{ route('mitglieder.change-role', $member->id) }}" method="POST">
    @csrf
    @method('PUT')
    <input type="hidden" name="role" value="{{ $role }}">
    <button type="submit"
    class="block w-full text-left px-4 py-2 text-sm text-base-content hover:bg-base-200">
    Zu {{ $role }} ändern
    </button>
    </form>
    @endif
    @endforeach
    </div>
    </div>
    </div>
    
    <!-- Mitgliedschaft beenden -->
    <form action="{{ route('mitglieder.remove', $member->id) }}" method="POST"
    onsubmit="return confirm('Willst du die Mitgliedschaft von {{ $member->name }} wirklich beenden? Dies löscht den Benutzer aus der Datenbank!');">
    @csrf
    @method('DELETE')
    <button type="submit"
    class="inline-flex items-center justify-center btn btn-error btn-xs"
    title="Mitgliedschaft beenden">
    <x-icon name="o-trash" class="h-4 w-4" />
    <span class="ml-1 hidden xl:inline">Löschen</span>
    </button>
    </form>
    @endif
    @endif
    </div>
    </td>
    </tr>
    @endforeach
    </tbody>
    </table>
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
    <div class="bg-base-200 p-4 rounded-lg shadow">
    <a href="{{ route('profile.view', $member->id) }}" class="flex items-center mb-4">
    <div class="h-12 w-12 flex-shrink-0">
    <img loading="lazy" class="h-12 w-12 rounded-full" src="{{ $member->profile_photo_url }}" alt="{{ $member->name }}">
    </div>
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
    <div x-data="{ open: false }" class="mb-4">
    <button @click="open = !open" type="button"
    class="flex items-center justify-between w-full px-4 py-2 text-sm font-medium text-left text-base-content bg-base-200 rounded-md hover:bg-base-300 focus:outline-none focus-visible:ring focus-visible:ring-opacity-75">
    <span>Weitere Details anzeigen</span>
    <span :class="{'transform rotate-180': open}">
        <x-icon name="o-chevron-down" class="w-5 h-5" />
    </span>
    </button>
    <div x-show="open"
    x-transition:enter="transition ease-out duration-100"
    x-transition:enter-start="transform opacity-0 scale-95"
    x-transition:enter-end="transform opacity-100 scale-100"
    x-transition:leave="transition ease-in duration-75"
    x-transition:leave-start="transform opacity-100 scale-100"
    x-transition:leave-end="transform opacity-0 scale-95"
    class="mt-2 space-y-3">
    
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
    
    <div class="flex flex-row gap-2">
    <a href="{{ route('profile.view', $member->id) }}"
    class="flex-1 flex justify-center items-center btn btn-info py-2 px-3 rounded">
    <x-icon name="o-eye" class="h-4 w-4 mr-1" />
    Profil
    </a>

    @if($canViewDetails)
    <x-copy-email-button :email="$member->email" variant="mobile" />
    @endif
    
    @if($canViewDetails && $currentUser->id !== $member->id)
    @php
    $memberRole = $member->membership->role;
    $memberRank = $roleRanks[$memberRole] ?? 0;
    @endphp
    
    @if($currentUserRank > $memberRank)
    <!-- Rolle ändern (Mobile) -->
    <div class="relative flex-1" x-data="{ open: false }">
    <button @click="open = !open" type="button"
    class="w-full flex justify-center items-center btn btn-warning py-2 px-3 rounded">
    <x-icon name="o-pencil-square" class="h-4 w-4 mr-1" />
    Rolle
    </button>
    
    <div x-show="open" @click.away="open = false"
    class="absolute left-0 right-0 mt-2 bg-base-100 rounded-md shadow-lg z-10">
    <div class="py-1">
    @foreach($roleRanks as $role => $rank)
    @if($rank <= $currentUserRank && $role !== $memberRole)
    <form action="{{ route('mitglieder.change-role', $member->id) }}" method="POST">
    @csrf
    @method('PUT')
    <input type="hidden" name="role" value="{{ $role }}">
    <button type="submit"
    class="block w-full text-left px-4 py-2 text-sm text-base-content hover:bg-base-200">
    Zu {{ $role }} ändern
    </button>
    </form>
    @endif
    @endforeach
    </div>
    </div>
    </div>
    
    <form action="{{ route('mitglieder.remove', $member->id) }}" method="POST"
    class="flex-1"
    onsubmit="return confirm('Willst du die Mitgliedschaft von {{ $member->name }} wirklich beenden? Dies löscht den Benutzer aus der Datenbank!');">
    @csrf
    @method('DELETE')
    <button type="submit"
    class="w-full flex justify-center items-center btn btn-error py-2 px-3 rounded">
    <x-icon name="o-trash" class="h-4 w-4 mr-1" />
    Löschen
    </button>
    </form>
    @endif
    @endif
    </div>
    </div>
    @endforeach
    </div>
    </x-card>
    </x-member-page>
</x-app-layout>