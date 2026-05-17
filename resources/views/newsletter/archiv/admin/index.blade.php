<x-app-layout title="Newsletter-Archiv verwalten">
    <x-member-page class="max-w-6xl space-y-8">
        @if (session('status'))
            <x-alert icon="o-check-circle" class="alert-success mb-4" dismissible>
                {{ session('status') }}
            </x-alert>
        @endif

        <x-ui.page-header
            eyebrow="Adminbereich"
            title="Newsletter-Archiv verwalten"
            description="Hier pruefst du Archiv-Entwuerfe, pflegst Inhalte nach und gibst Ausgaben fuer das Mitgliederarchiv frei."
        >
            <x-slot:actions>
                <x-button label="Newsletter versenden" link="{{ route('newsletter.create') }}" class="btn-primary btn-sm" icon="o-paper-airplane" />
            </x-slot:actions>
        </x-ui.page-header>

        @if ($newsletterAusgaben->isEmpty())
            <x-ui.panel>
                <div class="py-12 text-center">
                    <x-icon name="o-envelope" class="mx-auto h-12 w-12 text-base-content/50" />
                    <h2 class="mt-4 text-lg font-semibold">Noch keine Archiv-Eintraege</h2>
                    <p class="mt-2 text-sm text-base-content/70">Nach dem ersten echten Newsletter-Versand wird hier automatisch ein Entwurf angelegt.</p>
                </div>
            </x-ui.panel>
        @else
            <div class="space-y-6">
                @foreach ($newsletterAusgaben as $newsletterAusgabe)
                    <x-ui.panel>
                        <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                            <div class="space-y-2">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h2 class="text-xl font-semibold">{{ $newsletterAusgabe->subject }}</h2>
                                    @if ($newsletterAusgabe->status === \App\Enums\NewsletterAusgabeStatus::Veroeffentlicht)
                                        <x-badge value="Veroeffentlicht" class="badge-success" icon="o-check-circle" />
                                    @else
                                        <x-badge value="Entwurf" class="badge-warning" icon="o-pencil-square" />
                                    @endif
                                </div>
                                <p class="text-sm text-base-content/70">Slug: {{ $newsletterAusgabe->slug }}</p>
                                <p class="text-sm text-base-content/70">Versand: {{ optional($newsletterAusgabe->sent_at)->format('d.m.Y H:i') ?? 'Unbekannt' }}</p>
                            </div>

                            <div class="flex flex-wrap gap-2">
                                <x-button label="Bearbeiten" link="{{ route('newsletter.archiv.admin.edit', $newsletterAusgabe) }}" class="btn-primary btn-sm" icon="o-pencil-square" />
                                @if ($newsletterAusgabe->status !== \App\Enums\NewsletterAusgabeStatus::Veroeffentlicht)
                                    <form method="POST" action="{{ route('newsletter.archiv.admin.publish', $newsletterAusgabe) }}">
                                        @csrf
                                        <x-button type="submit" label="Veroeffentlichen" class="btn-secondary btn-sm" icon="o-check" />
                                    </form>
                                @endif
                            </div>
                        </div>
                    </x-ui.panel>
                @endforeach
            </div>

            <div>
                {{ $newsletterAusgaben->links() }}
            </div>
        @endif
    </x-member-page>
</x-app-layout>