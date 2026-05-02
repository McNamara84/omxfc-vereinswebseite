@php
    $hero = data_get($donationPage, 'hero', []);
    $impactSection = data_get($donationPage, 'impact', []);
    $transparencySection = data_get($donationPage, 'transparency', []);
    $paypalSection = data_get($donationPage, 'paypal', []);
@endphp

<x-app-layout title="Spenden – Offizieller MADDRAX Fanclub e. V." description="Unterstütze unseren Fanclub finanziell für Fantreffen, Projekte und Serverkosten.">
    <x-public-page class="space-y-8">
        <x-ui.page-header
            :eyebrow="data_get($hero, 'eyebrow')"
            :title="data_get($hero, 'title')"
            :description="data_get($hero, 'description')"
        >
            <x-slot:actions>
                <x-ui.action-cluster>
                    @foreach(data_get($hero, 'badges', []) as $badge)
                        <span class="{{ $loop->first ? 'badge badge-primary badge-outline rounded-full px-3 py-3' : 'badge badge-outline rounded-full px-3 py-3' }}">{{ $badge }}</span>
                    @endforeach
                </x-ui.action-cluster>
            </x-slot:actions>
        </x-ui.page-header>

        <section class="grid gap-8 xl:grid-cols-[minmax(0,1fr)_minmax(20rem,0.82fr)] xl:items-start">
            <div class="space-y-8">
                <x-ui.panel :title="data_get($impactSection, 'title')" :description="data_get($impactSection, 'description')">
                    <div class="grid gap-4 md:grid-cols-3">
                        @foreach(data_get($impactSection, 'items', []) as $punkt)
                            <article class="rounded-3xl border border-base-content/10 bg-base-100/72 p-5">
                                <p class="text-[0.68rem] font-semibold uppercase tracking-[0.24em] text-base-content/45">{{ $punkt['eyebrow'] }}</p>
                                <h2 class="mt-2 font-display text-xl font-semibold tracking-tight text-base-content">{{ $punkt['title'] }}</h2>
                                <p class="mt-2 text-sm leading-relaxed text-base-content/72">{{ $punkt['description'] }}</p>
                            </article>
                        @endforeach
                    </div>
                </x-ui.panel>

                <x-ui.panel :title="data_get($transparencySection, 'title')" :description="data_get($transparencySection, 'description')">
                    <ul class="grid gap-3">
                        @foreach(data_get($transparencySection, 'items', []) as $hinweis)
                            <li class="flex items-start gap-3 rounded-[1.25rem] border border-base-content/10 bg-base-100/72 px-4 py-3 text-sm leading-relaxed text-base-content/78">
                                <span class="mt-0.5 inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-primary/12 text-primary">{{ $loop->iteration }}</span>
                                <span>
                                    @if(isset($hinweis['email']))
                                        {{ $hinweis['prefix'] }}<a href="mailto:{{ $hinweis['email'] }}" class="link link-primary font-semibold">{{ $hinweis['email'] }}</a>{{ $hinweis['suffix'] }}
                                    @else
                                        {{ $hinweis['text'] }}
                                    @endif
                                </span>
                            </li>
                        @endforeach
                    </ul>
                </x-ui.panel>
            </div>

            <div class="xl:sticky xl:top-6">
                <x-ui.panel :title="data_get($paypalSection, 'title')" :description="data_get($paypalSection, 'description')">
                    <form action="https://www.paypal.com/donate" method="post" target="_top" class="flex flex-col items-center gap-4 text-center">
                        <input type="hidden" name="business" value="{{ data_get($paypalSection, 'business') }}" />
                        <input type="hidden" name="no_recurring" value="0" />
                        <input type="hidden" name="currency_code" value="EUR" />
                        <input type="image" src="https://www.paypalobjects.com/de_DE/DE/i/btn/btn_donateCC_LG.gif" name="submit" alt="{{ data_get($paypalSection, 'button_alt') }}" class="w-48" />
                        <img alt="" src="https://www.paypal.com/en_DE/i/scr/pixel.gif" width="1" height="1" />
                        <p class="max-w-sm text-sm leading-relaxed text-base-content/72">
                            {{ data_get($paypalSection, 'footer') }}
                        </p>
                    </form>
                </x-ui.panel>
            </div>
        </section>
    </x-public-page>
</x-app-layout>
