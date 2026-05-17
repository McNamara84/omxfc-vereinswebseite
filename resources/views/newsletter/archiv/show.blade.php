<x-app-layout title="{{ $newsletterAusgabe->subject }} – Newsletter-Archiv" description="Archivierte Newsletter-Ausgabe im Mitgliederbereich.">
    <x-member-page class="max-w-4xl space-y-8">
        <x-ui.page-header
            eyebrow="Newsletter-Archiv"
            title="{{ $newsletterAusgabe->subject }}"
            description="Archivierte Vereinsausgabe vom {{ optional($newsletterAusgabe->sent_at)->format('d.m.Y H:i') ?? 'unbekannten Zeitpunkt' }}."
        >
            <x-slot:actions>
                <div class="flex flex-wrap gap-2">
                    <x-badge value="{{ count($newsletterAusgabe->topics ?? []) }} Themen" class="badge-primary badge-outline" icon="o-document-text" />
                    <x-badge value="{{ implode(', ', $newsletterAusgabe->recipient_roles ?? []) }}" class="badge-ghost" icon="o-user-group" />
                </div>
            </x-slot:actions>
        </x-ui.page-header>

        <div class="flex justify-end">
            <x-button label="Zurück zum Archiv" link="{{ route('newsletter.archiv.index') }}" wire:navigate class="btn-ghost btn-sm" icon="o-arrow-left" />
        </div>

        <section class="space-y-6">
            @foreach ($newsletterAusgabe->topics ?? [] as $topic)
                <x-ui.panel :title="$topic['title'] ?? 'Ohne Titel'">
                    <div class="prose max-w-none text-base-content dark:prose-invert">
                        <p>{!! nl2br(e($topic['content'] ?? '')) !!}</p>
                    </div>
                </x-ui.panel>
            @endforeach
        </section>
    </x-member-page>
</x-app-layout>