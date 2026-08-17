@php
    $topUsersPanelTitle = $topUsersEntries->isNotEmpty()
        ? 'Top '.$topUsersEntries->count().' Baxx-Sammler'
        : 'Top Baxx-Sammler';
@endphp

<x-ui.panel :title="$topUsersPanelTitle" class="p-5">
    @if($topUsersEntries->isNotEmpty())
        <div
            class="grid gap-2 sm:grid-cols-3 lg:grid-cols-1"
            data-dashboard-top-users='{{ json_encode($topUsersPayload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) }}'
            role="list"
            aria-label="{{ $topUsersSummary }}"
        >
            <p class="sr-only" data-dashboard-top-summary="true" aria-live="polite">{{ $topUsersSummary }}</p>

            @foreach($topUsersEntries as $index => $topUser)
                <a href="{{ route('profile.view', $topUser['id']) }}" wire:navigate class="group flex items-center gap-3 rounded-xl border border-base-content/10 bg-base-200/35 px-3 py-2 transition hover:border-primary/30 hover:bg-primary/5" data-dashboard-top-user-item role="listitem">
                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-primary/10 text-xs font-bold text-primary">{{ $index + 1 }}</span>
                    <img loading="lazy" src="{{ $topUser['profile_photo_url'] }}" alt="" class="h-9 w-9 shrink-0 rounded-xl object-cover">
                    <span class="min-w-0 flex-1 truncate text-sm font-semibold group-hover:text-primary">{{ $topUser['name'] }}</span>
                    <span class="whitespace-nowrap text-sm font-bold text-primary">{{ $topUser['formatted_points'] ?? $topUser['points'] }} Baxx</span>
                </a>
            @endforeach
        </div>
    @else
        <x-ui.empty-state icon="o-trophy" title="Noch kein Ranking" description="Noch keine Baxx vergeben." class="py-4" />
    @endif
</x-ui.panel>
