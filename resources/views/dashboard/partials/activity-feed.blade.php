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

    <div class="mb-5 flex flex-wrap gap-2" aria-label="Aktivitäten filtern" data-testid="activity-filters">
        @foreach($filters as $filterKey => $filterLabel)
            <button
                type="button"
                wire:click="selectFilter('{{ $filterKey }}')"
                wire:loading.attr="disabled"
                @class([
                    'btn btn-sm rounded-full',
                    'btn-primary' => $activeFilter === $filterKey,
                    'btn-ghost bg-base-200/70' => $activeFilter !== $filterKey,
                ])
                aria-pressed="{{ $activeFilter === $filterKey ? 'true' : 'false' }}"
            >
                {{ $filterLabel }}
            </button>
        @endforeach
    </div>

    @php
        $currentActivityDate = null;
    @endphp
    <ul class="space-y-3" role="list" aria-live="polite" aria-busy="false" wire:loading.attr="aria-busy">
        @forelse($activities as $activity)
            @php
                $activityDate = $activity->dashboard_date_key;
                $activityDateLabel = $activity->dashboard_date_label;
            @endphp
            @if($activityDate !== $currentActivityDate)
                <li class="sticky top-16 z-10 py-1" role="presentation" data-testid="activity-day-heading">
                    <h3 class="inline-flex rounded-full bg-base-100/95 px-3 py-1 text-xs font-bold uppercase tracking-wider text-base-content/65 shadow-sm ring-1 ring-base-200">
                        {{ $activityDateLabel }}
                    </h3>
                </li>
                @php
                    $currentActivityDate = $activityDate;
                @endphp
            @endif
            @php
                $subject = $activity->subject;
                $missingSubjectMessage = $activity->dashboard_missing_subject_message;
                $isFantreffenRegistration = $activity->dashboard_is_registration;
                $isSwapCompletion = $activity->dashboard_is_swap_completion;
                $activityUser = $activity->user;
                $activityUserName = $activity->dashboard_actor_name;
                $showProfileLink = $activity->dashboard_show_profile_link;
                $activityLabel = $activity->dashboard_label;
            @endphp
            <li class="relative rounded-lg border border-base-200 bg-base-200/50 px-4 py-3 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md" aria-label="Aktivität am {{ $activity->created_at->format('d.m.Y H:i') }}" data-testid="dashboard-activity">
                <div class="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-base-content/60">
                    <time datetime="{{ $activity->created_at->toIso8601String() }}" title="{{ $activity->created_at->format('d.m.Y H:i') }}" class="font-medium tabular-nums">
                        <span class="sr-only">Zeitpunkt:</span>
                        {{ $activity->created_at->format('H:i') }} Uhr
                    </time>
                    <span aria-hidden="true">&middot;</span>
                    <span class="font-bold uppercase tracking-wide text-primary/80">{{ $activityLabel }}</span>
                    @if($showProfileLink)
                        <span aria-hidden="true">&middot;</span>
                        <span class="sr-only">von Nutzer</span>
                        <a href="{{ route('profile.view', $activityUser->id) }}" wire:navigate class="font-semibold text-primary hover:underline">{{ $activityUserName }}</a>
                    @elseif(! $isFantreffenRegistration && ! $isSwapCompletion)
                        <span aria-hidden="true">&middot;</span>
                        <span>Unbekannter Nutzer</span>
                    @endif
                </div>

                <div class="mt-2 space-y-1 text-sm leading-relaxed">
                    @if(! $subject)
                        <span class="text-base-content italic">
                            {{ $missingSubjectMessage }}
                        </span>
                    @elseif($isFantreffenRegistration)
                        @php
                            $registrantName = $activityUser?->displayAlias()
                                ?? $subject?->vorname
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
                            $fanfictionPreview = (string) ($activity->dashboard_fanfiction_preview ?? '');
                        @endphp
                        <div class="space-y-1">
                            <a href="{{ route('fanfiction.show', $subject) }}" wire:navigate class="font-semibold text-info hover:underline">Neue Fanfiction: {{ $subject->title }}</a>
                            @if(filled($fanfictionPreview))
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
                            $commentAuthorName = $activityUserName ?? 'Unbekannter Nutzer';
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
                            $commentAuthorName = $activityUserName ?? 'Unbekannter Nutzer';
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
                                    <a href="{{ route('profile.view', $offerOwner->id) }}" wire:navigate class="text-primary hover:underline">{{ $offerOwner->nicknameOrName() }}</a>
                                @else
                                    <span>Ein Mitglied</span>
                                @endif
                                und
                                @if($requestOwner)
                                    <a href="{{ route('profile.view', $requestOwner->id) }}" wire:navigate class="text-primary hover:underline">{{ $requestOwner->nicknameOrName() }}</a>
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
                    @elseif($activity->subject_type === \App\Models\MaddraxikonAccountLink::class && $activity->action === \App\Models\Activity::ACTION_MADDRAXIKON_ACCOUNT_LINKED)
                        <span>hat das Mitgliedschaftskonto erfolgreich mit dem Maddraxikon-Konto „{{ $subject->wiki_username }}“ verknüpft und verdient nun Baxx durch Bearbeitungen im Maddraxikon.</span>
                    @elseif($activity->subject_type === \App\Models\User::class && str_starts_with((string) $activity->action, \App\Models\Activity::ACTION_MADDRAXIKON_BAXX_AWARDED_PREFIX))
                        @php
                            $maddraxikonAwardedPoints = (int) str_replace(
                                \App\Models\Activity::ACTION_MADDRAXIKON_BAXX_AWARDED_PREFIX,
                                '',
                                (string) $activity->action
                            );
                        @endphp
                        <span>hat bei der aktuellen Maddraxikon-Abrechnung {{ $maddraxikonAwardedPoints }} Baxx durch Mitwirkung im Maddraxikon verdient.</span>
                    @elseif($activity->subject_type === \App\Models\User::class && str_starts_with((string) $activity->action, 'baxx_milestone_reached_'))
                        @php
                            $milestoneValue = (int) str_replace('baxx_milestone_reached_', '', (string) $activity->action);
                            $milestoneGroupCount = (int) ($activity->dashboard_group_count ?? 1);
                            $milestoneValues = collect($activity->dashboard_milestone_values ?? [$milestoneValue])->sort()->values();
                        @endphp
                        @if($milestoneGroupCount > 1)
                            <span>hat {{ $milestoneGroupCount }} Baxx-Meilensteine erreicht: {{ $milestoneValues->join(', ', ' und ') }} Baxx</span>
                        @elseif($milestoneValue === 1)
                            <span>hat die ersten Baxx verdient</span>
                        @else
                            <span>hat {{ $milestoneValue }} Baxx erreicht</span>
                        @endif
                    @elseif($activity->subject_type === \App\Models\User::class && $activity->action === 'member_approved')
                        <span>Wir begrüßen unser neues Mitglied <a href="{{ route('profile.view', $subject->id) }}" wire:navigate class="text-primary hover:underline">{{ $subject->nicknameOrName() }}</a></span>
                    @endif
                </div>
            </li>
        @empty
            <li>
                <x-ui.empty-state icon="o-inbox" title="Noch keine Aktivität" description="Keine Aktivitäten vorhanden." />
            </li>
        @endforelse
    </ul>

    <div class="mt-5 flex min-h-12 flex-col items-center justify-center gap-2 text-center" aria-live="polite">
        @if($hasMore)
            <button
                type="button"
                class="btn btn-outline btn-sm rounded-full"
                wire:click="loadMore"
                wire:loading.attr="disabled"
                data-dashboard-feed-load-more
            >
                <span wire:loading.remove wire:target="loadMore">Weitere Aktivitäten laden</span>
                <span wire:loading wire:target="loadMore">Aktivitäten werden geladen …</span>
            </button>
            <span class="block h-px w-full" data-dashboard-feed-sentinel aria-hidden="true"></span>
        @elseif($activities->isNotEmpty())
            <p class="text-sm text-base-content/60" data-testid="activity-feed-end">Du hast das Ende des Aktivitätsverlaufs erreicht.</p>
        @endif
    </div>
</x-ui.panel>
