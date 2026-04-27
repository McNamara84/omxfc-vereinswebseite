<x-app-layout>
    <x-member-page>
        @php
            $showGovernanceTools = in_array($userRole, $allowedRoles, true);
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

        <div class="space-y-8">
            <x-ui.page-header eyebrow="Community Hub" :title="$dashboardGreeting" :description="$dashboardDescription">
                <x-slot:actions>
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="badge badge-primary badge-outline rounded-full px-3 py-3">{{ $userPoints }} Baxx</span>
                        <span class="badge badge-outline rounded-full px-3 py-3">{{ $openTodos }} offene Challenges</span>
                        @if($showGovernanceTools && $pendingVerification > 0)
                            <span class="badge badge-secondary badge-outline rounded-full px-3 py-3">{{ $pendingVerification }} warten auf Verifizierung</span>
                        @endif
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <a href="{{ route('todos.index') }}" wire:navigate class="btn btn-primary btn-sm rounded-full">Challenges öffnen</a>
                        <a href="{{ route('fantreffen.2026') }}" wire:navigate class="btn btn-ghost btn-sm rounded-full bg-base-100/70">Fantreffen 2026</a>
                    </div>
                </x-slot:actions>
            </x-ui.page-header>

            @if(session('status'))
                <x-alert icon="o-check-circle" class="alert-success" dismissible>
                    {{ session('status') }}
                </x-alert>
            @endif

            @if($prominentReviewSpecialOffer)
                <x-review-baxx-special-offer :offer="$prominentReviewSpecialOffer" />
            @endif

            <div class="grid gap-8 xl:grid-cols-[minmax(0,1.7fr)_minmax(22rem,0.95fr)] xl:items-start">
                <div class="space-y-8">
                    <x-ui.panel title="Dein Fokus heute" description="Die wichtigsten Kennzahlen und Einstiege für deinen nächsten Schritt in der Community.">
                        <div class="grid grid-cols-1 gap-6 md:grid-cols-2 xl:grid-cols-2 2xl:grid-cols-3 grid-flow-row-dense" aria-label="Überblick wichtiger Community-Kennzahlen">
                            @foreach($focusCards as $card)
                                <x-bento-card :href="$card['href']" :title="$card['title']" :sr-text="$card['sr_text']" :icon="$card['icon']" wire:navigate>
                                    <x-slot:description>{{ $card['description'] }}</x-slot:description>
                                    <x-slot:value>{{ $card['value'] }}</x-slot:value>
                                </x-bento-card>
                            @endforeach
                        </div>
                    </x-ui.panel>

                    @if($anwaerter->isNotEmpty())
                        <x-ui.panel title="Mitgliedsanträge" description="Neue Vereinsanträge können hier direkt geprüft, genehmigt oder abgelehnt werden." data-testid="dashboard-applicants-panel">
                            <div x-data="{ rejectUrl: '' }">
                                <div class="overflow-x-auto">
                                    <table class="table table-zebra">
                                        <thead>
                                            <tr>
                                                <th>Name</th>
                                                <th>E-Mail</th>
                                                <th>Beitrag</th>
                                                <th class="text-center">Aktion</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($anwaerter as $person)
                                                <tr>
                                                    <td>
                                                        <a href="{{ route('profile.view', $person->id) }}" wire:navigate class="text-primary hover:underline">{{ $person->name }}</a>
                                                    </td>
                                                    <td>{{ $person->email }}</td>
                                                    <td>{{ $person->mitgliedsbeitrag }}</td>
                                                    <td>
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
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>

                                <x-mary-modal id="reject-anwaerter-modal" title="Antrag ablehnen" separator without-trap-focus>
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
                        </x-ui.panel>
                    @endif

                    @if($showGovernanceTools && $pendingVerification > 0)
                        <a href="{{ route('todos.index') }}?filter=pending" wire:navigate class="block" data-testid="dashboard-pending-panel">
                            <x-ui.panel>
                                <div class="flex items-center justify-between gap-4">
                                    <div class="space-y-1">
                                        <p class="text-[0.7rem] font-semibold uppercase tracking-[0.24em] text-base-content/45">Moderation</p>
                                        <h2 class="font-display text-2xl font-semibold tracking-tight text-base-content">Auf Verifizierung wartende Challenges</h2>
                                        <p class="text-sm text-base-content/72">Es gibt {{ $pendingVerification }} Challenge(s), die auf Bestätigung warten.</p>
                                    </div>

                                    <div class="flex items-center gap-4">
                                        <div class="font-display text-4xl font-bold tracking-tight text-primary">{{ $pendingVerification }}</div>
                                        <span class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-primary/10 text-primary ring-1 ring-primary/15">
                                            <x-icon name="o-chevron-right" class="h-6 w-6" />
                                        </span>
                                    </div>
                                </div>
                            </x-ui.panel>
                        </a>
                    @endif

                    <x-ui.panel class="overflow-hidden">
                        <x-slot:header>
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <h2 class="font-display text-2xl font-semibold tracking-tight text-base-content">Aktivitäten</h2>
                                    <p class="text-sm text-base-content/72">Neueste Rezensionen, Kommentare und Community-Aktionen im Überblick.</p>
                                </div>
                                <x-badge value="Live-Feed" class="badge-primary badge-outline hidden rounded-full sm:inline-flex" icon="o-signal" />
                            </div>
                        </x-slot:header>

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
                            <x-badge :value="$activityLabel" class="badge-primary badge-outline" icon="o-tag" />
                            @if($showProfileLink)
                                <span class="inline-flex items-center gap-1 rounded-full bg-base-100 px-2 py-1 ring-1 ring-base-200">
                                    <span class="sr-only">von Nutzer</span>
                                    <x-icon name="o-user" class="w-3.5 h-3.5" />
                                    <a href="{{ route('profile.view', $activityUser->id) }}" wire:navigate class="font-semibold text-primary hover:underline">{{ $activityUser->name }}</a>
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
                                    <a href="{{ route('reviews.show', $subject->book_id) }}" wire:navigate class="font-semibold text-info hover:underline">Neue Rezension: {{ $subject->title }}</a>
                                    @if($reviewPreview->isNotEmpty())
                                        <p class="text-sm text-base-content" aria-label="Auszug aus der Rezension">„{{ $reviewPreview }}"</p>
                                    @endif
                                </div>
                            @elseif($activity->subject_type === \App\Models\BookOffer::class)
                                <div class="space-y-1">
                                    <a href="{{ route('romantausch.index') }}" wire:navigate class="font-semibold text-info hover:underline">Neues Angebot: {{ $subject->book_title }}</a>
                                    <p class="text-sm text-base-content">Entdecke neue Tauschangebote aus der Community.</p>
                                </div>
                            @elseif($activity->subject_type === \App\Models\BookRequest::class)
                                <div class="space-y-1">
                                    <a href="{{ route('romantausch.index') }}" wire:navigate class="font-semibold text-info hover:underline">Neues Gesuch: {{ $subject->book_title }}</a>
                                    <p class="text-sm text-base-content">Vielleicht hast du genau das passende Heft zum Teilen.</p>
                                </div>
                            @elseif($activity->subject_type === \App\Models\ReviewComment::class)
                                @php
                                    $review = $subject?->review;
                                    $commentPreview = \App\Support\PreviewText::make($subject?->content ?? '', 140);
                                @endphp
                                @if($review)
                                    <div class="space-y-1">
                                        <span>Kommentar zu <a href="{{ route('reviews.show', $review->book_id) }}" wire:navigate class="text-info hover:underline">{{ $review->title }}</a> von <a href="{{ route('profile.view', $activity->user->id) }}" wire:navigate class="text-primary hover:underline">{{ $activity->user->name }}</a></span>
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
                                <span>hat die Challenge <a href="{{ route('todos.show', $subject->id) }}" wire:navigate class="text-info hover:underline">{{ $subject->title }}</a> angenommen</span>
                            @elseif($activity->subject_type === \App\Models\Todo::class && $activity->action === 'completed')
                                <span>hat die Challenge <a href="{{ route('todos.show', $subject->id) }}" wire:navigate class="text-info hover:underline">{{ $subject->title }}</a> erfolgreich abgeschlossen</span>
                            @elseif($activity->subject_type === \App\Models\User::class && $activity->action === 'member_approved')
                                <span>Wir begrüßen unser neues Mitglied <a href="{{ route('profile.view', $subject->id) }}" wire:navigate class="text-primary hover:underline">{{ $subject->name }}</a></span>
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
                    </x-ui.panel>
                </div>

                <aside class="space-y-8">
                    <x-ui.panel title="Schnellstart" description="Beliebte Wege zurück in laufende Aktionen und Inhalte." data-testid="dashboard-quick-actions">
                        <div class="grid gap-3">
                            @foreach($quickActions as $action)
                                <a href="{{ $action['href'] }}" wire:navigate class="group flex items-start gap-4 rounded-[1.5rem] border border-base-content/10 bg-base-100/70 px-4 py-4 transition hover:-translate-y-0.5 hover:border-primary/20 hover:shadow-lg">
                                    <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-primary/10 text-primary ring-1 ring-primary/15">
                                        <x-icon :name="$action['icon']" class="h-5 w-5" />
                                    </span>

                                    <span class="min-w-0 flex-1 space-y-1">
                                        <span class="flex flex-wrap items-center gap-2 font-semibold text-base-content transition-colors group-hover:text-primary">
                                            <span>{{ $action['title'] }}</span>
                                            @if($action['badge'] ?? null)
                                                <span class="badge badge-primary badge-sm rounded-full">{{ $action['badge'] }}</span>
                                            @endif
                                        </span>
                                        <span class="block text-sm leading-relaxed text-base-content/70">{{ $action['description'] }}</span>
                                    </span>

                                    <x-icon name="o-chevron-right" class="mt-1 h-5 w-5 shrink-0 text-base-content/35 transition-colors group-hover:text-primary" />
                                </a>
                            @endforeach
                        </div>
                    </x-ui.panel>

                    <x-ui.panel title="TOP 3 Baxx-Sammler" description="Wer aktuell das Community-Ranking anführt.">
                        @if($topUsersCollection->isNotEmpty())
                            <div
                                class="grid gap-4"
                                data-dashboard-top-users='{{ json_encode($topUsersPayload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) }}'
                                role="list"
                                aria-label="{{ $topUsersSummary }}"
                            >
                                <p class="sr-only" data-dashboard-top-summary="true" aria-live="polite">{{ $topUsersSummary }}</p>

                                @foreach($topUsersCollection as $index => $topUser)
                                    @php
                                        $medalClasses = [
                                            'bg-warning text-warning-content border-warning/40' => $index === 0,
                                            'bg-base-300 text-base-content border-base-content/20' => $index === 1,
                                            'bg-accent text-accent-content border-accent/40' => $index > 1,
                                        ];
                                    @endphp

                                    <a href="{{ route('profile.view', $topUser['id']) }}" wire:navigate class="group flex items-center gap-4 rounded-[1.5rem] border border-base-content/10 bg-base-100/72 px-4 py-4 transition hover:-translate-y-0.5 hover:border-primary/20 hover:shadow-lg" data-dashboard-top-user-item role="listitem">
                                        <div class="relative">
                                            <div class="h-16 w-16 overflow-hidden rounded-2xl border-2 border-base-content/10 shadow-md">
                                                <img loading="lazy" src="{{ $topUser['profile_photo_url'] }}" alt="{{ $topUser['name'] }}" class="h-full w-full object-cover">
                                            </div>
                                            <div @class(['absolute -bottom-2 -right-2 flex h-8 w-8 items-center justify-center rounded-full border text-sm font-bold shadow-md', ...$medalClasses])>
                                                {{ $index + 1 }}
                                            </div>
                                        </div>

                                        <div class="min-w-0 flex-1">
                                            <h3 class="truncate text-lg font-semibold text-base-content transition-colors group-hover:text-primary">{{ $topUser['name'] }}</h3>
                                            <p class="text-sm text-base-content/60">Community-Ranking</p>
                                        </div>

                                        <div class="text-right">
                                            <p class="font-display text-2xl font-bold tracking-tight text-primary">{{ $topUser['points'] }}</p>
                                            <p class="text-xs uppercase tracking-[0.22em] text-base-content/45">Baxx</p>
                                        </div>
                                    </a>
                                @endforeach
                            </div>
                        @else
                            <div class="py-8 text-center text-base-content">
                                <x-icon name="o-trophy" class="mx-auto mb-2 h-12 w-12 opacity-30" />
                                Noch keine Baxx vergeben.
                            </div>
                        @endif
                    </x-ui.panel>
                </aside>
            </div>
        </div>
    </x-member-page>
</x-app-layout>
