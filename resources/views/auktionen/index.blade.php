<x-app-layout title="Auktionen" description="Laufende Vereinsauktionen mit transparentem Gebotsverlauf und Archiv abgeschlossener Verkäufe.">
    <div class="mx-auto flex max-w-7xl flex-col gap-6 px-4 py-8 sm:px-6 lg:px-8">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div class="space-y-2">
                <p class="text-sm font-semibold uppercase tracking-[0.24em] text-base-content/50">Community</p>
                <h1 class="font-display text-4xl font-semibold tracking-tight text-base-content">Auktionen</h1>
                <p class="max-w-3xl text-sm leading-relaxed text-base-content/70">
                    Alle laufenden und abgeschlossenen Auktionen inklusive offenem Gebotsverlauf. Automatisches Bieten gibt es nicht.
                </p>
            </div>

            @can('manage', App\Models\Auktion::class)
                <a href="{{ route('admin.auktionen.index') }}" class="btn btn-outline">Auktionen verwalten</a>
            @endcan
        </div>

        @if (session('success'))
            <x-alert icon="o-check-circle" class="alert-success" dismissible>
                {{ session('success') }}
            </x-alert>
        @endif

        @if ($errors->any())
            <x-alert icon="o-exclamation-triangle" class="alert-error">
                <ul class="list-disc pl-5 text-sm">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </x-alert>
        @endif

        <section class="space-y-4">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h2 class="text-2xl font-semibold text-base-content">Laufende Auktionen</h2>
                    <p class="text-sm text-base-content/65">Gebote bleiben offen sichtbar, solange die Auktion noch nicht beendet ist.</p>
                </div>
            </div>

            @if ($aktiveAuktionen->isEmpty())
                <div class="rounded-3xl border border-dashed border-base-300 bg-base-100/70 px-6 py-10 text-sm text-base-content/70">
                    Aktuell laufen keine Auktionen.
                </div>
            @else
                <div class="grid gap-5 lg:grid-cols-2">
                    @foreach ($aktiveAuktionen as $auktion)
                        <article class="rounded-3xl border border-base-300 bg-base-100/90 p-6 shadow-sm">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <h3 class="text-2xl font-semibold text-base-content">{{ $auktion->titel }}</h3>
                                    <p class="mt-2 text-sm text-base-content/70">Status: {{ $auktion->status->label() }}</p>
                                </div>
                                <span class="badge badge-outline">{{ $auktion->gebote_count }} Gebote</span>
                            </div>

                            @if (filled($auktion->beschreibung_markdown))
                                <div class="prose prose-sm mt-4 max-w-none text-base-content/80">
                                    {!! $auktion->html_beschreibung !!}
                                </div>
                            @endif

                            <dl class="mt-5 grid gap-3 text-sm text-base-content/75 sm:grid-cols-2">
                                <div>
                                    <dt class="font-semibold text-base-content">Startbetrag</dt>
                                    <dd>{{ $auktion->formatierter_startbetrag }}</dd>
                                </div>
                                <div>
                                    <dt class="font-semibold text-base-content">Mindestschritt</dt>
                                    <dd>{{ $auktion->formatierter_mindestschritt }}</dd>
                                </div>
                                <div>
                                    <dt class="font-semibold text-base-content">Aktueller Stand</dt>
                                    <dd>{{ $auktion->aktuellerPreis() }}</dd>
                                </div>
                                <div>
                                    <dt class="font-semibold text-base-content">Nächstes Mindestgebot</dt>
                                    <dd>{{ $auktion->naechstesMindestgebot() }}</dd>
                                </div>
                            </dl>

                            <div class="mt-5 flex flex-wrap gap-3">
                                <a href="{{ route('auktionen.show', $auktion) }}" class="btn btn-primary btn-sm">Zur Auktion</a>
                                @can('manage', App\Models\Auktion::class)
                                    <a href="{{ route('admin.auktionen.edit', $auktion) }}" class="btn btn-ghost btn-sm">Bearbeiten</a>
                                @endcan
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </section>

        <section class="space-y-4">
            <div>
                <h2 class="text-2xl font-semibold text-base-content">Archiv</h2>
                <p class="text-sm text-base-content/65">Abgeschlossene Auktionen bleiben mit Ergebnis und Gebotsverlauf nachvollziehbar.</p>
            </div>

            @if ($archivierteAuktionen->isEmpty())
                <div class="rounded-3xl border border-dashed border-base-300 bg-base-100/70 px-6 py-10 text-sm text-base-content/70">
                    Noch keine abgeschlossenen Auktionen im Archiv.
                </div>
            @else
                <div class="grid gap-5 lg:grid-cols-2">
                    @foreach ($archivierteAuktionen as $auktion)
                        <article class="rounded-3xl border border-base-300 bg-base-100/90 p-6 shadow-sm">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <h3 class="text-2xl font-semibold text-base-content">{{ $auktion->titel }}</h3>
                                    <p class="mt-2 text-sm text-base-content/70">{{ $auktion->status->label() }}</p>
                                </div>
                                <span class="badge badge-outline">{{ $auktion->gebote_count }} Gebote</span>
                            </div>

                            <p class="mt-4 text-sm text-base-content/75">
                                @if ($auktion->status === App\Enums\AuktionsStatus::Verkauft)
                                    Verkauft an {{ $auktion->verkauftesGebot?->bieter_name ?? 'unbekannt' }} für {{ $auktion->verkauftesGebot?->formatierter_betrag ?? 'offen' }}.
                                @else
                                    Diese Auktion endete ohne Zuschlag.
                                @endif
                            </p>

                            <div class="mt-5 flex flex-wrap gap-3">
                                <a href="{{ route('auktionen.show', $auktion) }}" class="btn btn-primary btn-sm">Details und Verlauf</a>
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </section>
    </div>
</x-app-layout>