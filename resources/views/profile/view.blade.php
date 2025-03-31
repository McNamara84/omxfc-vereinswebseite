<x-app-layout>
    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6">
                <div class="flex flex-col lg:flex-row">
                    <!-- Profil-Header mit Foto und Basisdaten -->
                    <div class="w-full lg:w-1/3 flex flex-col items-center mb-8 lg:mb-0">
                        <div class="relative h-48 w-48 mb-4">
                            <img class="h-48 w-48 rounded-full object-cover" src="{{ $user->profile_photo_url }}" alt="{{ $user->name }}">
                        </div>
                        <h1 class="text-2xl font-bold text-gray-800 dark:text-white">{{ $user->name }}</h1>
                            <p class="text-lg text-gray-600 dark:text-gray-300">{{ $user->vorname }} {{ $user->nachname }}</p>
                            <p class="text-sm text-[#8B0116] mt-1">{{ $memberRole }}</p>
                            <!-- Nur für berechtigte Benutzer sichtbare Informationen -->
                        @if($canViewDetails)
                            <div class="mt-4 text-center">
                                <h3 class="text-lg font-semibold text-gray-800 dark:text-white mb-2">Kontaktdaten</h3>
                                <p class="text-gray-600 dark:text-gray-300">{{ $user->email }}</p>
                                @if($user->telefon)
                                    <p class="text-gray-600 dark:text-gray-300">{{ $user->telefon }}</p>
                                @endif
                            </div>
                            
                            <div class="mt-4 text-center">
                                <h3 class="text-lg font-semibold text-gray-800 dark:text-white mb-2">Adresse</h3>
                                @if($user->strasse && $user->hausnummer)
                                    <p class="text-gray-600 dark:text-gray-300">{{ $user->strasse }} {{ $user->hausnummer }}</p>
                                @endif
                                @if($user->plz && $user->stadt)
                                    <p class="text-gray-600 dark:text-gray-300">{{ $user->plz }} {{ $user->stadt }}</p>
                                @endif
                                @if($user->land)
                                    <p class="text-gray-600 dark:text-gray-300">{{ $user->land }}</p>
                                @endif
                            </div>
                            
                            <div class="mt-4 text-center">
                                <h3 class="text-lg font-semibold text-gray-800 dark:text-white mb-2">Mitgliedsbeitrag</h3>
                                <p class="text-gray-600 dark:text-gray-300">{{ $user->mitgliedsbeitrag }}</p>
                            </div>
                        @endif
                    </div>
                    
                    <!-- Serienbezogene Daten - für alle sichtbar -->
                    <div class="w-full lg:w-2/3 lg:pl-8">
                        <h2 class="text-xl font-semibold text-[#8B0116] mb-6">Meine Maddrax-Leidenschaft</h2>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            @if($user->einstiegsroman)
                                <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg shadow">
                                    <h3 class="font-semibold text-gray-800 dark:text-white mb-2">Einstiegsroman</h3>
                                    <p class="text-gray-600 dark:text-gray-300">{{ $user->einstiegsroman }}</p>
                                </div>
                            @endif
                            
                            @if($user->lesestand)
                                <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg shadow">
                                    <h3 class="font-semibold text-gray-800 dark:text-white mb-2">Aktueller Lesestand</h3>
                                    <p class="text-gray-600 dark:text-gray-300">{{ $user->lesestand }}</p>
                                </div>
                            @endif
                            
                            @if($user->lieblingsroman)
                                <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg shadow">
                                    <h3 class="font-semibold text-gray-800 dark:text-white mb-2">Lieblingsroman</h3>
                                    <p class="text-gray-600 dark:text-gray-300">{{ $user->lieblingsroman }}</p>
                                </div>
                            @endif
                            
                            @if($user->lieblingsfigur)
                                <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg shadow">
                                    <h3 class="font-semibold text-gray-800 dark:text-white mb-2">Lieblingsfigur</h3>
                                    <p class="text-gray-600 dark:text-gray-300">{{ $user->lieblingsfigur }}</p>
                                </div>
                            @endif
                            
                            @if($user->lieblingsmutation)
                                <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg shadow">
                                    <h3 class="font-semibold text-gray-800 dark:text-white mb-2">Lieblingsmutation</h3>
                                    <p class="text-gray-600 dark:text-gray-300">{{ $user->lieblingsmutation }}</p>
                                </div>
                            @endif
                            
                            @if($user->lieblingsschauplatz)
                                <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg shadow">
                                    <h3 class="font-semibold text-gray-800 dark:text-white mb-2">Lieblingsschauplatz</h3>
                                    <p class="text-gray-600 dark:text-gray-300">{{ $user->lieblingsschauplatz }}</p>
                                </div>
                            @endif
                            
                            @if($user->lieblingsautor)
                                <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg shadow">
                                    <h3 class="font-semibold text-gray-800 dark:text-white mb-2">Lieblingsautor</h3>
                                    <p class="text-gray-600 dark:text-gray-300">{{ $user->lieblingsautor }}</p>
                                </div>
                            @endif
                            
                            @if($user->lieblingszyklus)
                                <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg shadow">
                                    <h3 class="font-semibold text-gray-800 dark:text-white mb-2">Lieblingszyklus</h3>
                                    <p class="text-gray-600 dark:text-gray-300">{{ $user->lieblingszyklus }}-Zyklus</p>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>