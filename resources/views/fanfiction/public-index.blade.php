<x-guest-layout>
    <div class="min-h-screen bg-base-200">
        {{-- Header --}}
        <div class="bg-base-100 shadow">
            <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-3xl font-bold text-base-content">
                            Fanfiction
                        </h1>
                        <p class="mt-1 text-sm text-base-content">
                            Kurzgeschichten aus dem MADDRAX-Universum
                        </p>
                    </div>
                    <a href="{{ route('login') }}"
                        class="inline-flex items-center px-4 py-2 bg-primary border border-transparent rounded-md font-semibold text-xs text-primary-content uppercase tracking-widest hover:bg-primary/80 focus:bg-primary/80 active:bg-primary/90 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 transition ease-in-out duration-150">
                        Anmelden für mehr
                    </a>
                </div>
            </div>
        </div>

        {{-- Teaser-Info --}}
        <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
            <div class="bg-warning/10 border border-warning rounded-lg p-4 mb-6">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-warning" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd"
                                d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                                clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-warning">
                            Als Gast siehst du nur einen kurzen Teaser jeder Geschichte.
                            <a href="{{ route('register') }}" class="font-medium underline hover:text-warning/80">
                                Werde Mitglied
                            </a>
                            um die vollständigen Geschichten zu lesen und zu kommentieren!
                        </p>
                    </div>
                </div>
            </div>

            @if ($fanfictions->isEmpty())
                <div class="bg-base-100 rounded-lg shadow-xl p-12 text-center">
                    <svg class="mx-auto h-12 w-12 text-base-content/40" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-base-content">Noch keine Fanfiction</h3>
                    <p class="mt-1 text-sm text-base-content">
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
                                <p class="text-sm text-base-content mb-3">
                                    von {{ $fanfiction->author_display_name }}
                                    @if ($fanfiction->published_at)
                                        • {{ $fanfiction->published_at->format('d.m.Y') }}
                                    @endif
                                </p>
                                <div class="text-base-content text-sm flex-grow">
                                    <template x-if="!expanded">
                                        <p>{{ Str::limit($fanfiction->teaser, 200) }}</p>
                                    </template>
                                    <template x-if="expanded">
                                        <p>{{ $fanfiction->teaser }}</p>
                                    </template>
                                </div>
                                <div class="mt-4 pt-4 border-t border-base-content/20 flex items-center justify-between">
                                    <button
                                        @click="expanded = !expanded; localStorage.setItem('fanfiction_{{ $fanfiction->id }}_expanded', expanded)"
                                        class="text-sm text-primary hover:text-primary/80"
                                    >
                                        <span x-text="expanded ? 'Weniger anzeigen' : 'Teaser erweitern'"></span>
                                    </button>
                                    <a href="{{ route('login') }}"
                                        class="text-sm font-medium text-primary hover:text-primary/80">
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
        </div>
    </div>
</x-guest-layout>
