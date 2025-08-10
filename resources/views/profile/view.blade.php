<x-app-layout>
    <x-member-page>
            <div class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg overflow-hidden">
                <!-- Hintergrund-Banner -->
                <div class="h-40 bg-gradient-to-r from-[#8B0116] to-[#FF6B81] relative">
                    <!-- Profilbild -->
                    <div class="absolute left-8 bottom-0 transform translate-y-1/2">
                        <div
                            class="h-36 w-36 border-4 border-white dark:border-gray-800 rounded-full overflow-hidden shadow-xl">
                            <img loading="lazy" class="h-full w-full object-cover" src="{{ $user->profile_photo_url }}"
                                alt="{{ $user->name }}">
                        </div>
                    </div>
                </div>

                <!-- Hauptinhalt -->
                <div class="pt-24 px-8 pb-8 md:grid md:grid-cols-3 md:gap-8">
                    <!-- Linke Spalte: Persönliche Informationen -->
                    <div class="col-span-1">
                        <div class="space-y-6">
                            <!-- Name und Rolle -->
                            <div>
                                <h1 class="text-2xl font-bold text-gray-800 dark:text-white">{{ $user->name }}</h1>
                                <p class="text-lg text-gray-600 dark:text-gray-300">{{ $user->vorname }}
                                    {{ $user->nachname }}
                                </p>
                                <div
                                    class="mt-1 inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-[#8B0116] text-white">
                                    {{ $memberRole }}
                                </div>
                                <div class="mt-2 text-sm text-gray-600 dark:text-gray-400 flex items-center">
                                    @if ($isOnline)
                                        <span class="flex items-center">
                                            <span class="relative flex h-3 w-3 mr-2">
                                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                                                <span class="relative inline-flex rounded-full h-3 w-3 bg-green-500"></span>
                                            </span>
                                            Online
                                        </span>
                                    @elseif ($lastSeen)
                                        Zuletzt gesehen {{ $lastSeen->diffForHumans() }}
                                    @endif
                                </div>
                            </div>

                            <!-- Badges -->
                            @if(count($badges) > 0)
                                <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-5">
                                    <h2 class="text-lg font-semibold text-gray-800 dark:text-white mb-4">Errungenschaften
                                    </h2>
                                    <div class="grid grid-cols-2 gap-4">
                                        @foreach($badges as $index => $badge)
                                            <div class="flex flex-col items-center">
                                                <div class="h-20 w-20 mb-2 cursor-pointer hover:opacity-90 transition-opacity"
                                                    onclick="openBadgeModal('{{ $badge['name'] }}', '{{ $badge['description'] }}', '{{ $badge['image'] }}')">
                                                    <img loading="lazy" src="{{ $badge['image'] }}" alt="{{ $badge['name'] }}" class="w-full">
                                                </div>
                                                <h3 class="text-sm font-medium text-gray-800 dark:text-white text-center">
                                                    {{ $badge['name'] }}
                                                </h3>
                                                <p class="text-xs text-gray-500 dark:text-gray-400 text-center mt-1">
                                                    {{ $badge['description'] }}
                                                </p>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            <!-- Kontaktdaten (nur für berechtigte Benutzer) -->
                            @if($canViewDetails)
                                <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-5">
                                    <h2 class="text-lg font-semibold text-gray-800 dark:text-white mb-3">Kontaktdaten</h2>
                                    <div class="space-y-2">
                                        <div class="flex items-center">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400 mr-2"
                                                fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                            </svg>
                                            <span class="text-gray-700 dark:text-gray-300">{{ $user->email }}</span>
                                        </div>
                                        @if($user->telefon)
                                            <div class="flex items-center">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400 mr-2"
                                                    fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                                </svg>
                                                <span class="text-gray-700 dark:text-gray-300">{{ $user->telefon }}</span>
                                            </div>
                                        @endif
                                    </div>
                                </div>

                                <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-5">
                                    <h2 class="text-lg font-semibold text-gray-800 dark:text-white mb-3">Adresse</h2>
                                    <address class="not-italic text-gray-700 dark:text-gray-300">
                                        @if($user->strasse && $user->hausnummer)
                                            {{ $user->strasse }} {{ $user->hausnummer }}<br>
                                        @endif
                                        @if($user->plz && $user->stadt)
                                            {{ $user->plz }} {{ $user->stadt }}<br>
                                        @endif
                                        @if($user->land)
                                            {{ $user->land }}
                                        @endif
                                    </address>
                                </div>

                                <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-5">
                                    <h2 class="text-lg font-semibold text-gray-800 dark:text-white mb-3">Mitgliedsbeitrag
                                    </h2>
                                    <p class="text-gray-700 dark:text-gray-300">{{ $user->mitgliedsbeitrag }}</p>
                                </div>
                            @endif
                        </div>
                    </div>

                    <!-- Rechte Spalte: Statistiken und Maddrax-Leidenschaft -->
                    <div class="col-span-2 mt-8 md:mt-0">
                        <!-- Vereinsaktivität -->
                        <div class="mb-8">
                            <h2 class="text-xl font-semibold text-[#8B0116] dark:text-[#FF6B81] mb-4 flex items-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mr-2" fill="none"
                                    viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                </svg>
                                Vereinsaktivität
                            </h2>

                            <div class="bg-gray-50 dark:bg-gray-700 p-6 rounded-lg shadow">
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between mb-8">
                                    <div
                                        class="text-center sm:text-left mb-6 sm:mb-0 bg-white dark:bg-gray-800 p-4 rounded-lg shadow-sm flex-1 mr-0 sm:mr-4">
                                        <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">
                                            Baxx</h3>
                                        <p class="text-4xl font-bold text-[#8B0116] dark:text-[#FF6B81]">
                                            {{ $userPoints }}
                                        </p>
                                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Baxx</p>
                                    </div>
                                    <div
                                        class="text-center sm:text-left bg-white dark:bg-gray-800 p-4 rounded-lg shadow-sm flex-1">
                                        <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Erledigte
                                            Challenges</h3>
                                        <p class="text-4xl font-bold text-gray-700 dark:text-gray-300">
                                            {{ $completedTasks }}
                                        </p>
                                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Challenges</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Maddrax-Leidenschaft -->
                        <div>
                            <h2 class="text-xl font-semibold text-[#8B0116] dark:text-[#FF6B81] mb-4 flex items-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mr-2" fill="none"
                                    viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                                </svg>
                                Meine Maddrax-Leidenschaft
                            </h2>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                @if($user->einstiegsroman)
                                    <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg shadow">
                                        <h3 class="font-semibold text-gray-800 dark:text-white mb-2">Einstiegsroman</h3>
                                        <p class="text-gray-600 dark:text-gray-300">{{ $user->einstiegsroman }}</p>
                                    </div>
                                @endif

                                @if($user->lesestand)
                                    <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg shadow">
                                        <h3 class="font-semibold text-gray-800 dark:text-white mb-2">Aktueller Lesestand
                                        </h3>
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
                                        <h3 class="font-semibold text-gray-800 dark:text-white mb-2">Lieblingsschauplatz
                                        </h3>
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
                                @if($user->lieblingsthema)
                                    <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg shadow">
                                        <h3 class="font-semibold text-gray-800 dark:text-white mb-2">Lieblingsthema</h3>
                                        <p class="text-gray-600 dark:text-gray-300">{{ $user->lieblingsthema }}</p>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Badge Modal -->
    <div id="badgeModal" class="fixed inset-0 z-50 hidden overflow-y-auto overflow-x-hidden">
        <div class="min-h-screen px-4 flex items-center justify-center">
            <div class="fixed inset-0 bg-black bg-opacity-75 transition-opacity" onclick="closeBadgeModal()"></div>

            <div
                class="relative bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-lg w-full mx-auto z-10 transform transition-all">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center">
                    <h3 id="badgeModalTitle" class="text-xl font-bold text-gray-900 dark:text-white"></h3>
                    <button type="button" onclick="closeBadgeModal()"
                        class="text-gray-400 hover:text-gray-500 focus:outline-none">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>

                <div class="p-6">
                    <div class="flex justify-center mb-6">
                        <div class="w-128 h-128">
                            <img loading="lazy" id="badgeModalImage" src="" alt="Badge" class="w-full h-full object-contain">
                        </div>
                    </div>
                    <p id="badgeModalDescription" class="text-base text-gray-700 dark:text-gray-300 text-center"></p>
                </div>

                <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 flex justify-end">
                    <button type="button" onclick="closeBadgeModal()"
                        class="px-4 py-2 bg-[#8B0116] text-white rounded-md hover:bg-[#a50119] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#8B0116]">
                        Schließen
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function openBadgeModal(name, description, imageUrl) {
            document.getElementById('badgeModalTitle').textContent = name;
            document.getElementById('badgeModalDescription').textContent = description;
            document.getElementById('badgeModalImage').src = imageUrl;
            document.getElementById('badgeModal').classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
        }

        function closeBadgeModal() {
            document.getElementById('badgeModal').classList.add('hidden');
            document.body.classList.remove('overflow-hidden');
        }

        // Schließen mit der Escape-Taste
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && !document.getElementById('badgeModal').classList.contains('hidden')) {
                closeBadgeModal();
            }
        });
    </script>
    </x-member-page>
</x-app-layout>