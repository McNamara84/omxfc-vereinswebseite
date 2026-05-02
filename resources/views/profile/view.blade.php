<x-app-layout>
    <x-member-page>
        @php
            $activityLabel = $isOnline
                ? 'Online'
                : ($lastSeen ? 'Zuletzt gesehen ' . $lastSeen->diffForHumans() : 'Aktuell keine Aktivität erfasst');

            $profileDescription = $isOwnProfile
                ? 'Dein persönlicher Überblick über Vereinsaktivität, Errungenschaften und deine Maddrax-Leidenschaft.'
                : 'Öffentliche und freigegebene Vereinsinformationen dieses Mitglieds auf einen Blick.';

            $maddraxLeidenschaft = collect([
                ['label' => 'Einstiegsroman', 'value' => $user->einstiegsroman],
                ['label' => 'Aktueller Lesestand', 'value' => $user->lesestand],
                ['label' => 'Lieblingsroman', 'value' => $user->lieblingsroman],
                ['label' => 'Lieblingshardcover', 'value' => $user->lieblingshardcover],
                ['label' => 'Lieblingscover', 'value' => $user->lieblingscover],
                ['label' => 'Lieblingsfigur', 'value' => $user->lieblingsfigur],
                ['label' => 'Lieblingsmutation', 'value' => $user->lieblingsmutation],
                ['label' => 'Lieblingsschauplatz', 'value' => $user->lieblingsschauplatz],
                ['label' => 'Lieblingsautor', 'value' => $user->lieblingsautor],
                ['label' => 'Lieblingszyklus', 'value' => $user->lieblingszyklus ? $user->lieblingszyklus . '-Zyklus' : null],
                ['label' => 'Lieblingsthema', 'value' => $user->lieblingsthema],
            ])->filter(fn (array $entry) => filled($entry['value']))->values();
        @endphp

        <div class="space-y-8">
            <x-ui.page-header
                eyebrow="Mitgliederbereich"
                title="{{ $user->name }}"
                description="{{ $profileDescription }}"
            >
                <x-slot:actions>
                    <x-button
                        label="Zur Mitgliederliste"
                        icon="o-users"
                        link="{{ route('mitglieder.index') }}"
                        wire:navigate
                        class="btn-outline"
                    />
                </x-slot:actions>
            </x-ui.page-header>

            <div class="grid gap-8 xl:grid-cols-[minmax(0,0.95fr)_minmax(0,1.4fr)]">
                <div class="space-y-6">
                    <x-ui.panel class="overflow-hidden !p-0">
                        <div class="relative h-36 bg-linear-to-r from-primary via-primary/85 to-accent/60 sm:h-40">
                            <button
                                type="button"
                                class="group absolute left-6 bottom-0 flex h-28 w-28 translate-y-1/2 items-center justify-center overflow-hidden rounded-full border-4 border-base-100 bg-base-100 shadow-xl transition-transform hover:scale-[1.02] focus:outline-none focus:ring-2 focus:ring-primary/40 sm:left-8 sm:h-36 sm:w-36"
                                onclick="openProfilePhotoModal(@js($user->profile_photo_url), @js($user->name))"
                                aria-label="Profilbild von {{ $user->name }} vergrößern"
                            >
                                <img
                                    loading="lazy"
                                    class="h-full w-full object-cover"
                                    src="{{ $user->profile_photo_url }}"
                                    alt="{{ $user->name }}"
                                >
                            </button>
                        </div>

                        <div class="space-y-6 px-6 pb-6 pt-18 sm:px-8 sm:pb-8 sm:pt-24">
                            <div class="space-y-3">
                                <div class="space-y-1">
                                    <h1 class="font-display text-3xl font-semibold tracking-tight text-base-content">{{ $user->name }}</h1>
                                    <p class="text-base text-base-content/72">{{ $user->vorname }} {{ $user->nachname }}</p>
                                </div>

                                <div class="flex flex-wrap items-center gap-3 text-sm text-base-content/78">
                                    <x-badge value="{{ $memberRole }}" class="badge-primary" icon="o-identification" />

                                    <span class="inline-flex items-center gap-2 rounded-full bg-base-200/80 px-3 py-1">
                                        @if ($isOnline)
                                            <span class="relative flex h-3 w-3">
                                                <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-success opacity-75"></span>
                                                <span class="relative inline-flex h-3 w-3 rounded-full bg-success"></span>
                                            </span>
                                        @else
                                            <span class="inline-flex h-3 w-3 rounded-full bg-base-content/35"></span>
                                        @endif
                                        {{ $activityLabel }}
                                    </span>
                                </div>
                            </div>

                            <p class="text-sm leading-relaxed text-base-content/72">
                                {{ $isOwnProfile ? 'Hier siehst du deinen aktuellen Vereinsstand inklusive Baxx, Badges und hinterlegter Lieblingsdaten.' : 'Dieses Profil bündelt freigegebene Angaben, Vereinsaktivität und gesammelte Errungenschaften des Mitglieds.' }}
                            </p>
                        </div>
                    </x-ui.panel>

                    @if(count($badges) > 0)
                        <x-ui.panel title="Errungenschaften" description="Freigeschaltete Badges und besondere Vereinsmeilensteine dieses Profils.">
                            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
                                @foreach($badges as $badge)
                                    <div class="flex flex-col items-center rounded-3xl bg-base-200/70 p-4 text-center">
                                        <button
                                            type="button"
                                            class="mb-3 h-20 w-20 transition-opacity hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-primary/40"
                                            onclick="openBadgeModal(@js($badge['name']), @js($badge['description']), @js($badge['image']))"
                                            aria-label="Badge {{ $badge['name'] }} anzeigen"
                                        >
                                            <img loading="lazy" src="{{ $badge['image'] }}" alt="{{ $badge['name'] }}" class="w-full">
                                        </button>
                                        <h3 class="text-sm font-semibold text-base-content">{{ $badge['name'] }}</h3>
                                        <p class="mt-1 text-xs leading-relaxed text-base-content/72">{{ $badge['description'] }}</p>
                                    </div>
                                @endforeach
                            </div>
                        </x-ui.panel>
                    @endif

                    @if($canViewDetails)
                        <x-ui.panel title="Kontaktdetails" description="Sichtbare Kontakt- und Mitgliedsdaten für berechtigte Rollen.">
                            <div class="space-y-5">
                                <div class="space-y-3">
                                    <h3 class="text-sm font-semibold uppercase tracking-[0.24em] text-base-content/45">Kontakt</h3>
                                    <div class="space-y-2 text-sm text-base-content">
                                        <div class="flex items-center gap-2">
                                            <x-icon name="o-envelope" class="h-5 w-5 text-base-content/60" />
                                            <span>{{ $user->email }}</span>
                                        </div>
                                        @if($user->telefon)
                                            <div class="flex items-center gap-2">
                                                <x-icon name="o-phone" class="h-5 w-5 text-base-content/60" />
                                                <span>{{ $user->telefon }}</span>
                                            </div>
                                        @endif
                                    </div>
                                </div>

                                <div class="grid gap-5 md:grid-cols-2">
                                    <div class="rounded-3xl bg-base-200/70 p-4">
                                        <h3 class="mb-2 text-sm font-semibold uppercase tracking-[0.24em] text-base-content/45">Adresse</h3>
                                        <address class="not-italic text-sm leading-relaxed text-base-content">
                                            @if($user->strasse && $user->hausnummer)
                                                {{ $user->strasse }} {{ $user->hausnummer }}<br>
                                            @endif
                                            @if($user->plz && $user->stadt)
                                                {{ $user->plz }} {{ $user->stadt }}<br>
                                            @endif
                                            @if($user->land)
                                                {{ $user->land }}
                                            @else
                                                Keine Adresse hinterlegt
                                            @endif
                                        </address>
                                    </div>

                                    <div class="rounded-3xl bg-base-200/70 p-4">
                                        <h3 class="mb-2 text-sm font-semibold uppercase tracking-[0.24em] text-base-content/45">Mitgliedsbeitrag</h3>
                                        <p class="text-sm text-base-content">{{ $user->mitgliedsbeitrag ?? 'Nicht hinterlegt' }}</p>
                                    </div>
                                </div>
                            </div>
                        </x-ui.panel>
                    @endif
                </div>

                <div class="space-y-6">
                    <x-ui.panel title="Vereinsaktivität" description="Baxx, erledigte Challenges und die bisher gesammelten Punkte nach Bereich.">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="rounded-3xl bg-base-200/70 p-5 shadow-sm">
                                <h3 class="text-sm font-medium text-base-content/72">Baxx</h3>
                                <p class="mt-3 text-4xl font-semibold tracking-tight text-primary">{{ $userPoints }}</p>
                                <p class="mt-1 text-sm text-base-content/60">Gesammelte Vereinspunkte</p>
                            </div>

                            <div class="rounded-3xl bg-base-200/70 p-5 shadow-sm">
                                <h3 class="text-sm font-medium text-base-content/72">Erledigte Challenges</h3>
                                <p class="mt-3 text-4xl font-semibold tracking-tight text-base-content">{{ $completedTasks }}</p>
                                <p class="mt-1 text-sm text-base-content/60">Abgeschlossene Aufgaben</p>
                            </div>
                        </div>

                        <div class="mt-6 space-y-3">
                            <h3 class="text-sm font-semibold uppercase tracking-[0.24em] text-base-content/45">Punkte nach Bereich</h3>

                            @if(! empty($categoryPoints))
                                <dl class="grid gap-3 sm:grid-cols-2">
                                    @foreach($categoryPoints as $category => $points)
                                        <div class="rounded-3xl bg-base-200/70 px-4 py-3">
                                            <dt class="text-sm text-base-content/72">{{ $category }}</dt>
                                            <dd class="mt-1 text-lg font-semibold text-base-content">{{ $points }} Baxx</dd>
                                        </div>
                                    @endforeach
                                </dl>
                            @else
                                <p class="rounded-3xl bg-base-200/70 px-4 py-3 text-sm text-base-content/72">Für dieses Profil sind noch keine bereichsspezifischen Punkte erfasst.</p>
                            @endif
                        </div>
                    </x-ui.panel>

                    <x-ui.panel title="Maddrax-Leidenschaft" description="Persönliche Lieblingsdaten, Einstiege und Sammelschwerpunkte rund um das Maddraxiversum.">
                        @if($maddraxLeidenschaft->isNotEmpty())
                            <div class="grid gap-4 sm:grid-cols-2">
                                @foreach($maddraxLeidenschaft as $entry)
                                    <div class="rounded-3xl bg-base-200/70 p-4 shadow-sm">
                                        <h3 class="text-sm font-semibold uppercase tracking-[0.24em] text-base-content/45">{{ $entry['label'] }}</h3>
                                        <p class="mt-3 text-base leading-relaxed text-base-content">{{ $entry['value'] }}</p>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <p class="rounded-3xl bg-base-200/70 px-4 py-3 text-sm text-base-content/72">Für dieses Profil wurden noch keine persönlichen Maddrax-Favoriten hinterlegt.</p>
                        @endif
                    </x-ui.panel>
                </div>
            </div>
        </div>

        <!-- Profile Photo Modal -->
        <x-mary-modal id="profilePhotoModal" box-class="max-w-lg" without-trap-focus>
            <div class="flex justify-center">
                <img loading="lazy" id="profilePhotoModalImage" src="" alt="Profilbild" class="max-w-full max-h-[80vh] object-contain">
            </div>
            <x-slot:actions>
                <x-button label="Schließen" class="btn-primary" onclick="document.getElementById('profilePhotoModal').close()" />
            </x-slot:actions>
        </x-mary-modal>

        <!-- Badge Modal -->
        <x-mary-modal id="badgeModal" box-class="max-w-lg" without-trap-focus>
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
