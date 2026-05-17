<x-app-layout title="Newsletter-Archiv – Offizieller MADDRAX Fanclub e. V." description="Archivierte Vereinsnewsletter fuer Mitglieder.">
    <x-member-page class="max-w-6xl space-y-8">
        <x-ui.page-header
            eyebrow="Verein"
            title="Newsletter-Archiv"
            description="Hier findest du alle veroeffentlichten Vereinsnewsletter im Webformat, chronologisch sortiert und dauerhaft im Mitgliederbereich verfuegbar."
        >
            <x-slot:actions>
                <div class="flex flex-wrap gap-2">
                    <span class="badge badge-primary badge-outline rounded-full px-3 py-3">{{ $ausgaben->total() }} Ausgaben</span>
                    <span class="badge badge-outline rounded-full px-3 py-3">Mitgliederbereich</span>
                </div>
            </x-slot:actions>
        </x-ui.page-header>

        @if ($ausgaben->isEmpty())
            <x-ui.panel>
                <div class="py-12 text-center">
                    <x-icon name="o-envelope" class="mx-auto h-12 w-12 text-base-content/50" />
                    <h2 class="mt-4 text-lg font-semibold">Noch keine veroeffentlichten Newsletter</h2>
                    <p class="mt-2 text-sm text-base-content/70">Sobald die erste Ausgabe freigegeben ist, erscheint sie hier automatisch im Archiv.</p>
                </div>
            </x-ui.panel>
        @else
            <section class="grid gap-6 lg:grid-cols-2">
                @foreach ($ausgaben as $ausgabe)
                    @php
                        $firstTopic = $ausgabe->topics[0] ?? null;
                    @endphp

                    <x-ui.panel class="h-full">
                        <div class="flex h-full flex-col gap-4">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <h2 class="text-xl font-semibold">
                                        <a href="{{ route('newsletter.archiv.show', $ausgabe) }}" class="transition hover:text-primary" wire:navigate>
                                            {{ $ausgabe->subject }}
                                        </a>
                                    </h2>
                                    <p class="mt-2 text-sm text-base-content/70">
                                        Versand: {{ optional($ausgabe->sent_at)->format('d.m.Y H:i') ?? 'Unbekannt' }}
                                    </p>
                                </div>
                                <x-badge value="{{ count($ausgabe->topics ?? []) }} Themen" class="badge-ghost" icon="o-document-text" />
                            </div>

                            <div class="space-y-2 text-sm leading-relaxed text-base-content/80">
                                @if ($firstTopic)
                                    <p class="font-medium text-base-content">{{ $firstTopic['title'] }}</p>
                                    <p>{{ Str::limit($firstTopic['content'] ?? '', 220) }}</p>
                                @else
                                    <p>Diese Ausgabe enthaelt aktuell noch keine Themenbloecke.</p>
                                @endif
                            </div>

                            <div class="mt-auto flex items-center justify-between gap-3 border-t border-base-content/10 pt-4 text-sm">
                                <span class="text-base-content/65">Zielgruppen: {{ implode(', ', $ausgabe->recipient_roles ?? []) }}</span>
                                <x-button label="Zur Ausgabe" link="{{ route('newsletter.archiv.show', $ausgabe) }}" wire:navigate class="btn-primary btn-sm" icon="o-arrow-right" />
                            </div>
                        </div>
                    </x-ui.panel>
                @endforeach
            </section>

            <div>
                {{ $ausgaben->links() }}
            </div>
        @endif
    </x-member-page>
</x-app-layout>