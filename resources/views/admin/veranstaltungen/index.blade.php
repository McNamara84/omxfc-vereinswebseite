<x-app-layout title="Veranstaltungen verwalten" description="Verwalte öffentliche Veranstaltungen, Inhalte und Module zentral im Admin-Bereich.">
    <div class="mx-auto flex max-w-7xl flex-col gap-6 px-4 py-8 sm:px-6 lg:px-8">
        <x-ui.page-header
            eyebrow="Adminbereich"
            title="Veranstaltungen verwalten"
            description="Pflege Inhalte, Aktivierungsstatus und die angebundenen Module pro Veranstaltung."
        >
            <x-slot:actions>
                <x-button label="Neue Veranstaltung" link="{{ route('admin.veranstaltungen.create') }}" icon="o-plus" class="btn-primary" />
            </x-slot:actions>
        </x-ui.page-header>

        @if (session('success'))
            <x-alert icon="o-check-circle" class="alert-success" dismissible>
                {{ session('success') }}
            </x-alert>
        @endif

        <div class="grid gap-5 lg:grid-cols-2">
            @foreach ($veranstaltungen as $veranstaltung)
                <x-ui.panel :title="$veranstaltung->titel" :description="$veranstaltung->untertitel ?: 'Veranstaltung ohne Untertitel'">
                    <div class="space-y-4">
                        <div class="flex flex-wrap gap-2 text-sm">
                            <x-badge :value="ucfirst($veranstaltung->status)" class="badge-outline" />
                            @if ($veranstaltung->ist_highlight)
                                <x-badge value="Aktuelle Hauptveranstaltung" class="badge-primary" icon="o-star" />
                            @endif
                            @if ($veranstaltung->status === 'archiviert')
                                <x-badge value="Archivierte Veranstaltung" class="badge-warning" icon="o-archive-box" />
                            @endif
                            @if ($veranstaltung->anmeldung_aktiv)
                                <x-badge value="Anmeldung aktiv" class="badge-success" icon="o-ticket" />
                            @endif
                            @if ($veranstaltung->vip_autoren_aktiv)
                                <x-badge value="VIP-Autoren aktiv" class="badge-warning" icon="o-users" />
                            @endif
                        </div>

                        <dl class="grid gap-2 text-sm text-base-content/75 sm:grid-cols-2">
                            <div>
                                <dt class="font-semibold text-base-content">Slug</dt>
                                <dd>{{ $veranstaltung->slug }}</dd>
                            </div>
                            <div>
                                <dt class="font-semibold text-base-content">Datum</dt>
                                <dd>{{ $veranstaltung->datum_von?->locale('de')->isoFormat('D. MMMM YYYY, HH:mm') ?: 'offen' }}</dd>
                            </div>
                            <div>
                                <dt class="font-semibold text-base-content">Anmeldungen</dt>
                                <dd>{{ $veranstaltung->anmeldungen_count }}</dd>
                            </div>
                            <div>
                                <dt class="font-semibold text-base-content">Abschnitte</dt>
                                <dd>{{ $veranstaltung->abschnitte_count }}</dd>
                            </div>
                        </dl>

                        @if ($veranstaltung->status === 'archiviert')
                            <p class="text-sm text-base-content/70">Archivierte Veranstaltung. Anmeldeliste weiterhin verfügbar.</p>
                        @endif

                        <div class="flex flex-wrap gap-2">
                            <x-button label="Bearbeiten" link="{{ route('admin.veranstaltungen.edit', $veranstaltung) }}" class="btn-primary btn-sm" />
                            <x-button label="Anmeldungen" link="{{ route('admin.veranstaltungen.anmeldungen', $veranstaltung) }}" class="btn-ghost btn-sm" />
                            <x-button label="VIP-Autoren" link="{{ route('admin.veranstaltungen.vip-authors', $veranstaltung) }}" class="btn-ghost btn-sm" />
                            <x-button label="Öffentliche Seite" link="{{ route('veranstaltungen.show', $veranstaltung) }}" class="btn-ghost btn-sm" />
                        </div>
                    </div>
                </x-ui.panel>
            @endforeach
        </div>
    </div>
</x-app-layout>