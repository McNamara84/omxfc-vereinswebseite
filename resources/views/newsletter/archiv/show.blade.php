<x-app-layout title="{{ $newsletterAusgabe->subject }} – Newsletter-Archiv" description="Archivierte Newsletter-Ausgabe im Mitgliederbereich.">
    <x-member-page class="max-w-4xl space-y-8">
        @php($topics = \App\Support\NewsletterTopics::normalize($newsletterAusgabe->topics ?? []))

        <x-ui.page-header
            eyebrow="Newsletter-Archiv"
            title="{{ $newsletterAusgabe->subject }}"
            description="Archivierte Vereinsausgabe vom {{ optional($newsletterAusgabe->sent_at)->format('d.m.Y H:i') ?? 'unbekannten Zeitpunkt' }}."
        >
            <x-slot:actions>
                <div class="flex flex-wrap gap-2">
                    <x-badge value="{{ count($topics) }} Themen" class="badge-primary badge-outline" icon="o-document-text" />
                    <x-badge value="{{ implode(', ', $newsletterAusgabe->recipient_roles ?? []) }}" class="badge-ghost" icon="o-user-group" />
                </div>
            </x-slot:actions>
        </x-ui.page-header>

        <div class="flex justify-end">
            <x-button label="Zurück zum Archiv" link="{{ route('newsletter.archiv.index') }}" wire:navigate class="btn-ghost btn-sm" icon="o-arrow-left" />
        </div>

        <section class="space-y-6">
            @foreach ($topics as $topic)
                @php($renderedHtml = \App\Support\NewsletterTopics::renderHtml($topic['content'] ?? ''))
                <x-ui.panel :title="filled($topic['title'] ?? null) ? $topic['title'] : 'Ohne Titel'">
                    <div class="space-y-4">
                        @if (($topic['images'] ?? []) !== [])
                            <div class="grid gap-4 sm:grid-cols-2">
                                @foreach ($topic['images'] as $image)
                                    <img src="{{ Storage::disk('public')->url($image) }}" alt="Bild zum Thema {{ filled($topic['title'] ?? null) ? $topic['title'] : 'Ohne Titel' }}" class="w-full rounded-xl border border-base-content/10 object-cover shadow-sm" loading="lazy" decoding="async">
                                @endforeach
                            </div>
                        @endif

                        @if ($renderedHtml !== '')
                            <div class="prose max-w-none text-base-content dark:prose-invert">
                                {!! $renderedHtml !!}
                            </div>
                        @endif
                    </div>
                </x-ui.panel>
            @endforeach
        </section>
    </x-member-page>
</x-app-layout>