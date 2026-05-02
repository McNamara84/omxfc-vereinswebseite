@php
    $topUsersPanelTitle = $topUsersEntries->isNotEmpty()
        ? 'Top '.$topUsersEntries->count().' Baxx-Sammler'
        : 'Top Baxx-Sammler';
@endphp

<x-ui.panel :title="$topUsersPanelTitle" description="Wer aktuell das Community-Ranking anführt.">
    @if($topUsersEntries->isNotEmpty())
        <div
            class="grid gap-4"
            data-dashboard-top-users='{{ json_encode($topUsersPayload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) }}'
            role="list"
            aria-label="{{ $topUsersSummary }}"
        >
            <p class="sr-only" data-dashboard-top-summary="true" aria-live="polite">{{ $topUsersSummary }}</p>

            @foreach($topUsersEntries as $index => $topUser)
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
                        <p class="font-display text-2xl font-bold tracking-tight text-primary">{{ $topUser['formatted_points'] ?? $topUser['points'] }}</p>
                        <p class="text-xs uppercase tracking-[0.22em] text-base-content/45">Baxx</p>
                    </div>
                </a>
            @endforeach
        </div>
    @else
        <x-ui.empty-state icon="o-trophy" title="Noch kein Ranking" description="Noch keine Baxx vergeben." class="py-8" />
    @endif
</x-ui.panel>