<x-app-layout title="Fanfiction – Offizieller MADDRAX Fanclub e. V.">
    <x-public-page class="space-y-6">
        <x-ui.page-header title="Fanfiction" description="Kurzgeschichten aus dem MADDRAX-Universum">
            <x-slot:actions>
                <a href="{{ route('login') }}" wire:navigate class="btn btn-primary">Anmelden für mehr</a>
            </x-slot:actions>
        </x-ui.page-header>
        <x-alert class="alert-info" icon="o-information-circle">
            Als Gast siehst du nur einen kurzen Teaser jeder Geschichte.
            <a href="{{ route('register') }}" class="link">Werde Mitglied</a>, um die vollständigen Geschichten zu lesen und zu kommentieren!
        </x-alert>
            @if ($fanfictions->isEmpty())
                <div class="bg-base-100 rounded-lg shadow-xl p-12 text-center">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-base-content">Noch keine Fanfiction</h3>
                    <p class="mt-1 text-sm text-base-content/80">
                        Es wurden noch keine Geschichten veröffentlicht.
                    </p>
                </div>
            @else
                <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                    @foreach ($fanfictions as $fanfiction)
                        <div
                            class="bg-base-100 rounded-lg shadow-xl overflow-hidden flex flex-col"
                            x-data="{ expanded: localStorage.getItem('fanfiction_{{ $fanfiction->id }}_expanded') === 'true' }"
                        >
                            @if ($fanfiction->photos && count($fanfiction->photos) > 0)
                                <div class="aspect-video overflow-hidden">
                                    <img src="{{ Storage::url($fanfiction->photos[0]) }}"
                                        alt="{{ $fanfiction->title }}" class="w-full h-full object-cover">
                                </div>
                            @endif
                            <div class="p-6 flex-grow flex flex-col">
                                <h2 class="text-xl font-semibold text-base-content mb-2">
                                    {{ $fanfiction->title }}
                                </h2>
                                <p class="text-sm text-base-content/80 mb-3">
                                    von {{ $fanfiction->author_display_name }}
                                    @if ($fanfiction->published_at)
                                        • {{ $fanfiction->published_at->format('d.m.Y') }}
                                    @endif
                                </p>
                                <div class="text-base-content/80 text-sm flex-grow">
                                    <template x-if="!expanded">
                                        <p>{{ Str::limit($fanfiction->teaser, 200) }}</p>
                                    </template>
                                    <template x-if="expanded">
                                        <p>{{ $fanfiction->teaser }}</p>
                                    </template>
                                </div>
                                <div class="mt-4 pt-4 border-t border-base-content/10 flex items-center justify-between">
                                    <button
                                        @click="expanded = !expanded; localStorage.setItem('fanfiction_{{ $fanfiction->id }}_expanded', expanded)"
                                        class="text-sm link link-primary"
                                    >
                                        <span x-text="expanded ? 'Weniger anzeigen' : 'Teaser erweitern'"></span>
                                    </button>
                                    <a href="{{ route('login') }}"
                                        class="text-sm font-medium link link-primary">
                                        Vollständig lesen →
                                    </a>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="mt-8">
                    {{ $fanfictions->links() }}
                </div>
            @endif

    </x-public-page>
</x-app-layout>
