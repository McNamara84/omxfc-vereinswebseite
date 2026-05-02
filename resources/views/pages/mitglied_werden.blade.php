@php
    $hero = data_get($membershipPage, 'hero', []);
    $highlightSection = data_get($membershipPage, 'highlights', []);
    $processSection = data_get($membershipPage, 'process', []);
    $infoSection = data_get($membershipPage, 'infos', []);
    $formSection = data_get($membershipPage, 'form', []);
@endphp

<x-app-layout title="Mitglied werden – Offizieller MADDRAX Fanclub e. V." description="Online-Antrag zur Aufnahme in den Fanclub der MADDRAX-Romanserie.">
    <x-public-page class="space-y-8">
        <x-ui.page-header
            :eyebrow="data_get($hero, 'eyebrow')"
            :title="data_get($hero, 'title')"
            :description="data_get($hero, 'description')"
            data-testid="mitglied-werden-header"
        >
            <x-slot:actions>
                <x-ui.action-cluster>
                    @foreach(data_get($hero, 'badges', []) as $badge)
                        <span @class([
                            'badge badge-outline rounded-full px-3 py-3',
                            'badge-primary' => $loop->first,
                        ])>{{ $badge }}</span>
                    @endforeach
                </x-ui.action-cluster>

                <a href="{{ route(data_get($hero, 'secondary_cta.route')) }}" wire:navigate class="btn btn-ghost btn-sm rounded-full bg-base-100/75">
                    {{ data_get($hero, 'secondary_cta.label') }}
                </a>
            </x-slot:actions>
        </x-ui.page-header>

        <section class="grid gap-8 xl:grid-cols-[minmax(0,1fr)_minmax(22rem,0.92fr)] xl:items-start">
            <div class="order-2 space-y-8 xl:order-1">
                <x-ui.panel :title="data_get($highlightSection, 'title')" :description="data_get($highlightSection, 'description')">
                    <div class="grid gap-4 md:grid-cols-3">
                        @foreach(data_get($highlightSection, 'items', []) as $highlight)
                            <article class="rounded-[1.5rem] border border-base-content/10 bg-base-100/72 p-5">
                                <p class="text-[0.68rem] font-semibold uppercase tracking-[0.24em] text-base-content/45">{{ $highlight['eyebrow'] }}</p>
                                <h2 class="mt-2 font-display text-xl font-semibold tracking-tight text-base-content">{{ $highlight['title'] }}</h2>
                                <p class="mt-2 text-sm leading-relaxed text-base-content/72">{{ $highlight['description'] }}</p>
                            </article>
                        @endforeach
                    </div>
                </x-ui.panel>

                <x-ui.panel :title="data_get($processSection, 'title')" :description="data_get($processSection, 'description')">
                    <div class="space-y-4">
                        @foreach(data_get($processSection, 'steps', []) as $schritt)
                            <article class="flex gap-4 rounded-[1.5rem] border border-base-content/10 bg-base-100/72 p-4">
                                <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary/12 font-display text-lg font-semibold text-primary">
                                    {{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}
                                </span>
                                <div class="space-y-1">
                                    <h2 class="font-semibold text-base-content">{{ $schritt['title'] }}</h2>
                                    <p class="text-sm leading-relaxed text-base-content/72">{{ $schritt['description'] }}</p>
                                </div>
                            </article>
                        @endforeach
                    </div>
                </x-ui.panel>

                <x-ui.panel :title="data_get($infoSection, 'title')" :description="data_get($infoSection, 'description')">
                    <ul class="grid gap-3">
                        @foreach(data_get($infoSection, 'items', []) as $info)
                            <li class="flex items-start gap-3 rounded-[1.25rem] border border-base-content/10 bg-base-100/72 px-4 py-3 text-sm leading-relaxed text-base-content/78">
                                <span class="mt-0.5 inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-success/12 text-success">✓</span>
                                <span>{{ $info }}</span>
                            </li>
                        @endforeach
                    </ul>
                </x-ui.panel>
            </div>

            <div class="order-1 xl:order-2 xl:sticky xl:top-6">
                <x-ui.panel :eyebrow="data_get($formSection, 'eyebrow')" :title="data_get($formSection, 'title')" :description="data_get($formSection, 'description')">
                    <livewire:mitglied-werden-form />
                </x-ui.panel>
            </div>
        </section>
    </x-public-page>
</x-app-layout>
