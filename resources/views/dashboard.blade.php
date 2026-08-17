<x-app-layout>
    <x-member-page>
        <div class="space-y-6">
            <header class="flex flex-col gap-3 border-b border-base-content/10 pb-5 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-primary/75">Community Hub</p>
                    <h1 class="mt-1 font-display text-3xl font-semibold tracking-tight text-base-content sm:text-4xl">{{ $dashboardGreeting }}</h1>
                    <p class="mt-1 text-sm text-base-content/65">{{ $dashboardDescription }}</p>
                </div>
                @if($dashboardPrimaryAction)
                    <a href="{{ $dashboardPrimaryAction['href'] }}" wire:navigate class="btn btn-primary btn-sm rounded-full self-start sm:self-auto">
                        {{ $dashboardPrimaryAction['title'] }}
                    </a>
                @endif
            </header>

            @if(session('status'))
                <x-alert icon="o-check-circle" class="alert-success" dismissible>
                    {{ session('status') }}
                </x-alert>
            @endif

            @if($walletWarning)
                <x-alert icon="o-exclamation-triangle" class="alert-warning" dismissible>
                    <span class="font-semibold">Baxx-Guthaben wird geprüft</span>
                    <span class="block text-sm">{{ $walletWarning }}</span>
                </x-alert>
            @endif

            @if($prominentReviewSpecialOffer)
                <x-review-baxx-special-offer :offer="$prominentReviewSpecialOffer" />
            @endif

            <x-dashboard.tasks-panel :tasks="$tasks" />

            @foreach($metricGroups as $metricGroup)
                <x-dashboard.metric-group :group="$metricGroup" />
            @endforeach

            <div class="grid gap-4 lg:grid-cols-2 lg:items-start">
                @include('dashboard.partials.quick-actions-panel')
                @include('dashboard.partials.top-users-panel')
            </div>

            @include('dashboard.partials.applicants-panel')

            <livewire:dashboard-activity-feed />
        </div>
    </x-member-page>
</x-app-layout>
