<x-app-layout>
    @php
        $formatBytes = fn (?int $bytes): string => \App\Services\DatabaseMaintenance\DatabaseMaintenanceLimitService::formatBytes($bytes);
        $effectiveUploadBytes = $limits['effective_upload_bytes'] ?? null;
    @endphp

    <x-member-page class="space-y-8">
        <x-ui.page-header
            eyebrow="Adminbereich"
            title="Datenbank"
            description="SQL-Dumps herunterladen und kontrolliert wieder in die MariaDB-Datenbank einspielen."
        />

        @if(session('status'))
            <x-alert icon="o-check-circle" class="alert-success" role="status">
                {{ session('status') }}
            </x-alert>
        @endif

        @if($errors->any())
            <x-alert icon="o-exclamation-triangle" class="alert-error" role="alert">
                {{ $errors->first() }}
            </x-alert>
        @endif

        <div class="grid gap-6 xl:grid-cols-2">
            <x-ui.panel
                title="SQL-Dump herunterladen"
                description="Erzeugt einen komprimierten Dump der aktuell konfigurierten Anwendungsdatenbank."
            >
                <x-slot:actions>
                    <x-button
                        label="Dump herunterladen"
                        link="{{ route('admin.datenbank.dump') }}"
                        icon="o-arrow-down-tray"
                        class="btn-primary"
                    />
                </x-slot:actions>

                <dl class="grid gap-3 text-sm sm:grid-cols-2">
                    <div class="rounded-lg bg-base-200/70 p-3">
                        <dt class="font-semibold text-base-content/70">Format</dt>
                        <dd class="mt-1 text-base-content">.sql.gz</dd>
                    </div>
                    <div class="rounded-lg bg-base-200/70 p-3">
                        <dt class="font-semibold text-base-content/70">Speicherort</dt>
                        <dd class="mt-1 text-base-content">Privater temporärer Storage</dd>
                    </div>
                </dl>
            </x-ui.panel>

            <x-ui.panel
                title="Dump einspielen"
                description="Ersetzt die aktuelle Datenbank durch den hochgeladenen SQL-Dump und erzeugt vorher automatisch eine Sicherung."
            >
                <form method="POST" action="{{ route('admin.datenbank.restore') }}" enctype="multipart/form-data" class="space-y-5">
                    @csrf

                    <x-alert icon="o-exclamation-triangle" class="alert-warning" role="note">
                        Dieser Vorgang überschreibt Daten. Vor dem Import wird automatisch ein Vorab-Dump gespeichert.
                    </x-alert>

                    <div>
                        <label for="dump" class="mb-2 block text-sm font-semibold text-base-content">SQL-Dump</label>
                        <input
                            id="dump"
                            name="dump"
                            type="file"
                            accept=".sql,.sql.gz"
                            required
                            class="file-input file-input-bordered w-full"
                        />
                        <p class="mt-2 text-sm text-base-content/65">
                            Effektive Upload-Grenze: {{ $formatBytes($effectiveUploadBytes) }}
                        </p>
                    </div>

                    <div>
                        <label for="confirmation" class="mb-2 block text-sm font-semibold text-base-content">Bestätigung</label>
                        <input
                            id="confirmation"
                            name="confirmation"
                            type="text"
                            value="{{ old('confirmation') }}"
                            autocomplete="off"
                            required
                            class="input input-bordered w-full"
                            placeholder="{{ $confirmationText }}"
                        />
                        <p class="mt-2 text-sm text-base-content/65">
                            Erforderlicher Text: <span class="font-mono">{{ $confirmationText }}</span>
                        </p>
                    </div>

                    <div class="flex justify-end">
                        <x-button type="submit" icon="o-arrow-up-tray" class="btn-error">
                            Dump einspielen
                        </x-button>
                    </div>
                </form>
            </x-ui.panel>
        </div>

        <x-ui.panel title="Aktuelle Limits" description="Die harte Grenze ergibt sich aus PHP, App-Konfiguration, bekanntem Proxy-Limit und freiem privaten Storage.">
            <dl class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                <div class="rounded-lg bg-base-200/70 p-4">
                    <dt class="text-sm font-semibold text-base-content/70">Effektiver Upload</dt>
                    <dd class="mt-1 text-lg font-semibold">{{ $formatBytes($effectiveUploadBytes) }}</dd>
                </div>
                <div class="rounded-lg bg-base-200/70 p-4">
                    <dt class="text-sm font-semibold text-base-content/70">PHP upload_max_filesize</dt>
                    <dd class="mt-1 text-lg font-semibold">{{ $formatBytes($limits['php_upload_max_filesize_bytes'] ?? null) }}</dd>
                </div>
                <div class="rounded-lg bg-base-200/70 p-4">
                    <dt class="text-sm font-semibold text-base-content/70">PHP post_max_size</dt>
                    <dd class="mt-1 text-lg font-semibold">{{ $formatBytes($limits['php_post_max_size_bytes'] ?? null) }}</dd>
                </div>
                <div class="rounded-lg bg-base-200/70 p-4">
                    <dt class="text-sm font-semibold text-base-content/70">Proxy-Limit</dt>
                    <dd class="mt-1 text-lg font-semibold">{{ $formatBytes($limits['proxy_limit_bytes'] ?? null) }}</dd>
                </div>
                <div class="rounded-lg bg-base-200/70 p-4">
                    <dt class="text-sm font-semibold text-base-content/70">Freier privater Storage</dt>
                    <dd class="mt-1 text-lg font-semibold">{{ $formatBytes($limits['storage_free_bytes'] ?? null) }}</dd>
                </div>
                <div class="rounded-lg bg-base-200/70 p-4">
                    <dt class="text-sm font-semibold text-base-content/70">Max. entpackte SQL-Datei</dt>
                    <dd class="mt-1 text-lg font-semibold">{{ $formatBytes($limits['max_uncompressed_bytes'] ?? null) }}</dd>
                </div>
            </dl>

            <div class="mt-6 overflow-x-auto">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Quelle</th>
                            <th>Grenze</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach(($limits['candidates'] ?? []) as $label => $bytes)
                            <tr>
                                <td class="font-mono text-xs">{{ $label }}</td>
                                <td>{{ $formatBytes($bytes) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-ui.panel>

        <x-ui.panel title="Vorab-Dump" description="Der neueste automatisch erzeugte Vorab-Dump bleibt privat im Storage und wird nach Ablauf der Aufbewahrungsfrist bereinigt.">
            @if($lastPreRestoreDump)
                <dl class="grid gap-3 text-sm md:grid-cols-3">
                    <div class="rounded-lg bg-base-200/70 p-3">
                        <dt class="font-semibold text-base-content/70">Datei</dt>
                        <dd class="mt-1 break-all font-mono text-xs">{{ $lastPreRestoreDump['filename'] }}</dd>
                    </div>
                    <div class="rounded-lg bg-base-200/70 p-3">
                        <dt class="font-semibold text-base-content/70">Größe</dt>
                        <dd class="mt-1">{{ $formatBytes($lastPreRestoreDump['bytes']) }}</dd>
                    </div>
                    <div class="rounded-lg bg-base-200/70 p-3">
                        <dt class="font-semibold text-base-content/70">Zeitpunkt</dt>
                        <dd class="mt-1">{{ \Carbon\Carbon::createFromTimestamp($lastPreRestoreDump['created_at'])->format('d.m.Y H:i') }}</dd>
                    </div>
                </dl>
            @else
                <x-alert icon="o-information-circle" class="alert-info" role="status">
                    Noch kein automatischer Vorab-Dump vorhanden.
                </x-alert>
            @endif
        </x-ui.panel>
    </x-member-page>
</x-app-layout>
