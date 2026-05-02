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
                $showProfileLink = ! $isFantreffenRegistration && $activityUser;
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
                    @elseif(! $isFantreffenRegistration)
                        <span class="inline-flex items-center gap-1 rounded-full bg-base-100 px-2 py-1 ring-1 ring-base-200">
                            <x-icon name="o-user" class="w-3.5 h-3.5" />
                            Unbekannter Nutzer
                        </span>
                    @endif
                </div>

                <div class="mt-2 space-y-1 text-sm leading-relaxed">
                    @if(! $subject)
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
            <li>
                <x-ui.empty-state icon="o-inbox" title="Noch keine Aktivität" description="Keine Aktivitäten vorhanden." />
            </li>
        @endforelse
    </ul>
</x-ui.panel>