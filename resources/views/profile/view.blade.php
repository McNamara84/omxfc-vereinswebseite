<x-app-layout>
    <x-member-page>
        <x-card shadow class="overflow-hidden !p-0">
            <!-- Hintergrund-Banner -->
            <div class="h-40 bg-gradient-to-r from-primary to-primary/50 relative">
                <!-- Profilbild -->
                <div class="absolute left-8 bottom-0 transform translate-y-1/2">
                    <div
                        class="h-36 w-36 border-4 border-base-100 rounded-full overflow-hidden shadow-xl cursor-pointer"
                        onclick="openProfilePhotoModal('{{ $user->profile_photo_url }}', '{{ $user->name }}')">
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
                                <h1 class="text-2xl font-bold text-base-content">{{ $user->name }}</h1>
                                <p class="text-lg text-base-content">{{ $user->vorname }}
                                    {{ $user->nachname }}
                                </p>
                                <x-badge value="{{ $memberRole }}" class="badge-primary mt-1" />
                                <div class="mt-2 text-sm text-base-content flex items-center">
                                    @if ($isOnline)
                                        <span class="flex items-center">
                                            <span class="relative flex h-3 w-3 mr-2">
                                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-success opacity-75"></span>
                                                <span class="relative inline-flex rounded-full h-3 w-3 bg-success"></span>
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
                                <div class="bg-base-200 rounded-lg p-5">
                                    <h2 class="text-lg font-semibold text-base-content mb-4">Errungenschaften
                                    </h2>
                                    <div class="grid grid-cols-2 gap-4">
                                        @foreach($badges as $index => $badge)
                                            <div class="flex flex-col items-center">
                                                <div class="h-20 w-20 mb-2 cursor-pointer hover:opacity-90 transition-opacity"
                                                    onclick="openBadgeModal('{{ $badge['name'] }}', '{{ $badge['description'] }}', '{{ $badge['image'] }}')">
                                                    <img loading="lazy" src="{{ $badge['image'] }}" alt="{{ $badge['name'] }}" class="w-full">
                                                </div>
                                                <h3 class="text-sm font-medium text-base-content text-center">
                                                    {{ $badge['name'] }}
                                                </h3>
                                                <p class="text-xs text-base-content text-center mt-1">
                                                    {{ $badge['description'] }}
                                                </p>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            <!-- Kontaktdaten (nur für berechtigte Benutzer) -->
                            @if($canViewDetails)
                                <div class="bg-base-200 rounded-lg p-5">
                                    <h2 class="text-lg font-semibold text-base-content mb-3">Kontaktdaten</h2>
                                    <div class="space-y-2">
                                        <div class="flex items-center">
                                            <x-icon name="o-envelope" class="w-5 h-5 text-base-content mr-2" />
                                            <span class="text-base-content">{{ $user->email }}</span>
                                        </div>
                                        @if($user->telefon)
                                            <div class="flex items-center">
                                                <x-icon name="o-phone" class="w-5 h-5 text-base-content mr-2" />
                                                <span class="text-base-content">{{ $user->telefon }}</span>
                                            </div>
                                        @endif
                                    </div>
                                </div>

                                <div class="bg-base-200 rounded-lg p-5">
                                    <h2 class="text-lg font-semibold text-base-content mb-3">Adresse</h2>
                                    <address class="not-italic text-base-content">
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

                                <div class="bg-base-200 rounded-lg p-5">
                                    <h2 class="text-lg font-semibold text-base-content mb-3">Mitgliedsbeitrag
                                    </h2>
                                    <p class="text-base-content">{{ $user->mitgliedsbeitrag }}</p>
                                </div>
                            @endif
                        </div>
                    </div>

                    <!-- Rechte Spalte: Statistiken und Maddrax-Leidenschaft -->
                    <div class="col-span-2 mt-8 md:mt-0">
                        <!-- Vereinsaktivität -->
                        <div class="mb-8">
                            <h2 class="text-xl font-semibold text-primary mb-4 flex items-center">
                                <x-icon name="o-chart-bar" class="w-6 h-6 mr-2" />
                                Vereinsaktivität
                            </h2>

                            <div class="bg-base-200 p-6 rounded-lg shadow">
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between mb-8">
                                    <div
                                        class="text-center sm:text-left mb-6 sm:mb-0 bg-base-100 p-4 rounded-lg shadow-sm flex-1 mr-0 sm:mr-4">
                                        <h3 class="text-sm font-medium text-base-content mb-1">
                                            Baxx</h3>
                                        <p class="text-4xl font-bold text-primary">
                                            {{ $userPoints }}
                                        </p>
                                        <p class="text-sm text-base-content mt-1">Baxx</p>
                                    </div>
                                    <div
                                        class="text-center sm:text-left bg-base-100 p-4 rounded-lg shadow-sm flex-1">
                                        <h3 class="text-sm font-medium text-base-content mb-1">Erledigte
                                            Challenges</h3>
                                        <p class="text-4xl font-bold text-base-content">
                                            {{ $completedTasks }}
                                        </p>
                                        <p class="text-sm text-base-content mt-1">Challenges</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Maddrax-Leidenschaft -->
                        <div>
                            <h2 class="text-xl font-semibold text-primary mb-4 flex items-center">
                                <x-icon name="o-heart" class="w-6 h-6 mr-2" />
                                Meine Maddrax-Leidenschaft
                            </h2>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                @if($user->einstiegsroman)
                                    <div class="bg-base-200 p-4 rounded-lg shadow">
                                        <h3 class="font-semibold text-base-content mb-2">Einstiegsroman</h3>
                                        <p class="text-base-content">{{ $user->einstiegsroman }}</p>
                                    </div>
                                @endif

                                @if($user->lesestand)
                                    <div class="bg-base-200 p-4 rounded-lg shadow">
                                        <h3 class="font-semibold text-base-content mb-2">Aktueller Lesestand
                                        </h3>
                                        <p class="text-base-content">{{ $user->lesestand }}</p>
                                    </div>
                                @endif

                                @if($user->lieblingsroman)
                                    <div class="bg-base-200 p-4 rounded-lg shadow">
                                        <h3 class="font-semibold text-base-content mb-2">Lieblingsroman</h3>
                                        <p class="text-base-content">{{ $user->lieblingsroman }}</p>
                                    </div>
                                @endif

                                @if($user->lieblingshardcover)
                                    <div class="bg-base-200 p-4 rounded-lg shadow">
                                        <h3 class="font-semibold text-base-content mb-2">Lieblingshardcover</h3>
                                        <p class="text-base-content">{{ $user->lieblingshardcover }}</p>
                                    </div>
                                @endif

                                @if($user->lieblingscover)
                                    <div class="bg-base-200 p-4 rounded-lg shadow">
                                        <h3 class="font-semibold text-base-content mb-2">Lieblingscover</h3>
                                        <p class="text-base-content">{{ $user->lieblingscover }}</p>
                                    </div>
                                @endif

                                @if($user->lieblingsfigur)
                                    <div class="bg-base-200 p-4 rounded-lg shadow">
                                        <h3 class="font-semibold text-base-content mb-2">Lieblingsfigur</h3>
                                        <p class="text-base-content">{{ $user->lieblingsfigur }}</p>
                                    </div>
                                @endif

                                @if($user->lieblingsmutation)
                                    <div class="bg-base-200 p-4 rounded-lg shadow">
                                        <h3 class="font-semibold text-base-content mb-2">Lieblingsmutation</h3>
                                        <p class="text-base-content">{{ $user->lieblingsmutation }}</p>
                                    </div>
                                @endif

                                @if($user->lieblingsschauplatz)
                                    <div class="bg-base-200 p-4 rounded-lg shadow">
                                        <h3 class="font-semibold text-base-content mb-2">Lieblingsschauplatz
                                        </h3>
                                        <p class="text-base-content">{{ $user->lieblingsschauplatz }}</p>
                                    </div>
                                @endif

                                @if($user->lieblingsautor)
                                    <div class="bg-base-200 p-4 rounded-lg shadow">
                                        <h3 class="font-semibold text-base-content mb-2">Lieblingsautor</h3>
                                        <p class="text-base-content">{{ $user->lieblingsautor }}</p>
                                    </div>
                                @endif

                                @if($user->lieblingszyklus)
                                    <div class="bg-base-200 p-4 rounded-lg shadow">
                                        <h3 class="font-semibold text-base-content mb-2">Lieblingszyklus</h3>
                                        <p class="text-base-content">{{ $user->lieblingszyklus }}-Zyklus</p>
                                    </div>
                                @endif
                                @if($user->lieblingsthema)
                                    <div class="bg-base-200 p-4 rounded-lg shadow">
                                        <h3 class="font-semibold text-base-content mb-2">Lieblingsthema</h3>
                                        <p class="text-base-content">{{ $user->lieblingsthema }}</p>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </x-card>

        <!-- Profile Photo Modal -->
        <x-mary-modal id="profilePhotoModal" box-class="max-w-lg">
            <div class="flex justify-center">
                <img loading="lazy" id="profilePhotoModalImage" src="" alt="Profilbild" class="max-w-full max-h-[80vh] object-contain">
            </div>
            <x-slot:actions>
                <x-button label="Schließen" class="btn-primary" onclick="document.getElementById('profilePhotoModal').close()" />
            </x-slot:actions>
        </x-mary-modal>

        <!-- Badge Modal -->
        <x-mary-modal id="badgeModal" box-class="max-w-lg">
            <h3 id="badgeModalTitle" class="text-xl font-bold text-base-content mb-5"></h3>
            <div class="flex justify-center mb-6">
                <img loading="lazy" id="badgeModalImage" src="" alt="Badge" class="w-32 h-32 object-contain">
            </div>
            <p id="badgeModalDescription" class="text-base text-base-content text-center"></p>
            <x-slot:actions>
                <x-button label="Schließen" class="btn-primary" onclick="document.getElementById('badgeModal').close()" />
            </x-slot:actions>
        </x-mary-modal>

        <script>
            function openProfilePhotoModal(imageUrl, altText) {
                const img = document.getElementById('profilePhotoModalImage');
                img.src = imageUrl;
                img.alt = altText;
                document.getElementById('profilePhotoModal').showModal();
            }

            function openBadgeModal(name, description, imageUrl) {
                document.getElementById('badgeModalTitle').textContent = name;
                document.getElementById('badgeModalDescription').textContent = description;
                document.getElementById('badgeModalImage').src = imageUrl;
                document.getElementById('badgeModal').showModal();
            }
        </script>
    </x-member-page>
</x-app-layout>
