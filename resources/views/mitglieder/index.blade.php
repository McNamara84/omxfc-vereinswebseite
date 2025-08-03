<x-app-layout>
    <x-member-page>
    @if(session('status'))
    <div class="mb-4 p-4 bg-green-100 border border-green-400 text-green-800 dark:bg-green-800 dark:border-green-700 dark:text-green-100 rounded">
    {{ session('status') }}
    </div>
    @endif
    
    @if(session('error'))
    <div class="mb-4 p-4 bg-red-100 border border-red-400 text-red-800 dark:bg-red-800 dark:border-red-700 dark:text-red-100 rounded">
    {{ session('error') }}
    </div>
    @endif
    
    <div class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6">
    <h2 class="text-2xl font-semibold text-[#8B0116] dark:text-red-400 mb-6">Mitgliederliste</h2>
    <!-- Filter -->
    <form method="GET" action="{{ route('mitglieder.index') }}" class="mb-6" x-data>
        <input type="hidden" name="sort" value="{{ $sortBy }}">
        <input type="hidden" name="dir" value="{{ $sortDir }}">
        <div class="flex flex-wrap gap-4 items-center">
            <label class="inline-flex items-center">
                <input type="checkbox" name="filters[]" value="online" @checked(in_array('online', $filters ?? [])) @change="$root.submit()" class="rounded border-gray-300 text-[#8B0116] shadow-sm focus:ring-[#8B0116]">
                <span class="ml-2 text-gray-700 dark:text-gray-300">Nur online</span>
            </label>
        </div>
    </form>
    <!-- Export-Funktionen für berechtigte Benutzer -->
    @if($canViewDetails)
    <div class="mb-6">
        <div x-data="{ showExportOptions: false, showEmailCopy: false, emailsCopied: false }" class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
            <div class="flex flex-wrap gap-4 items-center justify-between">
                <h3 class="text-lg font-medium text-gray-700 dark:text-gray-300">Datenexport & Funktionen</h3>
                
                <div class="flex space-x-2">
                    <!-- CSV Export Button -->
                    <button @click="showExportOptions = !showExportOptions" type="button" 
                        class="inline-flex items-center px-4 py-2 bg-[#8B0116] text-white rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        CSV Export
                    </button>
                    
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
                        class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2" />
                        </svg>
                        E-Mail-Adressen kopieren
                    </button>
                </div>
            </div>
            
            <!-- CSV Export Optionen -->
            <div x-show="showExportOptions" class="mt-4">
                <form action="{{ route('mitglieder.export-csv') }}" method="POST" class="bg-white dark:bg-gray-800 p-4 rounded-md shadow">
                    @csrf
                    <div class="mb-3">
                        <h4 class="font-medium text-gray-700 dark:text-gray-300 mb-2">Zu exportierende Daten auswählen:</h4>
                        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
                            <label class="inline-flex items-center">
                                <input type="checkbox" name="export_fields[]" value="name" class="rounded border-gray-300 text-[#8B0116] shadow-sm focus:border-red-300 focus:ring focus:ring-red-200 focus:ring-opacity-50" checked>
                                <span class="ml-2 text-gray-700 dark:text-gray-300">Name (Vor-/Nachname)</span>
                            </label>
                            <label class="inline-flex items-center">
                                <input type="checkbox" name="export_fields[]" value="email" class="rounded border-gray-300 text-[#8B0116] shadow-sm focus:border-red-300 focus:ring focus:ring-red-200 focus:ring-opacity-50" checked>
                                <span class="ml-2 text-gray-700 dark:text-gray-300">E-Mail-Adresse</span>
                            </label>
                            <label class="inline-flex items-center">
                                <input type="checkbox" name="export_fields[]" value="adresse" class="rounded border-gray-300 text-[#8B0116] shadow-sm focus:border-red-300 focus:ring focus:ring-red-200 focus:ring-opacity-50">
                                <span class="ml-2 text-gray-700 dark:text-gray-300">Postadresse</span>
                            </label>
                            <label class="inline-flex items-center">
                                <input type="checkbox" name="export_fields[]" value="bezahlt_bis" class="rounded border-gray-300 text-[#8B0116] shadow-sm focus:border-red-300 focus:ring focus:ring-red-200 focus:ring-opacity-50">
                                <span class="ml-2 text-gray-700 dark:text-gray-300">Bezahlt bis</span>
                            </label>
                        </div>
                    </div>
                    <div class="flex justify-end">
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-[#8B0116] text-white rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                            </svg>
                            CSV herunterladen
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Erfolgsmeldung für E-Mail-Kopieren -->
            <div x-show="emailsCopied" x-transition class="mt-3 text-sm text-green-600 dark:text-green-400">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 inline-block mr-1" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                </svg>
                E-Mail-Adressen wurden in die Zwischenablage kopiert!
            </div>
        </div>
    </div>
    @endif
    
    <!-- Desktop-Ansicht (versteckt auf Mobilgeräten) -->
    <div class="hidden md:block overflow-x-auto">
    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
    <thead>
    <tr>
    <th class="px-4 py-2 text-left">
    <a href="{{ route('mitglieder.index', array_merge(request()->query(), ['sort' => 'name', 'dir' => ($sortBy === 'name' && $sortDir === 'asc') ? 'desc' : 'asc'])) }}"
    class="flex items-center group text-gray-700 dark:text-gray-300 hover:text-[#8B0116] dark:hover:text-red-400">
    Name
    @if($sortBy === 'name')
    <span class="ml-1">
    @if($sortDir === 'asc')
    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
    </svg>
    @else
    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
    </svg>
    @endif
    </span>
    @endif
    </a>
    </th>
    
    <th class="px-4 py-2 text-left">
    <a href="{{ route('mitglieder.index', array_merge(request()->query(), ['sort' => 'mitglied_seit', 'dir' => ($sortBy === 'mitglied_seit' && $sortDir === 'asc') ? 'desc' : 'asc'])) }}"
    class="flex items-center group text-gray-700 dark:text-gray-300 hover:text-[#8B0116] dark:hover:text-red-400">
    Mitglied seit
    @if($sortBy === 'mitglied_seit')
    <span class="ml-1">
    @if($sortDir === 'asc')
    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
    </svg>
    @else
    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
    </svg>
    @endif
    </span>
    @endif
    </a>
    </th>
    
    <th class="px-4 py-2 text-left">
    <a href="{{ route('mitglieder.index', array_merge(request()->query(), ['sort' => 'role', 'dir' => ($sortBy === 'role' && $sortDir === 'asc') ? 'desc' : 'asc'])) }}"
    class="flex items-center group text-gray-700 dark:text-gray-300 hover:text-[#8B0116] dark:hover:text-red-400">
    Rolle
    @if($sortBy === 'role')
    <span class="ml-1">
    @if($sortDir === 'asc')
    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
    </svg>
    @else
    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
    </svg>
    @endif
    </span>
    @endif
    </a>
    </th>
    
    @if($canViewDetails)
    <th class="px-4 py-2 text-left">
    <a href="{{ route('mitglieder.index', array_merge(request()->query(), ['sort' => 'last_activity', 'dir' => ($sortBy === 'last_activity' && $sortDir === 'asc') ? 'desc' : 'asc'])) }}"
    class="flex items-center group text-gray-700 dark:text-gray-300 hover:text-[#8B0116] dark:hover:text-red-400">
    Zuletzt online
    @if($sortBy === 'last_activity')
    <span class="ml-1">
    @if($sortDir === 'asc')
    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
    </svg>
    @else
    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
    </svg>
    @endif
    </span>
    @endif
    </a>
    </th>

    <th class="px-4 py-2 text-left">
    <a href="{{ route('mitglieder.index', array_merge(request()->query(), ['sort' => 'mitgliedsbeitrag', 'dir' => ($sortBy === 'mitgliedsbeitrag' && $sortDir === 'asc') ? 'desc' : 'asc'])) }}"
    class="flex items-center group text-gray-700 dark:text-gray-300 hover:text-[#8B0116] dark:hover:text-red-400">
    Beitrag
    @if($sortBy === 'mitgliedsbeitrag')
    <span class="ml-1">
    @if($sortDir === 'asc')
    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
    </svg>
    @else
    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
    </svg>
    @endif
    </span>
    @endif
    </a>
    </th>
    
    <th class="px-4 py-2 text-left text-gray-700 dark:text-gray-300 hidden lg:table-cell">Details</th>
    @endif
    
    <th class="px-4 py-2 text-center text-gray-700 dark:text-gray-300">Aktionen</th>
    </tr>
    </thead>
    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
    @foreach($members as $member)
    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
    <td class="px-4 py-3">
    <div class="flex items-center">
    <div class="h-10 w-10 flex-shrink-0">
    <img class="h-10 w-10 rounded-full" src="{{ $member->profile_photo_url }}" alt="{{ $member->name }}">
    </div>
    <div class="ml-4">
    <div class="font-medium text-gray-900 dark:text-gray-100 flex items-center">
        <span class="inline-block w-2 h-2 rounded-full mr-2 {{ in_array($member->id, $onlineUserIds) ? 'bg-green-500' : 'bg-gray-400' }}" title="{{ in_array($member->id, $onlineUserIds) ? 'Online' : 'Offline' }}"></span>
        {{ $member->name }}
    </div>
    @if($canViewDetails)
    <div class="text-sm text-gray-500 dark:text-gray-400">{{ $member->vorname }} {{ $member->nachname }}</div>
    @endif
    </div>
    </div>
    </td>
    
    <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">
    {{ $member->mitglied_seit ? $member->mitglied_seit->format('d.m.Y') : '-' }}
    </td>
    
    <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">
    {{ $member->membership->role }}
    </td>
    
    @if($canViewDetails)
    <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">
    {{ $member->last_activity ? \Carbon\Carbon::createFromTimestamp($member->last_activity, config('app.timezone'))->format('d.m.Y H:i') : '-' }}
    </td>
    
    <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">
    {{ $member->mitgliedsbeitrag }}
    </td>
    
    <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400 hidden lg:table-cell">
    <div x-data="{ showDetails: false }" class="relative">
    <button @click="showDetails = !showDetails" type="button"
    class="inline-flex items-center text-blue-600 dark:text-blue-400 hover:underline">
    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
    </svg>
    Info
    </button>
    
    <div x-show="showDetails" @click.away="showDetails = false"
    class="absolute left-0 mt-2 w-64 bg-white dark:bg-gray-800 rounded-md shadow-lg z-10 p-4">
    <h4 class="font-semibold text-gray-900 dark:text-gray-100 mb-2">Kontaktdaten</h4>
    <div class="mb-3">
    <div class="text-sm">{{ $member->email }}</div>
    <div class="text-sm">{{ $member->telefon }}</div>
    </div>
    
    <h4 class="font-semibold text-gray-900 dark:text-gray-100 mb-2">Adresse</h4>
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
    class="inline-flex items-center justify-center bg-blue-500 hover:bg-blue-600 text-white text-xs px-2 py-1 rounded"
    title="Profil ansehen">
    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
    </svg>
    <span class="ml-1 hidden xl:inline">Profil</span>
    </a>
    
    @if($canViewDetails && $currentUser->id !== $member->id)
    @php
    $memberRole = $member->membership->role;
    $memberRank = $roleRanks[$memberRole] ?? 0;
    @endphp
    
    @if($currentUserRank > $memberRank)
    <!-- Rolle ändern (Dropdown) -->
    <div class="relative" x-data="{ open: false }">
    <button @click="open = !open" type="button"
    class="inline-flex items-center justify-center bg-yellow-500 hover:bg-yellow-600 text-white text-xs px-2 py-1 rounded"
    title="Rolle ändern">
    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
    </svg>
    <span class="ml-1 hidden xl:inline">Rolle</span>
    </button>
    
    <div x-show="open" @click.away="open = false"
    class="absolute right-0 mt-2 w-48 bg-white dark:bg-gray-800 rounded-md shadow-lg z-10">
    <div class="py-1">
    @foreach($roleRanks as $role => $rank)
    @if($rank <= $currentUserRank && $role !== $memberRole)
    <form action="{{ route('mitglieder.change-role', $member->id) }}" method="POST">
    @csrf
    @method('PUT')
    <input type="hidden" name="role" value="{{ $role }}">
    <button type="submit"
    class="block w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700">
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
    class="inline-flex items-center justify-center bg-red-500 hover:bg-red-600 text-white text-xs px-2 py-1 rounded"
    title="Mitgliedschaft beenden">
    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
    </svg>
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
    <div class="mb-4 bg-gray-100 dark:bg-gray-700 rounded-lg p-3">
    <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Sortieren nach:</h3>
    <div class="flex flex-wrap gap-2">
    <a href="{{ route('mitglieder.index', array_merge(request()->query(), ['sort' => 'nachname', 'dir' => ($sortBy === 'nachname' && $sortDir === 'asc') ? 'desc' : 'asc'])) }}"
    class="px-3 py-1 text-xs rounded-full {{ $sortBy === 'nachname' ? 'bg-[#8B0116] text-white' : 'bg-gray-200 dark:bg-gray-600 text-gray-700 dark:text-gray-300' }}">
    Name {{ $sortBy === 'nachname' ? ($sortDir === 'asc' ? '↑' : '↓') : '' }}
    </a>
    <a href="{{ route('mitglieder.index', array_merge(request()->query(), ['sort' => 'mitglied_seit', 'dir' => ($sortBy === 'mitglied_seit' && $sortDir === 'asc') ? 'desc' : 'asc'])) }}"
    class="px-3 py-1 text-xs rounded-full {{ $sortBy === 'mitglied_seit' ? 'bg-[#8B0116] text-white' : 'bg-gray-200 dark:bg-gray-600 text-gray-700 dark:text-gray-300' }}">
    Mitglied seit {{ $sortBy === 'mitglied_seit' ? ($sortDir === 'asc' ? '↑' : '↓') : '' }}
    </a>
    <a href="{{ route('mitglieder.index', array_merge(request()->query(), ['sort' => 'role', 'dir' => ($sortBy === 'role' && $sortDir === 'asc') ? 'desc' : 'asc'])) }}"
    class="px-3 py-1 text-xs rounded-full {{ $sortBy === 'role' ? 'bg-[#8B0116] text-white' : 'bg-gray-200 dark:bg-gray-600 text-gray-700 dark:text-gray-300' }}">
    Rolle {{ $sortBy === 'role' ? ($sortDir === 'asc' ? '↑' : '↓') : '' }}
    </a>
    @if($canViewDetails)
    <a href="{{ route('mitglieder.index', array_merge(request()->query(), ['sort' => 'last_activity', 'dir' => ($sortBy === 'last_activity' && $sortDir === 'asc') ? 'desc' : 'asc'])) }}"
    class="px-3 py-1 text-xs rounded-full {{ $sortBy === 'last_activity' ? 'bg-[#8B0116] text-white' : 'bg-gray-200 dark:bg-gray-600 text-gray-700 dark:text-gray-300' }}">
    Zuletzt online {{ $sortBy === 'last_activity' ? ($sortDir === 'asc' ? '↑' : '↓') : '' }}
    </a>
    <a href="{{ route('mitglieder.index', array_merge(request()->query(), ['sort' => 'mitgliedsbeitrag', 'dir' => ($sortBy === 'mitgliedsbeitrag' && $sortDir === 'asc') ? 'desc' : 'asc'])) }}"
    class="px-3 py-1 text-xs rounded-full {{ $sortBy === 'mitgliedsbeitrag' ? 'bg-[#8B0116] text-white' : 'bg-gray-200 dark:bg-gray-600 text-gray-700 dark:text-gray-300' }}">
    Beitrag {{ $sortBy === 'mitgliedsbeitrag' ? ($sortDir === 'asc' ? '↑' : '↓') : '' }}
    </a>
    @endif
    </div>
    </div>
    
    @foreach($members as $member)
    <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg shadow">
    <div class="flex items-center mb-4">
    <div class="h-12 w-12 flex-shrink-0">
    <img class="h-12 w-12 rounded-full" src="{{ $member->profile_photo_url }}" alt="{{ $member->name }}">
    </div>
    <div class="ml-4">
    <div class="font-medium text-gray-900 dark:text-gray-100 flex items-center">
        <span class="inline-block w-2 h-2 rounded-full mr-2 {{ in_array($member->id, $onlineUserIds) ? 'bg-green-500' : 'bg-gray-400' }}" title="{{ in_array($member->id, $onlineUserIds) ? 'Online' : 'Offline' }}"></span>
        {{ $member->name }}
    </div>
    <div class="text-xs text-gray-500 dark:text-gray-400">
    {{ $member->membership->role }} •
    Mitglied seit {{ $member->mitglied_seit ? $member->mitglied_seit->format('d.m.Y') : 'k.A.' }}
    </div>
    </div>
    </div>
    
    <div class="mb-4">
    <div class="grid grid-cols-2 gap-4">
    @if($canViewDetails)
    <div>
    <h4 class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400 font-semibold mb-1">Zuletzt online</h4>
    <div class="text-sm text-gray-900 dark:text-gray-100">
    {{ $member->last_activity ? \Carbon\Carbon::createFromTimestamp($member->last_activity, config('app.timezone'))->format('d.m.Y H:i') : '-' }}
    </div>
    </div>
    
    <div>
    <h4 class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400 font-semibold mb-1">Beitrag</h4>
    <div class="text-sm text-gray-900 dark:text-gray-100">{{ $member->mitgliedsbeitrag }}</div>
    </div>
    @endif
    </div>
    </div>
    
    @if($canViewDetails)
    <div x-data="{ open: false }" class="mb-4">
    <button @click="open = !open" type="button"
    class="flex items-center justify-between w-full px-4 py-2 text-sm font-medium text-left text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-600 rounded-md hover:bg-gray-200 dark:hover:bg-gray-500 focus:outline-none focus-visible:ring focus-visible:ring-opacity-75">
    <span>Weitere Details anzeigen</span>
    <svg :class="{'transform rotate-180': open}" class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
    </svg>
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
    <h4 class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400 font-semibold mb-1">Kontaktdaten</h4>
    <div class="text-sm text-gray-900 dark:text-gray-100">{{ $member->email }}</div>
    <div class="text-sm text-gray-700 dark:text-gray-300">{{ $member->telefon }}</div>
    </div>
    
    <div>
    <h4 class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400 font-semibold mb-1">Adresse</h4>
    <div class="text-sm text-gray-900 dark:text-gray-100">{{ $member->strasse }} {{ $member->hausnummer }}</div>
    <div class="text-sm text-gray-700 dark:text-gray-300">{{ $member->plz }} {{ $member->stadt }}</div>
    <div class="text-sm text-gray-700 dark:text-gray-300">{{ $member->land }}</div>
    </div>
    </div>
    </div>
    @endif
    
    <div class="flex flex-row gap-2">
    <a href="{{ route('profile.view', $member->id) }}"
    class="flex-1 flex justify-center items-center bg-blue-500 hover:bg-blue-600 text-white py-2 px-3 rounded">
    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
    </svg>
    Profil
    </a>
    
    @if($canViewDetails && $currentUser->id !== $member->id)
    @php
    $memberRole = $member->membership->role;
    $memberRank = $roleRanks[$memberRole] ?? 0;
    @endphp
    
    @if($currentUserRank > $memberRank)
    <!-- Rolle ändern (Mobile) -->
    <div class="relative flex-1" x-data="{ open: false }">
    <button @click="open = !open" type="button"
    class="w-full flex justify-center items-center bg-yellow-500 hover:bg-yellow-600 text-white py-2 px-3 rounded">
    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
    </svg>
    Rolle
    </button>
    
    <div x-show="open" @click.away="open = false"
    class="absolute left-0 right-0 mt-2 bg-white dark:bg-gray-800 rounded-md shadow-lg z-10">
    <div class="py-1">
    @foreach($roleRanks as $role => $rank)
    @if($rank <= $currentUserRank && $role !== $memberRole)
    <form action="{{ route('mitglieder.change-role', $member->id) }}" method="POST">
    @csrf
    @method('PUT')
    <input type="hidden" name="role" value="{{ $role }}">
    <button type="submit"
    class="block w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700">
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
    class="w-full flex justify-center items-center bg-red-500 hover:bg-red-600 text-white py-2 px-3 rounded">
    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
    </svg>
    Löschen
    </button>
    </form>
    @endif
    @endif
    </div>
    </div>
    @endforeach
    </div>
    </div>
    </x-member-page>
</x-app-layout>