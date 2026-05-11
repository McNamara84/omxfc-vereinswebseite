<x-app-layout :title="$auktion->titel" description="Transparenter Verlauf einer Vereinsauktion mit allen sichtbaren Geboten.">
    <div class="mx-auto flex max-w-5xl flex-col gap-6 px-4 py-8 sm:px-6 lg:px-8">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="space-y-2">
                <p class="text-sm font-semibold uppercase tracking-[0.24em] text-base-content/50">Auktion</p>
                <h1 class="font-display text-4xl font-semibold tracking-tight text-base-content">{{ $auktion->titel }}</h1>
                <p class="text-sm text-base-content/70">Status: {{ $auktion->status->label() }}</p>
            </div>

            <div class="flex flex-wrap gap-3">
                <a href="{{ route('auktionen.index') }}" class="btn btn-ghost">Zur Uebersicht</a>
                @can('manage', App\Models\Auktion::class)
                    <a href="{{ route('admin.auktionen.edit', $auktion) }}" class="btn btn-outline">Verwalten</a>
                @endcan
            </div>
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

        <section class="rounded-3xl border border-base-300 bg-base-100/90 p-6 shadow-sm">
            <div class="grid gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(18rem,1fr)]">
                <div>
                    @if (filled($auktion->beschreibung_markdown))
                        <div class="prose prose-sm max-w-none text-base-content/80">
                            {!! $auktion->html_beschreibung !!}
                        </div>
                    @else
                        <p class="text-sm text-base-content/65">Zu dieser Auktion wurde noch keine Beschreibung hinterlegt.</p>
                    @endif
                </div>

                <div class="rounded-2xl border border-base-300 bg-base-200/50 p-4">
                    <h2 class="text-lg font-semibold text-base-content">Auktionsstand</h2>
                    <dl class="mt-4 space-y-3 text-sm text-base-content/75">
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
                            <dt class="font-semibold text-base-content">Naechstes Mindestgebot</dt>
                            <dd>{{ $auktion->naechstesMindestgebot() }}</dd>
                        </div>
                        @if ($auktion->status === App\Enums\AuktionsStatus::Verkauft)
                            <div>
                                <dt class="font-semibold text-base-content">Zuschlag</dt>
                                <dd>
                                    {{ $auktion->verkauftesGebot?->bieter_name ?? 'unbekannt' }}
                                    fuer {{ $auktion->verkauftesGebot?->formatierter_betrag ?? 'offen' }}
                                </dd>
                            </div>
                        @elseif ($auktion->status === App\Enums\AuktionsStatus::NichtVerkauft)
                            <div>
                                <dt class="font-semibold text-base-content">Ergebnis</dt>
                                <dd>Nicht verkauft</dd>
                            </div>
                        @endif
                    </dl>
                </div>
            </div>
        </section>

        @can('bid', $auktion)
            @if ($auktion->kannGeboteAnnehmen())
                <section class="rounded-3xl border border-base-300 bg-base-100/90 p-6 shadow-sm">
                    <h2 class="text-2xl font-semibold text-base-content">Gebot abgeben</h2>
                    <p class="mt-2 text-sm text-base-content/70">Das Gebot muss mindestens {{ $auktion->naechstesMindestgebot() }} betragen.</p>

                    <form action="{{ route('auktionen.gebote.store', $auktion) }}" method="POST" class="mt-5 flex flex-col gap-4 sm:flex-row sm:items-end">
                        @csrf
                        <div class="flex-1">
                            <label for="betrag" class="mb-2 block text-sm font-medium text-base-content">Gebot in Euro</label>
                            <input
                                id="betrag"
                                name="betrag"
                                type="number"
                                step="0.01"
                                min="{{ \App\Support\Euro::decimal($auktion->naechstesMindestgebotCent()) }}"
                                value="{{ old('betrag', \App\Support\Euro::decimal($auktion->naechstesMindestgebotCent())) }}"
                                class="input input-bordered w-full"
                                required
                            >
                        </div>

                        <button type="submit" class="btn btn-primary">Gebot speichern</button>
                    </form>
                </section>
            @endif
        @endcan

        <section class="rounded-3xl border border-base-300 bg-base-100/90 p-6 shadow-sm">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h2 class="text-2xl font-semibold text-base-content">Gebotsverlauf</h2>
                    <p class="mt-2 text-sm text-base-content/70">Wer wann wie viel geboten hat, bleibt fuer alle berechtigten Mitglieder sichtbar.</p>
                </div>
                <span class="badge badge-outline">{{ $auktion->gebotsverlauf()->count() }} Eintraege</span>
            </div>

            @if ($auktion->gebotsverlauf()->isEmpty())
                <p class="mt-5 text-sm text-base-content/65">Noch keine Gebote vorhanden.</p>
            @else
                <ol class="mt-5 space-y-3">
                    @foreach ($auktion->gebotsverlauf() as $gebot)
                        <li class="rounded-2xl border border-base-300 bg-base-200/45 px-4 py-3">
                            <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <p class="font-semibold text-base-content">{{ $gebot->bieter_name }}</p>
                                    <p class="text-xs text-base-content/60">{{ $gebot->created_at?->locale('de')->isoFormat('DD.MM.YYYY HH:mm') }}</p>
                                </div>
                                <p class="text-lg font-semibold text-base-content">{{ $gebot->formatierter_betrag }}</p>
                            </div>
                        </li>
                    @endforeach
                </ol>
            @endif
        </section>
    </div>
</x-app-layout>