<x-app-layout>
    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
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

                <!-- Desktop-Ansicht (versteckt auf Mobilgeräten) -->
                <div class="hidden md:block overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead>
                            <tr>
                                <th class="px-4 py-2 text-left">
                                    <a href="{{ route('mitglieder.index', ['sort' => 'name', 'dir' => ($sortBy === 'name' && $sortDir === 'asc') ? 'desc' : 'asc']) }}" 
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
                                @if($canViewDetails)
                                    <th class="px-4 py-2 text-left text-gray-700 dark:text-gray-300">Kontaktdaten</th>
                                    <th class="px-4 py-2 text-left text-gray-700 dark:text-gray-300">Adresse</th>
                                    <th class="px-4 py-2 text-left">
                                        <a href="{{ route('mitglieder.index', ['sort' => 'mitgliedsbeitrag', 'dir' => ($sortBy === 'mitgliedsbeitrag' && $sortDir === 'asc') ? 'desc' : 'asc']) }}" 
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
                                @endif
                                <th class="px-4 py-2 text-left">
                                    <a href="{{ route('mitglieder.index', ['sort' => 'role', 'dir' => ($sortBy === 'role' && $sortDir === 'asc') ? 'desc' : 'asc']) }}" 
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
                                                <div class="font-medium text-gray-900 dark:text-gray-100">{{ $member->name }}</div>
                                                @if($canViewDetails)
                                                    <div class="text-sm text-gray-500 dark:text-gray-400">{{ $member->vorname }} {{ $member->nachname }}</div>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    
                                    @if($canViewDetails)
                                        <td class="px-4 py-3">
                                            <div class="text-sm text-gray-900 dark:text-gray-100">{{ $member->email }}</div>
                                            <div class="text-sm text-gray-500 dark:text-gray-400">{{ $member->telefon }}</div>
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="text-sm text-gray-900 dark:text-gray-100">{{ $member->strasse }} {{ $member->hausnummer }}</div>
                                            <div class="text-sm text-gray-500 dark:text-gray-400">{{ $member->plz }} {{ $member->stadt }}</div>
                                            <div class="text-sm text-gray-500 dark:text-gray-400">{{ $member->land }}</div>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">
                                            {{ $member->mitgliedsbeitrag }}
                                        </td>
                                    @endif
                                    <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">
                                        {{ $member->membership->role }}
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <div class="flex justify-center items-center space-x-1">
                                            <a href="{{ route('profile.view', $member->id) }}" 
                                               class="inline-flex items-center justify-center bg-blue-500 hover:bg-blue-600 text-white text-xs px-2 py-1 rounded" 
                                               title="Profil ansehen">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                </svg>
                                                <span class="ml-1 hidden md:inline">Profil</span>
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
                                                            <span class="ml-1 hidden md:inline">Rolle</span>
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
                                                            <span class="ml-1 hidden md:inline">Löschen</span>
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
                            <a href="{{ route('mitglieder.index', ['sort' => 'name', 'dir' => ($sortBy === 'name' && $sortDir === 'asc') ? 'desc' : 'asc']) }}"
                               class="px-3 py-1 text-xs rounded-full {{ $sortBy === 'name' ? 'bg-[#8B0116] text-white' : 'bg-gray-200 dark:bg-gray-600 text-gray-700 dark:text-gray-300' }}">
                                Name {{ $sortBy === 'name' ? ($sortDir === 'asc' ? '↑' : '↓') : '' }}
                            </a>
                            <a href="{{ route('mitglieder.index', ['sort' => 'role', 'dir' => ($sortBy === 'role' && $sortDir === 'asc') ? 'desc' : 'asc']) }}"
                               class="px-3 py-1 text-xs rounded-full {{ $sortBy === 'role' ? 'bg-[#8B0116] text-white' : 'bg-gray-200 dark:bg-gray-600 text-gray-700 dark:text-gray-300' }}">
                                Rolle {{ $sortBy === 'role' ? ($sortDir === 'asc' ? '↑' : '↓') : '' }}
                            </a>
                            @if($canViewDetails)
                                <a href="{{ route('mitglieder.index', ['sort' => 'mitgliedsbeitrag', 'dir' => ($sortBy === 'mitgliedsbeitrag' && $sortDir === 'asc') ? 'desc' : 'asc']) }}"
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
                                    <div class="font-medium text-gray-900 dark:text-gray-100">{{ $member->name }}</div>
                                    @if($canViewDetails)
                                        <div class="text-sm text-gray-500 dark:text-gray-400">{{ $member->vorname }} {{ $member->nachname }}</div>
                                    @endif
                                    <div class="text-xs text-gray-500 dark:text-gray-400">Rolle: {{ $member->membership->role }}</div>
                                </div>
                            </div>
                            
                            @if($canViewDetails)
                                <div class="mb-3">
                                    <h4 class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400 font-semibold mb-1">Kontaktdaten</h4>
                                    <div class="text-sm text-gray-900 dark:text-gray-100">{{ $member->email }}</div>
                                    <div class="text-sm text-gray-700 dark:text-gray-300">{{ $member->telefon }}</div>
                                </div>
                                
                                <div class="mb-3">
                                    <h4 class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400 font-semibold mb-1">Adresse</h4>
                                    <div class="text-sm text-gray-900 dark:text-gray-100">{{ $member->strasse }} {{ $member->hausnummer }}</div>
                                    <div class="text-sm text-gray-700 dark:text-gray-300">{{ $member->plz }} {{ $member->stadt }}</div>
                                    <div class="text-sm text-gray-700 dark:text-gray-300">{{ $member->land }}</div>
                                </div>
                                
                                <div class="mb-4">
                                    <h4 class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400 font-semibold mb-1">Beitrag</h4>
                                    <div class="text-sm text-gray-900 dark:text-gray-100">{{ $member->mitgliedsbeitrag }}</div>
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
        </div>
    </div>
</x-app-layout>