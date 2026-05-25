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
                    \App\Models\FanfictionComment::class => 'Kommentar – Bezug nicht mehr verfügbar',
                ];
                $missingSubjectMessage = $missingSubjectMessages[$activity->subject_type]
                    ?? 'Gelöschter Eintrag – nicht mehr verfügbar';
                $isFantreffenRegistration = $activity->subject_type === \App\Models\FantreffenAnmeldung::class;
                $isSwapCompletion = $activity->subject_type === \App\Models\BookSwap::class && $activity->action === 'swap_completed';
                $activityUser = $activity->user;
                $showProfileLink = ! $isFantreffenRegistration && ! $isSwapCompletion && $activityUser;
                $typeLabels = [
                    \App\Models\Review::class => 'Rezension',
                    \App\Models\Fanfiction::class => 'Fanfiction',
                    \App\Models\ReviewComment::class => 'Kommentar',
                    \App\Models\FanfictionComment::class => 'Kommentar',
                    \App\Models\BookOffer::class => 'Tausch',
                    \App\Models\BookRequest::class => 'Gesuch',
                    \App\Models\BookSwap::class => 'Tausch',
                    \App\Models\RewardPurchase::class => 'Belohnung',
                    \App\Models\AdminMessage::class => 'Hinweis',
                    \App\Models\FantreffenAnmeldung::class => 'Fantreffen',
                    \App\Models\Todo::class => 'Challenge',
                    \App\Models\User::class => 'Mitglied',
                ];
                if ($activity->subject_type === \App\Models\User::class && str_starts_with((string) $activity->action, 'baxx_milestone_reached_')) {
                    $typeLabels[\App\Models\User::class] = 'Meilenstein';
                }
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
                    @elseif(! $isFantreffenRegistration && ! $isSwapCompletion)
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
                    @elseif($activity->subject_type === \App\Models\Fanfiction::class && $activity->action === 'published')
                        @php
                            $fanfictionPreview = \App\Support\PreviewText::make($subject->content ?? '', 160);
                        @endphp
                        <div class="space-y-1">
                            <a href="{{ route('fanfiction.show', $subject) }}" wire:navigate class="font-semibold text-info hover:underline">Neue Fanfiction: {{ $subject->title }}</a>
                            @if($fanfictionPreview->isNotEmpty())
                                <p class="text-sm text-base-content" aria-label="Auszug aus der Fanfiction">„{{ $fanfictionPreview }}"</p>
                            @endif
                        </div>
                    @elseif($activity->subject_type === \App\Models\BookOffer::class && $activity->action === 'bundle_created')
                        <div class="space-y-1">
                            <a href="{{ route('romantausch.index') }}" wire:navigate class="font-semibold text-info hover:underline">Neues Romantausch-Paket: {{ $subject->book_title }}</a>
                            <p class="text-sm text-base-content">Mehrere Heftangebote wurden als Paket für die Börse eingestellt.</p>
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
                            $commentAuthorName = $activityUser?->name ?? 'Unbekannter Nutzer';
                        @endphp
                        @if($review)
                            <div class="space-y-1">
                                <span>
                                    Kommentar zu <a href="{{ route('reviews.show', $review->book_id) }}" wire:navigate class="text-info hover:underline">{{ $review->title }}</a> von
                                    @if($activityUser)
                                        <a href="{{ route('profile.view', $activityUser->id) }}" wire:navigate class="text-primary hover:underline">{{ $commentAuthorName }}</a>
                                    @else
                                        <span class="text-base-content">{{ $commentAuthorName }}</span>
                                    @endif
                                </span>
                                @if($commentPreview->isNotEmpty())
                                    <p class="text-sm text-base-content" aria-label="Auszug aus dem Kommentar">„{{ $commentPreview }}"</p>
                                @endif
                            </div>
                        @else
                            <span class="text-base-content italic">
                                {{ $missingSubjectMessage }}
                            </span>
                        @endif
                    @elseif($activity->subject_type === \App\Models\FanfictionComment::class && $activity->action === 'created')
                        @php
                            $fanfiction = $subject?->fanfiction;
                            $commentPreview = \App\Support\PreviewText::make($subject?->content ?? '', 140);
                            $commentAuthorName = $activityUser?->name ?? 'Unbekannter Nutzer';
                        @endphp
                        @if($fanfiction)
                            <div class="space-y-1">
                                <span>
                                    Kommentar zu <a href="{{ route('fanfiction.show', $fanfiction) }}" wire:navigate class="text-info hover:underline">{{ $fanfiction->title }}</a> von
                                    @if($activityUser)
                                        <a href="{{ route('profile.view', $activityUser->id) }}" wire:navigate class="text-primary hover:underline">{{ $commentAuthorName }}</a>
                                    @else
                                        <span class="text-base-content">{{ $commentAuthorName }}</span>
                                    @endif
                                </span>
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
                    @elseif($activity->subject_type === \App\Models\RewardPurchase::class && $activity->action === 'reward_unlocked')
                        <div class="space-y-1">
                            <a href="{{ route('rewards.index') }}" wire:navigate class="font-semibold text-info hover:underline">Belohnung freigeschaltet: {{ $subject->reward?->title ?? 'Unbekannte Belohnung' }}</a>
                            <p class="text-sm text-base-content">Ein neues Extra aus dem Baxx-Bereich wurde freigeschaltet.</p>
                        </div>
                    @elseif($activity->subject_type === \App\Models\BookSwap::class && $activity->action === 'swap_completed')
                        @php
                            $offerOwner = $subject?->offer?->user;
                            $requestOwner = $subject?->request?->user;
                            $swapTitle = $subject?->offer?->book_title ?? $subject?->request?->book_title;
                        @endphp
                        <div class="space-y-1">
                            <a href="{{ route('romantausch.index') }}" wire:navigate class="font-semibold text-info hover:underline">Tausch erfolgreich abgeschlossen</a>
                            <p>
                                @if($offerOwner)
                                    <a href="{{ route('profile.view', $offerOwner->id) }}" wire:navigate class="text-primary hover:underline">{{ $offerOwner->name }}</a>
                                @else
                                    <span>Ein Mitglied</span>
                                @endif
                                und
                                @if($requestOwner)
                                    <a href="{{ route('profile.view', $requestOwner->id) }}" wire:navigate class="text-primary hover:underline">{{ $requestOwner->name }}</a>
                                @else
                                    <span>ein weiteres Mitglied</span>
                                @endif
                                haben ihren Romantausch bestätigt.
                            </p>
                            @if($swapTitle)
                                <p class="text-sm text-base-content">Abgeschlossenes Heft: <a href="{{ route('romantausch.index') }}" wire:navigate class="text-info hover:underline">{{ $swapTitle }}</a></p>
                            @endif
                        </div>
                    @elseif($activity->subject_type === \App\Models\Todo::class && $activity->action === 'accepted')
                        <span>hat die Challenge <a href="{{ route('todos.show', $subject->id) }}" wire:navigate class="text-info hover:underline">{{ $subject->title }}</a> angenommen</span>
                    @elseif($activity->subject_type === \App\Models\Todo::class && $activity->action === 'completed')
                        <span>hat die Challenge <a href="{{ route('todos.show', $subject->id) }}" wire:navigate class="text-info hover:underline">{{ $subject->title }}</a> erfolgreich abgeschlossen und {{ $subject->points }} Baxx verdient</span>
                    @elseif($activity->subject_type === \App\Models\User::class && str_starts_with((string) $activity->action, 'baxx_milestone_reached_'))
                        @php
                            $milestoneValue = (int) str_replace('baxx_milestone_reached_', '', (string) $activity->action);
                        @endphp
                        @if($milestoneValue === 1)
                            <span>hat die ersten Baxx verdient</span>
                        @else
                            <span>hat {{ $milestoneValue }} Baxx erreicht</span>
                        @endif
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