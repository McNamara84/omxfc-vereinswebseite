<x-app-layout>
    <x-member-page>
        <div class="space-y-8">
            <x-ui.page-header eyebrow="Community Hub" :title="$dashboardGreeting" :description="$dashboardDescription">
                <x-slot:actions>
                    <x-ui.action-cluster align="end">
                        @foreach($dashboardHeaderBadges as $badge)
                            <span class="{{ $badge['class'] }}">{{ $badge['label'] }}</span>
                        @endforeach
                    </x-ui.action-cluster>

                    <x-ui.action-cluster align="end">
                        <a href="{{ route('todos.index') }}" wire:navigate class="btn btn-primary btn-sm rounded-full">Baxx verdienen</a>
                        <a href="{{ route('veranstaltungen.aktuell') }}" wire:navigate class="btn btn-ghost btn-sm rounded-full bg-base-100/70">Aktuelle Veranstaltung</a>
                    </x-ui.action-cluster>
                </x-slot:actions>
            </x-ui.page-header>

            @if(session('status'))
                <x-alert icon="o-check-circle" class="alert-success" dismissible>
                    {{ session('status') }}
                </x-alert>
            @endif

            @if($walletWarning)
                <x-alert icon="o-exclamation-triangle" class="alert-warning" dismissible>
                    {{ $walletWarning }}
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

                    @include('dashboard.partials.applicants-panel')
                    @include('dashboard.partials.pending-verification-panel')
                    @include('dashboard.partials.activity-feed')
                </div>

                <aside class="space-y-8">
                    @include('dashboard.partials.quick-actions-panel')
                    @include('dashboard.partials.top-users-panel')
                </aside>
            </div>
        </div>
    </x-member-page>
</x-app-layout>
