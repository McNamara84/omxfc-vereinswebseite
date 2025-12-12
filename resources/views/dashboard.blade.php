<x-app-layout>
    <x-member-page>
            @if(session('status'))
                <div
                    class="mb-4 p-4 bg-green-100 dark:bg-green-800 border border-green-400 dark:border-green-600 text-green-800 dark:text-green-100 rounded">
                    {{ session('status') }}
                </div>
            @endif
            <!-- Dashboard Cards Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6 mb-8 grid-flow-row-dense" aria-label="Überblick wichtiger Community-Kennzahlen">
                <!-- Persönliche offene Challenges Card -->
                <x-bento-card href="{{ route('todos.index') }}" title="Offene Challenges" sr-text="Meine offenen Challenges: {{ $openTodos }}">
                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">Angenommene, noch nicht abgeschlossene Challenges</p>
                    <div class="text-4xl font-bold text-gray-800 dark:text-gray-200 mt-auto" aria-live="polite">
                        {{ $openTodos }}
                    </div>
                </x-bento-card>
                <!-- Baxx Card -->
                <x-bento-card title="Meine Baxx" sr-text="Meine Baxx: {{ $userPoints }}">
                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">Aktueller Punktestand für deine Aktivitäten</p>
                    <div class="text-4xl font-bold text-gray-800 dark:text-gray-200 mt-auto" aria-live="polite">
                        {{ $userPoints }}
                    </div>
                </x-bento-card>
                <!-- Matches in Tauschbörse Card -->
                <x-bento-card href="{{ route('romantausch.index') }}" title="Matches in Tauschbörse" sr-text="Meine Matches in der Tauschbörse: {{ $romantauschMatches }}">
                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">Offene Treffer aus Angeboten und Gesuchen in der Romantauschbörse</p>
                    <div class="text-4xl font-bold text-gray-800 dark:text-gray-200 mt-auto" aria-live="polite">
                        {{ $romantauschMatches }}
                    </div>
                </x-bento-card>
                <!-- Angebote in Tauschbörse Card -->
                <x-bento-card href="{{ route('romantausch.index') }}" title="Angebote in der Tauschbörse" sr-text="Meine Angebote in der Tauschbörse: {{ $romantauschOffers }}">
                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">Aktive Angebote, die du für die Community bereitgestellt hast</p>
                    <div class="text-4xl font-bold text-gray-800 dark:text-gray-200 mt-auto" aria-live="polite">
                        {{ $romantauschOffers }}
                    </div>
                </x-bento-card>
                <!-- Meine Rezensionen Card -->
                <x-bento-card href="{{ route('reviews.index') }}" title="Meine Rezensionen" sr-text="Meine Rezensionen: {{ $myReviews }}">
                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">Überblick deiner veröffentlichten Rezensionen</p>
                    <div class="text-4xl font-bold text-gray-800 dark:text-gray-200" aria-live="polite">
                        {{ $myReviews }}
                    </div>
                </x-bento-card>
                <!-- Meine Kommentare Card -->
                <x-bento-card title="Meine Kommentare" sr-text="Meine Kommentare: {{ $myReviewComments }}">
                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">Kommentare, die du zu Rezensionen verfasst hast</p>
                    <div class="text-4xl font-bold text-gray-800 dark:text-gray-200" aria-live="polite">
                        {{ $myReviewComments }}
                    </div>
                </x-bento-card>
            </div>
            <!-- Anwärter-Liste für Kassenwart, Vorstand und Admin -->
            @if($anwaerter->isNotEmpty())
                <div class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6 mb-8">
                    <h2 class="text-xl font-semibold text-[#8B0116] dark:text-[#FCA5A5] mb-4">Mitgliedsanträge</h2>
                    <!-- Desktop-Ansicht (versteckt auf Mobilgeräten) -->
                    <div class="hidden md:block overflow-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead>
                                <tr>
                                    <th class="px-4 py-2 text-left text-gray-700 dark:text-gray-300">Name</th>
                                    <th class="px-4 py-2 text-left text-gray-700 dark:text-gray-300">E-Mail</th>
                                    <th class="px-4 py-2 text-left text-gray-700 dark:text-gray-300">Beitrag</th>
                                    <th class="px-4 py-2 text-center text-gray-700 dark:text-gray-300">Aktion</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($anwaerter as $person)
                                    <tr>
                                        <td class="px-4 py-2 text-gray-800 dark:text-gray-200"><a href="{{ route('profile.view', $person->id) }}" class="text-[#8B0116] hover:underline">{{ $person->name }}</a></td>
                                        <td class="px-4 py-2 text-gray-800 dark:text-gray-200">{{ $person->email }}</td>
                                        <td class="px-4 py-2 text-gray-800 dark:text-gray-200">
                                            {{ $person->mitgliedsbeitrag }}</td>
                                        <td class="px-4 py-2 flex justify-center gap-2">
                                            <form action="{{ route('anwaerter.approve', $person->id) }}" method="POST">
                                                @csrf
                                                <button type="submit"
                                                    class="bg-green-500 hover:bg-green-600 text-white px-3 py-1 rounded">
                                                    Genehmigen
                                                </button>
                                            </form>
                                            <form action="{{ route('anwaerter.reject', $person->id) }}" method="POST"
                                                onsubmit="return confirm('Antrag wirklich ablehnen und Nutzer löschen?');">
                                                @csrf
                                                <button type="submit"
                                                    class="bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded">
                                                    Ablehnen
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <!-- Mobile-Ansicht (nur auf Mobilgeräten sichtbar) -->
                    <div class="md:hidden space-y-6">
                        @foreach($anwaerter as $person)
                            <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg shadow">
                                <div class="mb-2">
                                    <span class="font-semibold text-gray-700 dark:text-gray-300">Name:</span>
                                    <span class="block mt-1 text-gray-800 dark:text-gray-200"><a href="{{ route('profile.view', $person->id) }}" class="text-[#8B0116] hover:underline">{{ $person->name }}</a></span>
                                </div>
                                <div class="mb-2">
                                    <span class="font-semibold text-gray-700 dark:text-gray-300">E-Mail:</span>
                                    <span class="block mt-1 break-words text-gray-800 dark:text-gray-200">{{ $person->email }}</span>
                                </div>
                                <div class="mb-4">
                                    <span class="font-semibold text-gray-700 dark:text-gray-300">Beitrag:</span>
                                    <span class="block mt-1 text-gray-800 dark:text-gray-200">{{ $person->mitgliedsbeitrag }}</span>
                                </div>
                                <div class="flex gap-2 mt-4">
                                    <form action="{{ route('anwaerter.approve', $person->id) }}" method="POST"
                                        class="w-1/2">
                                        @csrf
                                        <button type="submit"
                                            class="w-full bg-green-500 hover:bg-green-600 text-white py-2 px-4 rounded">
                                            Genehmigen
                                        </button>
                                    </form>
                                    <form action="{{ route('anwaerter.reject', $person->id) }}" method="POST" class="w-1/2"
                                        onsubmit="return confirm('Antrag wirklich ablehnen und Nutzer löschen?');">
                                        @csrf
                                        <button type="submit"
                                            class="w-full bg-red-500 hover:bg-red-600 text-white py-2 px-4 rounded">
                                            Ablehnen
                                        </button>
                                    </form>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
            <!-- Aktivitäten Card -->
            <div class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6 mb-8">
                <div class="flex items-start justify-between gap-3 mb-4">
                    <div>
                        <h2 class="text-xl font-semibold text-[#8B0116] dark:text-[#FCA5A5]">Aktivitäten</h2>
                        <p class="text-sm text-gray-600 dark:text-gray-400">Neueste Rezensionen, Kommentare & Aktionen im Überblick.</p>
                    </div>
                    <span class="inline-flex items-center gap-1 rounded-full bg-[#8B0116]/10 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-[#8B0116] dark:bg-[#FCA5A5]/10 dark:text-[#FCA5A5]">Live-Feed</span>
                </div>
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
                        <li class="relative rounded-lg border border-gray-100 bg-gray-50 px-4 py-3 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md dark:border-gray-700/60 dark:bg-gray-900/40" aria-label="Aktivität am {{ $activity->created_at->format('d.m.Y H:i') }}">
                            <div class="flex flex-wrap items-center gap-2 text-xs font-semibold text-gray-500 dark:text-gray-400">
                                <span class="inline-flex items-center gap-1 rounded-full bg-white px-2 py-1 text-[#8B0116] shadow-sm ring-1 ring-[#8B0116]/20 dark:bg-gray-800">
                                    <span class="sr-only">Zeitpunkt</span>
                                    <svg class="h-3.5 w-3.5" aria-hidden="true" viewBox="0 0 20 20" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16Zm.75-12.25a.75.75 0 00-1.5 0v4.5c0 .414.336.75.75.75h3.5a.75.75 0 000-1.5H10.75v-3.75Z" clip-rule="evenodd" />
                                    </svg>
                                    {{ $activity->created_at->format('d.m.Y H:i') }}
                                </span>
                                <span class="inline-flex items-center gap-1 rounded-full bg-[#8B0116]/10 px-2 py-1 text-[#8B0116] ring-1 ring-[#8B0116]/20 dark:bg-[#FCA5A5]/10 dark:text-[#FCA5A5]">
                                    <span class="sr-only">Aktivitätstyp:</span>
                                    {{ $activityLabel }}
                                </span>
                                @if($showProfileLink)
                                    <span class="inline-flex items-center gap-1 rounded-full bg-white px-2 py-1 text-gray-700 ring-1 ring-gray-200 dark:bg-gray-800 dark:text-gray-200 dark:ring-gray-700">
                                        <span class="sr-only">von Nutzer</span>
                                        <svg class="h-3.5 w-3.5" aria-hidden="true" viewBox="0 0 20 20" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                                            <path fill-rule="evenodd" d="M10 2a8 8 0 100 16 8 8 0 000-16Zm0 4a2 2 0 110 4 2 2 0 010-4zm-4 8a4 4 0 118 0H6z" clip-rule="evenodd" />
                                        </svg>
                                        <a href="{{ route('profile.view', $activityUser->id) }}" class="font-semibold text-[#8B0116] hover:underline dark:text-[#FCA5A5]">{{ $activityUser->name }}</a>
                                    </span>
                                @elseif(!$isFantreffenRegistration)
                                    <span class="inline-flex items-center gap-1 rounded-full bg-white px-2 py-1 text-gray-700 ring-1 ring-gray-200 dark:bg-gray-800 dark:text-gray-200 dark:ring-gray-700">
                                        <svg class="h-3.5 w-3.5" aria-hidden="true" viewBox="0 0 20 20" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                                            <path fill-rule="evenodd" d="M10 2a8 8 0 100 16 8 8 0 000-16Zm0 4a2 2 0 110 4 2 2 0 010-4zm-4 8a4 4 0 118 0H6z" clip-rule="evenodd" />
                                        </svg>
                                        Unbekannter Nutzer
                                    </span>
                                @endif
                            </div>

                            <div class="mt-2 space-y-1 text-sm leading-relaxed text-gray-800 dark:text-gray-200">
                                @if(!$subject)
                                    <span class="text-gray-500 dark:text-gray-300 italic">
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
                                        <a href="{{ route('reviews.show', $subject->book_id) }}" class="font-semibold text-blue-600 dark:text-blue-400 hover:underline">Neue Rezension: {{ $subject->title }}</a>
                                        @if($reviewPreview->isNotEmpty())
                                            <p class="text-sm text-gray-600 dark:text-gray-300" aria-label="Auszug aus der Rezension">„{{ $reviewPreview }}“</p>
                                        @endif
                                    </div>
                                @elseif($activity->subject_type === \App\Models\BookOffer::class)
                                    <div class="space-y-1">
                                        <a href="{{ route('romantausch.index') }}" class="font-semibold text-blue-600 dark:text-blue-400 hover:underline">Neues Angebot: {{ $subject->book_title }}</a>
                                        <p class="text-sm text-gray-600 dark:text-gray-300">Entdecke neue Tauschangebote aus der Community.</p>
                                    </div>
                                @elseif($activity->subject_type === \App\Models\BookRequest::class)
                                    <div class="space-y-1">
                                        <a href="{{ route('romantausch.index') }}" class="font-semibold text-blue-600 dark:text-blue-400 hover:underline">Neues Gesuch: {{ $subject->book_title }}</a>
                                        <p class="text-sm text-gray-600 dark:text-gray-300">Vielleicht hast du genau das passende Heft zum Teilen.</p>
                                    </div>
                                @elseif($activity->subject_type === \App\Models\ReviewComment::class)
                                    @php
                                        $review = $subject?->review;
                                        $commentPreview = \App\Support\PreviewText::make($subject?->content ?? '', 140);
                                    @endphp
                                    @if($review)
                                        <div class="space-y-1">
                                            <span>Kommentar zu <a href="{{ route('reviews.show', $review->book_id) }}" class="text-blue-600 dark:text-blue-400 hover:underline">{{ $review->title }}</a> von <a href="{{ route('profile.view', $activity->user->id) }}" class="text-[#8B0116] hover:underline dark:text-[#FCA5A5]">{{ $activity->user->name }}</a></span>
                                            @if($commentPreview->isNotEmpty())
                                                <p class="text-sm text-gray-600 dark:text-gray-300" aria-label="Auszug aus dem Kommentar">„{{ $commentPreview }}“</p>
                                            @endif
                                        </div>
                                    @else
                                        <span class="text-gray-500 dark:text-gray-300 italic">
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
                                                <button class="text-xs font-semibold text-red-500 hover:text-red-600" onclick="return confirm('Nachricht löschen?')">Löschen</button>
                                            </form>
                                        @endif
                                    </div>
                                @elseif($activity->subject_type === \App\Models\Todo::class && $activity->action === 'accepted')
                                    <span>hat die Challenge <a href="{{ route('todos.show', $subject->id) }}" class="text-blue-600 dark:text-blue-400 hover:underline">{{ $subject->title }}</a> angenommen</span>
                                @elseif($activity->subject_type === \App\Models\Todo::class && $activity->action === 'completed')
                                    <span>hat die Challenge <a href="{{ route('todos.show', $subject->id) }}" class="text-blue-600 dark:text-blue-400 hover:underline">{{ $subject->title }}</a> erfolgreich abgeschlossen</span>
                                @elseif($activity->subject_type === \App\Models\User::class && $activity->action === 'member_approved')
                                    <span>Wir begrüßen unser neues Mitglied <a href="{{ route('profile.view', $subject->id) }}" class="text-[#8B0116] hover:underline dark:text-[#FCA5A5]">{{ $subject->name }}</a></span>
                                @endif
                            </div>
                        </li>
                    @empty
                        <li class="rounded-lg border border-dashed border-gray-200 px-4 py-6 text-center text-gray-600 dark:border-gray-700 dark:text-gray-400">Keine Aktivitäten vorhanden.</li>
                    @endforelse
                </ul>
            </div>
            <!-- TOP 3 Mitglieder -->
            <div class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6 mb-8">
                <h2 class="text-xl font-semibold text-[#8B0116] dark:text-[#FCA5A5] mb-6">TOP 3 Baxx-Sammler</h2>

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
                                    <!-- Gold Medaille für Platz 1 -->
                                    <div class="relative mb-2">
                                        <div class="absolute -top-3 -right-2 w-8 h-8 bg-yellow-400 dark:bg-yellow-500 rounded-full flex items-center justify-center text-white font-bold shadow-md transform group-hover:scale-110 transition-transform">1</div>
                                        <div class="h-20 w-20 rounded-full overflow-hidden border-4 border-yellow-400 dark:border-yellow-500 shadow-lg group-hover:shadow-xl transition-shadow">
                                            <img loading="lazy" src="{{ $topUser['profile_photo_url'] }}" alt="{{ $topUser['name'] }}" class="h-full w-full object-cover">
                                        </div>
                                    </div>
                                @elseif($index === 1)
                                    <!-- Silber Medaille für Platz 2 -->
                                    <div class="relative mb-2">
                                        <div class="absolute -top-3 -right-2 w-8 h-8 bg-gray-300 dark:bg-gray-400 rounded-full flex items-center justify-center text-gray-700 dark:text-gray-800 font-bold shadow-md transform group-hover:scale-110 transition-transform">2</div>
                                        <div class="h-20 w-20 rounded-full overflow-hidden border-4 border-gray-300 dark:border-gray-400 shadow-lg group-hover:shadow-xl transition-shadow">
                                            <img loading="lazy" src="{{ $topUser['profile_photo_url'] }}" alt="{{ $topUser['name'] }}" class="h-full w-full object-cover">
                                        </div>
                                    </div>
                                @else
                                    <!-- Bronze Medaille für Platz 3 -->
                                    <div class="relative mb-2">
                                        <div class="absolute -top-3 -right-2 w-8 h-8 bg-yellow-700 dark:bg-yellow-800 rounded-full flex items-center justify-center text-white font-bold shadow-md transform group-hover:scale-110 transition-transform">3</div>
                                        <div class="h-20 w-20 rounded-full overflow-hidden border-4 border-yellow-700 dark:border-yellow-800 shadow-lg group-hover:shadow-xl transition-shadow">
                                            <img loading="lazy" src="{{ $topUser['profile_photo_url'] }}" alt="{{ $topUser['name'] }}" class="h-full w-full object-cover">
                                        </div>
                                    </div>
                                @endif
                                
                                <h3 class="text-lg font-semibold text-gray-800 dark:text-white mt-2 group-hover:text-[#8B0116] dark:group-hover:text-[#FCA5A5] transition-colors">{{ $topUser['name'] }}</h3>
                                <p class="font-bold text-xl text-[#8B0116] dark:text-[#FCA5A5]">{{ $topUser['points'] }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Baxx</p>
                            </a>
                        @endforeach
                    </div>
                @else
                    <p class="text-gray-600 dark:text-gray-400 text-center">Noch keine Baxx vergeben.</p>
                @endif
            </div>
            <!-- Zu verifizierende Aufgaben Card (nur für Admin) -->
            @if(in_array($userRole, $allowedRoles) && $pendingVerification > 0)
                <a href="{{ route('todos.index') }}?filter=pending" class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6 mb-8 flex items-center justify-between hover:bg-gray-50 dark:hover:bg-gray-700 transition duration-200">
                    <div>
                        <h2 class="text-lg font-semibold text-[#8B0116] dark:text-[#FCA5A5] mb-1">Auf Verifizierung wartende Challenges</h2>
                        <p class="text-gray-600 dark:text-gray-400 text-sm">Es gibt {{ $pendingVerification }} Challenge(s), die auf Bestätigung warten</p>
                    </div>
                    <div class="flex items-center">
                        <div class="text-3xl font-bold text-[#8B0116] dark:text-[#FCA5A5] mr-4">{{ $pendingVerification }}</div>
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-gray-500 dark:text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </div>
                </a>
            @endif
    </x-member-page>
</x-app-layout>
