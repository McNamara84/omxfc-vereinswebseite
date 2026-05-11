<x-app-layout title="Auktionen verwalten" description="Verwalte Vereinsauktionen, Gebotsstatus und Zuschlaege zentral im Vorstandsbereich.">
    <div class="mx-auto flex max-w-7xl flex-col gap-6 px-4 py-8 sm:px-6 lg:px-8">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div class="space-y-2">
                <p class="text-sm font-semibold uppercase tracking-[0.24em] text-base-content/50">Vorstand</p>
                <h1 class="font-display text-4xl font-semibold tracking-tight text-base-content">Auktionen verwalten</h1>
                <p class="max-w-3xl text-sm leading-relaxed text-base-content/70">
                    Neue Auktionen anlegen, Gebote nachvollziehen und den Versteigerungsablauf per Zum ersten, Zum zweiten und Verkauf steuern.
                </p>
            </div>

            <a href="{{ route('admin.auktionen.create') }}" class="btn btn-primary">Neue Auktion</a>
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

        @if ($auktionen->isEmpty())
            <div class="rounded-3xl border border-dashed border-base-300 bg-base-100/70 px-6 py-10 text-sm text-base-content/70">
                Es sind noch keine Auktionen vorhanden.
            </div>
        @else
            <div class="grid gap-5 lg:grid-cols-2">
                @foreach ($auktionen as $auktion)
                    <article class="rounded-3xl border border-base-300 bg-base-100/90 p-6 shadow-sm">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h2 class="text-2xl font-semibold text-base-content">{{ $auktion->titel }}</h2>
                                <p class="mt-2 text-sm text-base-content/70">{{ $auktion->status->label() }}</p>
                            </div>
                            <span class="badge badge-outline">{{ $auktion->gebote_count }} Gebote</span>
                        </div>

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
                                <dt class="font-semibold text-base-content">Hoechstbietend</dt>
                                <dd>{{ $auktion->hoechstgebot()?->bieter_name ?? 'Noch kein Gebot' }}</dd>
                            </div>
                        </dl>

                        <div class="mt-5 flex flex-wrap gap-2">
                            <a href="{{ route('admin.auktionen.edit', $auktion) }}" class="btn btn-primary btn-sm">Bearbeiten</a>
                            <a href="{{ route('auktionen.show', $auktion) }}" class="btn btn-ghost btn-sm">Oeffentliche Ansicht</a>

                            @can('call', $auktion)
                                @if ($auktion->kannZumErstenAufgerufenWerden())
                                    <form action="{{ route('admin.auktionen.zum-ersten', $auktion) }}" method="POST">
                                        @csrf
                                        <button type="submit" class="btn btn-outline btn-sm">Zum ersten...</button>
                                    </form>
                                @endif

                                @if ($auktion->kannZumZweitenAufgerufenWerden())
                                    <form action="{{ route('admin.auktionen.zum-zweiten', $auktion) }}" method="POST">
                                        @csrf
                                        <button type="submit" class="btn btn-outline btn-sm">Zum zweiten...</button>
                                    </form>
                                @endif

                                @if ($auktion->status === App\Enums\AuktionsStatus::ZumZweiten)
                                    @if ($auktion->hoechstgebot())
                                        <form action="{{ route('admin.auktionen.verkaufen', $auktion) }}" method="POST">
                                            @csrf
                                            <button type="submit" class="btn btn-success btn-sm">Verkauft an...</button>
                                        </form>
                                    @endif

                                    <form action="{{ route('admin.auktionen.nicht-verkauft', $auktion) }}" method="POST">
                                        @csrf
                                        <button type="submit" class="btn btn-warning btn-sm">Nicht verkauft</button>
                                    </form>
                                @endif
                            @endcan

                            @can('delete', $auktion)
                                <form action="{{ route('admin.auktionen.destroy', $auktion) }}" method="POST" onsubmit="return confirm('Auktion wirklich loeschen?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-error btn-outline btn-sm">Loeschen</button>
                                </form>
                            @endcan
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </div>
</x-app-layout>