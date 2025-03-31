<x-app-layout>
    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if(session('status'))
                <div class="mb-4 p-4 bg-green-100 border border-green-400 text-green-800 rounded">
                    {{ session('status') }}
                </div>
            @endif
            
            @if(session('error'))
                <div class="mb-4 p-4 bg-red-100 border border-red-400 text-red-800 rounded">
                    {{ session('error') }}
                </div>
            @endif
            
            <div class="bg-white shadow-xl sm:rounded-lg p-6">
                <h2 class="text-2xl font-semibold text-[#8B0116] mb-6">Mitgliederliste</h2>

                <!-- Desktop-Ansicht (versteckt auf Mobilgeräten) -->
                <div class="hidden md:block">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead>
                            <tr>
                                <th class="px-4 py-2 text-left">Name</th>
                                @if($canViewDetails)
                                    <th class="px-4 py-2 text-left">Kontaktdaten</th>
                                    <th class="px-4 py-2 text-left">Adresse</th>
                                    <th class="px-4 py-2 text-left">Beitrag</th>
                                @endif
                                <th class="px-4 py-2 text-center">Aktionen</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @foreach($members as $member)
                                <tr>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center">
                                            <div class="h-10 w-10 flex-shrink-0">
                                                <img class="h-10 w-10 rounded-full" src="{{ $member->profile_photo_url }}" alt="{{ $member->name }}">
                                            </div>
                                            <div class="ml-4">
                                                <div class="font-medium text-gray-900">{{ $member->name }}</div>
                                                @if($canViewDetails)
                                                    <div class="text-sm text-gray-500">{{ $member->vorname }} {{ $member->nachname }}</div>
                                                    <div class="text-xs text-gray-500">
                                                        {{ $member->membership->role }}
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    
                                    @if($canViewDetails)
                                        <td class="px-4 py-3">
                                            <div class="text-sm text-gray-900">{{ $member->email }}</div>
                                            <div class="text-sm text-gray-500">{{ $member->telefon }}</div>
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="text-sm text-gray-900">{{ $member->strasse }} {{ $member->hausnummer }}</div>
                                            <div class="text-sm text-gray-500">{{ $member->plz }} {{ $member->stadt }}</div>
                                            <div class="text-sm text-gray-500">{{ $member->land }}</div>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-900">
                                            {{ $member->mitgliedsbeitrag }}
                                        </td>
                                    @endif
                                    
                                    <td class="px-4 py-3 text-center">
                                        <div class="flex justify-center gap-2">
                                            <a href="{{ route('profile.show', ['user' => $member->id]) }}" 
                                               class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded">
                                                Profil
                                            </a>
                                            
                                            @if($canViewDetails && $currentUser->id !== $member->id)
                                                @php
                                                    $memberRole = $member->membership->role;
                                                    $memberRank = $roleRanks[$memberRole] ?? 0;
                                                @endphp
                                                
                                                @if($currentUserRank > $memberRank)
                                                    <form action="{{ route('mitglieder.remove', $member->id) }}" method="POST"
                                                        onsubmit="return confirm('Willst du die Mitgliedschaft von {{ $member->name }} wirklich beenden? Dies löscht den Benutzer aus der Datenbank!');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" 
                                                            class="bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded">
                                                            Mitgliedschaft beenden
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
                    @foreach($members as $member)
                        <div class="bg-gray-50 p-4 rounded-lg shadow">
                            <div class="flex items-center mb-4">
                                <div class="h-12 w-12 flex-shrink-0">
                                    <img class="h-12 w-12 rounded-full" src="{{ $member->profile_photo_url }}" alt="{{ $member->name }}">
                                </div>
                                <div class="ml-4">
                                    <div class="font-medium text-gray-900">{{ $member->name }}</div>
                                    @if($canViewDetails)
                                        <div class="text-sm text-gray-500">{{ $member->vorname }} {{ $member->nachname }}</div>
                                        <div class="text-xs text-gray-500">{{ $member->membership->role }}</div>
                                    @endif
                                </div>
                            </div>
                            
                            @if($canViewDetails)
                                <div class="mb-3">
                                    <h4 class="text-xs uppercase tracking-wide text-gray-500 font-semibold mb-1">Kontaktdaten</h4>
                                    <div class="text-sm">{{ $member->email }}</div>
                                    <div class="text-sm">{{ $member->telefon }}</div>
                                </div>
                                
                                <div class="mb-3">
                                    <h4 class="text-xs uppercase tracking-wide text-gray-500 font-semibold mb-1">Adresse</h4>
                                    <div class="text-sm">{{ $member->strasse }} {{ $member->hausnummer }}</div>
                                    <div class="text-sm">{{ $member->plz }} {{ $member->stadt }}</div>
                                    <div class="text-sm">{{ $member->land }}</div>
                                </div>
                                
                                <div class="mb-4">
                                    <h4 class="text-xs uppercase tracking-wide text-gray-500 font-semibold mb-1">Beitrag</h4>
                                    <div class="text-sm">{{ $member->mitgliedsbeitrag }}</div>
                                </div>
                            @endif
                            
                            <div class="flex gap-2">
                                <a href="{{ route('profile.show', ['user' => $member->id]) }}" 
                                   class="flex-1 block text-center bg-blue-500 hover:bg-blue-600 text-white py-2 px-4 rounded">
                                    Profil ansehen
                                </a>
                                
                                @if($canViewDetails && $currentUser->id !== $member->id)
                                    @php
                                        $memberRole = $member->membership->role;
                                        $memberRank = $roleRanks[$memberRole] ?? 0;
                                    @endphp
                                    
                                    @if($currentUserRank > $memberRank)
                                        <form action="{{ route('mitglieder.remove', $member->id) }}" method="POST" class="flex-1"
                                            onsubmit="return confirm('Willst du die Mitgliedschaft von {{ $member->name }} wirklich beenden? Dies löscht den Benutzer aus der Datenbank!');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" 
                                                class="w-full bg-red-500 hover:bg-red-600 text-white py-2 px-4 rounded">
                                                Mitgliedschaft beenden
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