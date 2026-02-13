<x-app-layout>
    <x-member-page>
        {{-- Flash Messages --}}
        @if(session('status'))
            <x-alert icon="o-check-circle" class="alert-success mb-4" dismissible>
                {{ session('status') }}
            </x-alert>
        @endif

        {{-- Dashboard Cards Grid --}}
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6 mb-8 grid-flow-row-dense" aria-label="Überblick wichtiger Community-Kennzahlen">
            {{-- Persönliche offene Challenges Card --}}
            <x-bento-card href="{{ route('todos.index') }}" title="Offene Challenges" sr-text="Meine offenen Challenges: {{ $openTodos }}">
                <p class="text-sm text-base-content mb-2">Angenommene, noch nicht abgeschlossene Challenges</p>
                <div class="text-4xl font-bold mt-auto" aria-live="polite">
                    {{ $openTodos }}
                </div>
            </x-bento-card>

            {{-- Baxx Card --}}
            <x-bento-card title="Meine Baxx" sr-text="Meine Baxx: {{ $userPoints }}">
                <p class="text-sm text-base-content mb-2">Aktueller Punktestand für deine Aktivitäten</p>
                <div class="text-4xl font-bold mt-auto" aria-live="polite">
                    {{ $userPoints }}
                </div>
            </x-bento-card>

            {{-- Matches in Tauschbörse Card --}}
            <x-bento-card href="{{ route('romantausch.index') }}" title="Matches in Tauschbörse" sr-text="Meine Matches in der Tauschbörse: {{ $romantauschMatches }}">
                <p class="text-sm text-base-content mb-2">Offene Treffer aus Angeboten und Gesuchen in der Romantauschbörse</p>
                <div class="text-4xl font-bold mt-auto" aria-live="polite">
                    {{ $romantauschMatches }}
                </div>
            </x-bento-card>

            {{-- Angebote in Tauschbörse Card --}}
            <x-bento-card href="{{ route('romantausch.index') }}" title="Angebote in der Tauschbörse" sr-text="Meine Angebote in der Tauschbörse: {{ $romantauschOffers }}">
                <p class="text-sm text-base-content mb-2">Aktive Angebote, die du für die Community bereitgestellt hast</p>
                <div class="text-4xl font-bold mt-auto" aria-live="polite">
                    {{ $romantauschOffers }}
                </div>
            </x-bento-card>

            {{-- Meine Rezensionen Card --}}
            <x-bento-card href="{{ route('reviews.index') }}" title="Meine Rezensionen" sr-text="Meine Rezensionen: {{ $myReviews }}">
                <p class="text-sm text-base-content mb-2">Überblick deiner veröffentlichten Rezensionen</p>
                <div class="text-4xl font-bold" aria-live="polite">
                    {{ $myReviews }}
                </div>
            </x-bento-card>

            {{-- Fanfiction Card --}}
            <x-bento-card href="{{ route('fanfiction.index') }}" title="Fanfiction" sr-text="Fanfiction: {{ $fanfictionCount ?? 0 }}">
                <p class="text-sm text-base-content mb-2">Kurzgeschichten aus dem MADDRAX-Universum</p>
                <div class="text-4xl font-bold" aria-live="polite">
                    {{ $fanfictionCount ?? 0 }}
                </div>
            </x-bento-card>
        </div>

        {{-- Anwärter-Liste für Kassenwart, Vorstand und Admin --}}
        @if($anwaerter->isNotEmpty())
            @php
                $anwaerterHeaders = [
                    ['key' => 'name', 'label' => 'Name'],
                    ['key' => 'email', 'label' => 'E-Mail'],
                    ['key' => 'mitgliedsbeitrag', 'label' => 'Beitrag'],
                    ['key' => 'actions', 'label' => 'Aktion', 'class' => 'text-center'],
                ];
            @endphp

            <x-card title="Mitgliedsanträge" class="mb-8" shadow>
                <div x-data="{ rejectUrl: '' }">
                    <x-table :headers="$anwaerterHeaders" :rows="$anwaerter" striped>
                        @scope('cell_name', $person)
                            <a href="{{ route('profile.view', $person->id) }}" class="text-primary hover:underline">{{ $person->name }}</a>
                        @endscope

                        @scope('cell_actions', $person)
                            <div class="flex justify-center gap-2">
                                <form action="{{ route('anwaerter.approve', $person->id) }}" method="POST">
                                    @csrf
                                    <x-button type="submit" label="Genehmigen" class="btn-success btn-sm" icon="o-check" />
                                </form>
                                <x-button
                                    label="Ablehnen"
                                    class="btn-error btn-sm"
                                    icon="o-x-mark"
                                    @click="rejectUrl = '{{ route('anwaerter.reject', $person->id) }}'; document.getElementById('reject-anwaerter-modal').showModal()"
                                />
                            </div>
                        @endscope
                    </x-table>

                    {{-- Ablehnungs-Bestätigungsdialog --}}
                    <x-mary-modal id="reject-anwaerter-modal" title="Antrag ablehnen" separator>
                        <p class="text-base-content">
                            Möchtest du diesen Mitgliedsantrag wirklich ablehnen? Der Nutzer wird dadurch gelöscht.
                        </p>

                        <x-slot:actions>
                            <x-button label="Abbrechen" @click="document.getElementById('reject-anwaerter-modal').close()" />
                            <form :action="rejectUrl" method="POST" class="inline">
                                @csrf
                                <x-button type="submit" label="Ablehnen" class="btn-error" icon="o-x-mark" />
                            </form>
                        </x-slot:actions>
                    </x-mary-modal>
                </div>
            </x-card>
        @endif

        {{-- Aktivitäten Card --}}
        <x-card class="mb-8" shadow>
            <x-slot:title>
                <div class="flex items-start justify-between gap-3 w-full">
                    <div>
                        <h2 class="text-xl font-semibold text-primary">Aktivitäten</h2>
                        <p class="text-sm text-base-content">Neueste Rezensionen, Kommentare & Aktionen im Überblick.</p>
                    </div>
                    <x-badge value="Live-Feed" class="badge-primary badge-outline" />
                </div>
            </x-slot:title>

            <ul class="space-y-3" role="list">
                @forelse($activities as $activity)
                    @php
                        $subject = $activity->subject;
                        $missingSubjectMessages = [
                            \App\Models\ReviewComment::class => 'Kommentar – Bezug nicht mehr verfügbar',
                        ];
                        $missingSubjectMessage = $missingSubjectMessages[$activity->subject_type]
                            ?? 'Gelöschter Eintrag – nicht mehr verfügbar';
                        $isFantreffenRegistration = $activity->subject_type === \App\Models\FantreffenAnmeldung::class;
                        $activityUser = $activity->user;
                        $showProfileLink = !$isFantreffenRegistration && $activityUser;
                        $typeLabels = [
                            \App\Models\Review::class => 'Rezension',
                            \App\Models\ReviewComment::class => 'Kommentar',
                            \App\Models\BookOffer::class => 'Tausch',
                            \App\Models\BookRequest::class => 'Gesuch',
                            \App\Models\AdminMessage::class => 'Hinweis',
                            \App\Models\FantreffenAnmeldung::class => 'Fantreffen',
                            \App\Models\Todo::class => 'Challenge',
                            \App\Models\User::class => 'Mitglied',
                        ];
                        $activityLabel = $typeLabels[$activity->subject_type] ?? 'Aktivität';
                    @endphp
                    <li class="relative rounded-lg border border-base-200 bg-base-200/50 px-4 py-3 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md" aria-label="Aktivität am {{ $activity->created_at->format('d.m.Y H:i') }}">
                        <div class="flex flex-wrap items-center gap-2 text-xs font-semibold text-base-content">
                            <span class="inline-flex items-center gap-1 rounded-full bg-base-100 px-2 py-1 text-primary shadow-sm ring-1 ring-primary/20">
                                <span class="sr-only">Zeitpunkt</span>
                                <x-icon name="o-clock" class="w-3.5 h-3.5" />
                                {{ $activity->created_at->format('d.m.Y H:i') }}
                            </span>
                            <x-badge :value="$activityLabel" class="badge-primary badge-outline" />
                            @if($showProfileLink)
                                <span class="inline-flex items-center gap-1 rounded-full bg-base-100 px-2 py-1 ring-1 ring-base-200">
                                    <span class="sr-only">von Nutzer</span>
                                    <x-icon name="o-user" class="w-3.5 h-3.5" />
                                    <a href="{{ route('profile.view', $activityUser->id) }}" class="font-semibold text-primary hover:underline">{{ $activityUser->name }}</a>
                                </span>
                            @elseif(!$isFantreffenRegistration)
                                <span class="inline-flex items-center gap-1 rounded-full bg-base-100 px-2 py-1 ring-1 ring-base-200">
                                    <x-icon name="o-user" class="w-3.5 h-3.5" />
                                    Unbekannter Nutzer
                                </span>
                            @endif
                        </div>

                        <div class="mt-2 space-y-1 text-sm leading-relaxed">
                            @if(!$subject)
                                <span class="text-base-content italic">
                                    {{ $missingSubjectMessage }}
                                </span>
                            @elseif($isFantreffenRegistration)
                                @php
                                    $registrantName = $subject?->vorname
                                        ?? $activityUser?->vorname
                                        ?? $activityUser?->name
                                        ?? 'Teilnehmer';
                                @endphp
                                <span>{{ $registrantName }} hat sich zum Fantreffen in Coellen angemeldet</span>
                            @elseif($activity->subject_type === \App\Models\Review::class)
                                @php
                                    $reviewPreview = \App\Support\PreviewText::make($subject->content ?? '', 160);
                                @endphp
                                <div class="space-y-1">
                                    <a href="{{ route('reviews.show', $subject->book_id) }}" class="font-semibold text-info hover:underline">Neue Rezension: {{ $subject->title }}</a>
                                    @if($reviewPreview->isNotEmpty())
                                        <p class="text-sm text-base-content" aria-label="Auszug aus der Rezension">„{{ $reviewPreview }}"</p>
                                    @endif
                                </div>
                            @elseif($activity->subject_type === \App\Models\BookOffer::class)
                                <div class="space-y-1">
                                    <a href="{{ route('romantausch.index') }}" class="font-semibold text-info hover:underline">Neues Angebot: {{ $subject->book_title }}</a>
                                    <p class="text-sm text-base-content">Entdecke neue Tauschangebote aus der Community.</p>
                                </div>
                            @elseif($activity->subject_type === \App\Models\BookRequest::class)
                                <div class="space-y-1">
                                    <a href="{{ route('romantausch.index') }}" class="font-semibold text-info hover:underline">Neues Gesuch: {{ $subject->book_title }}</a>
                                    <p class="text-sm text-base-content">Vielleicht hast du genau das passende Heft zum Teilen.</p>
                                </div>
                            @elseif($activity->subject_type === \App\Models\ReviewComment::class)
                                @php
                                    $review = $subject?->review;
                                    $commentPreview = \App\Support\PreviewText::make($subject?->content ?? '', 140);
                                @endphp
                                @if($review)
                                    <div class="space-y-1">
                                        <span>Kommentar zu <a href="{{ route('reviews.show', $review->book_id) }}" class="text-info hover:underline">{{ $review->title }}</a> von <a href="{{ route('profile.view', $activity->user->id) }}" class="text-primary hover:underline">{{ $activity->user->name }}</a></span>
                                        @if($commentPreview->isNotEmpty())
                                            <p class="text-sm text-base-content" aria-label="Auszug aus dem Kommentar">„{{ $commentPreview }}"</p>
                                        @endif
                                    </div>
                                @else
                                    <span class="text-base-content italic">
                                        {{ $missingSubjectMessage }}
                                    </span>
                                @endif
                            @elseif($activity->subject_type === \App\Models\AdminMessage::class)
                                <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                                    <span class="font-medium">{{ $subject->message }}</span>
                                    @if(auth()->user()->hasRole(\App\Enums\Role::Admin))
                                        <form method="POST" action="{{ route('admin.messages.destroy', $subject) }}" class="text-right">
                                            @csrf
                                            @method('DELETE')
                                            <x-button type="submit" label="Löschen" class="btn-ghost btn-xs text-error" onclick="return confirm('Nachricht löschen?')" />
                                        </form>
                                    @endif
                                </div>
                            @elseif($activity->subject_type === \App\Models\Todo::class && $activity->action === 'accepted')
                                <span>hat die Challenge <a href="{{ route('todos.show', $subject->id) }}" class="text-info hover:underline">{{ $subject->title }}</a> angenommen</span>
                            @elseif($activity->subject_type === \App\Models\Todo::class && $activity->action === 'completed')
                                <span>hat die Challenge <a href="{{ route('todos.show', $subject->id) }}" class="text-info hover:underline">{{ $subject->title }}</a> erfolgreich abgeschlossen</span>
                            @elseif($activity->subject_type === \App\Models\User::class && $activity->action === 'member_approved')
                                <span>Wir begrüßen unser neues Mitglied <a href="{{ route('profile.view', $subject->id) }}" class="text-primary hover:underline">{{ $subject->name }}</a></span>
                            @endif
                        </div>
                    </li>
                @empty
                    <li class="rounded-lg border border-dashed border-base-300 px-4 py-6 text-center text-base-content">
                        <x-icon name="o-inbox" class="w-12 h-12 mx-auto mb-2 opacity-30" />
                        Keine Aktivitäten vorhanden.
                    </li>
                @endforelse
            </ul>
        </x-card>

        {{-- TOP 3 Mitglieder --}}
        <x-card title="TOP 3 Baxx-Sammler" class="mb-8" shadow>
            @php
                $topUsersCollection = collect($topUsers)->values();
                $topUsersSummary = $topUsersCollection->isNotEmpty()
                    ? 'Top ' . $topUsersCollection->count() . ' Baxx-Sammler: '
                        . $topUsersCollection->map(function ($user, $index) {
                            $position = $index + 1;
                            $points = number_format((int) $user['points'], 0, ',', '.');
                            return $position . '. ' . $user['name'] . ' (' . $points . ' Baxx)';
                        })->implode(', ')
                    : null;
                $topUsersPayload = $topUsersCollection->map(function ($user) {
                    return [
                        'id' => $user['id'],
                        'name' => $user['name'],
                        'points' => (int) $user['points'],
                    ];
                })->toArray();
            @endphp

            @if($topUsersCollection->isNotEmpty())
                <div
                    class="flex flex-col md:flex-row items-center md:items-start justify-center gap-6 md:gap-10"
                    data-dashboard-top-users='{{ json_encode($topUsersPayload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) }}'
                    role="list"
                    aria-label="{{ $topUsersSummary }}"
                >
                    <p class="sr-only" data-dashboard-top-summary="true" aria-live="polite">{{ $topUsersSummary }}</p>
                    @foreach($topUsersCollection as $index => $topUser)
                        <a href="{{ route('profile.view', $topUser['id']) }}" class="flex flex-col items-center group" data-dashboard-top-user-item role="listitem">
                            @if($index === 0)
                                {{-- Gold Medaille für Platz 1 --}}
                                <div class="relative mb-2">
                                    <div class="absolute -top-3 -right-2 w-8 h-8 bg-warning rounded-full flex items-center justify-center text-warning-content font-bold shadow-md transform group-hover:scale-110 transition-transform">1</div>
                                    <div class="h-20 w-20 rounded-full overflow-hidden border-4 border-warning shadow-lg group-hover:shadow-xl transition-shadow">
                                        <img loading="lazy" src="{{ $topUser['profile_photo_url'] }}" alt="{{ $topUser['name'] }}" class="h-full w-full object-cover">
                                    </div>
                                </div>
                            @elseif($index === 1)
                                {{-- Silber Medaille für Platz 2 --}}
                                <div class="relative mb-2">
                                    <div class="absolute -top-3 -right-2 w-8 h-8 bg-base-300 rounded-full flex items-center justify-center text-base-content font-bold shadow-md transform group-hover:scale-110 transition-transform">2</div>
                                    <div class="h-20 w-20 rounded-full overflow-hidden border-4 border-base-300 shadow-lg group-hover:shadow-xl transition-shadow">
                                        <img loading="lazy" src="{{ $topUser['profile_photo_url'] }}" alt="{{ $topUser['name'] }}" class="h-full w-full object-cover">
                                    </div>
                                </div>
                            @else
                                {{-- Bronze Medaille für Platz 3 --}}
                                <div class="relative mb-2">
                                    <div class="absolute -top-3 -right-2 w-8 h-8 bg-accent rounded-full flex items-center justify-center text-accent-content font-bold shadow-md transform group-hover:scale-110 transition-transform">3</div>
                                    <div class="h-20 w-20 rounded-full overflow-hidden border-4 border-accent shadow-lg group-hover:shadow-xl transition-shadow">
                                        <img loading="lazy" src="{{ $topUser['profile_photo_url'] }}" alt="{{ $topUser['name'] }}" class="h-full w-full object-cover">
                                    </div>
                                </div>
                            @endif
                            
                            <h3 class="text-lg font-semibold mt-2 group-hover:text-primary transition-colors">{{ $topUser['name'] }}</h3>
                            <p class="font-bold text-xl text-primary">{{ $topUser['points'] }}</p>
                            <p class="text-xs text-base-content">Baxx</p>
                        </a>
                    @endforeach
                </div>
            @else
                <div class="text-center py-8 text-base-content">
                    <x-icon name="o-trophy" class="w-12 h-12 mx-auto mb-2 opacity-30" />
                    Noch keine Baxx vergeben.
                </div>
            @endif
        </x-card>

        {{-- Zu verifizierende Aufgaben Card (nur für Admin) --}}
        @if(in_array($userRole, $allowedRoles) && $pendingVerification > 0)
            <a href="{{ route('todos.index') }}?filter=pending" class="block">
                <x-card class="mb-8 hover:shadow-lg transition-shadow" shadow>
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-lg font-semibold text-primary mb-1">Auf Verifizierung wartende Challenges</h2>
                            <p class="text-base-content text-sm">Es gibt {{ $pendingVerification }} Challenge(s), die auf Bestätigung warten</p>
                        </div>
                        <div class="flex items-center">
                            <div class="text-3xl font-bold text-primary mr-4">{{ $pendingVerification }}</div>
                            <x-icon name="o-chevron-right" class="w-6 h-6 text-base-content" />
                        </div>
                    </div>
                </x-card>
            </a>
        @endif
    </x-member-page>
</x-app-layout>
