<x-app-layout>
    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
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
                                <th class="px-4 py-2 text-center">Profil</th>
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
                                        <a href="{{ route('profile.show', ['user' => $member->id]) }}" 
                                           class="text-[#8B0116] hover:text-[#c2334a] font-medium">
                                            Profil ansehen
                                        </a>
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
                            
                            <a href="{{ route('profile.show', ['user' => $member->id]) }}" 
                               class="block text-center bg-[#8B0116] hover:bg-[#c2334a] text-white py-2 px-4 rounded">
                                Profil ansehen
                            </a>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</x-app-layout>