@php($basisFelderGesperrt = $auktion->exists && $auktion->basisFelderGesperrt())

<x-app-layout :title="$isCreate ? 'Auktion anlegen' : 'Auktion bearbeiten'" description="Titel, Markdown-Beschreibung und Bietregeln der Auktion verwalten.">
    <div class="mx-auto flex max-w-5xl flex-col gap-6 px-4 py-8 sm:px-6 lg:px-8">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div class="space-y-2">
                <p class="text-sm font-semibold uppercase tracking-[0.24em] text-base-content/50">Vorstand</p>
                <h1 class="font-display text-4xl font-semibold tracking-tight text-base-content">
                    {{ $isCreate ? 'Neue Auktion' : 'Auktion bearbeiten' }}
                </h1>
                <p class="max-w-3xl text-sm leading-relaxed text-base-content/70">
                    {{ $isCreate ? 'Lege eine neue Vereinsauktion mit Startbetrag und Mindestschritt an.' : 'Passe Titel und Beschreibung an. Startbetrag und Mindestschritt bleiben nach dem ersten Gebot gesperrt.' }}
                </p>
            </div>

            <div class="flex flex-wrap gap-3">
                <a href="{{ route('admin.auktionen.index') }}" class="btn btn-ghost">Zur Verwaltung</a>
                @if (! $isCreate)
                    <a href="{{ route('auktionen.show', $auktion) }}" class="btn btn-outline">Zur Auktion</a>
                @endif
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
            <form action="{{ $isCreate ? route('admin.auktionen.store') : route('admin.auktionen.update', $auktion) }}" method="POST" class="space-y-6">
                @csrf
                @unless ($isCreate)
                    @method('PUT')
                @endunless

                <div class="grid gap-6 md:grid-cols-2">
                    <div class="md:col-span-2">
                        <label for="titel" class="mb-2 block text-sm font-medium text-base-content">Titel</label>
                        <input id="titel" name="titel" type="text" value="{{ old('titel', $auktion->titel) }}" class="input input-bordered w-full" required maxlength="255">
                    </div>

                    <div class="md:col-span-2">
                        <label for="beschreibung_markdown" class="mb-2 block text-sm font-medium text-base-content">Beschreibung in Markdown</label>
                        <textarea id="beschreibung_markdown" name="beschreibung_markdown" rows="8" class="textarea textarea-bordered w-full">{{ old('beschreibung_markdown', $auktion->beschreibung_markdown) }}</textarea>
                        <p class="mt-2 text-xs text-base-content/60">Markdown wird beim Anzeigen bereinigt gerendert.</p>
                    </div>

                    <div>
                        <label for="startbetrag" class="mb-2 block text-sm font-medium text-base-content">Startbetrag in Euro</label>
                        <input
                            id="startbetrag"
                            name="startbetrag"
                            type="text"
                            inputmode="decimal"
                            value="{{ old('startbetrag', \App\Support\Euro::decimal((int) $auktion->startbetrag_cent)) }}"
                            placeholder="0,00"
                            class="input input-bordered w-full"
                            @readonly($basisFelderGesperrt)
                            required
                        >
                        @if ($basisFelderGesperrt)
                            <p class="mt-2 text-xs text-base-content/60">Der Startbetrag ist nach dem ersten Gebot gesperrt.</p>
                        @endif
                    </div>

                    <div>
                        <label for="mindestschritt" class="mb-2 block text-sm font-medium text-base-content">Mindestschritt in Euro</label>
                        <input
                            id="mindestschritt"
                            name="mindestschritt"
                            type="text"
                            inputmode="decimal"
                            value="{{ old('mindestschritt', \App\Support\Euro::decimal((int) $auktion->mindestschritt_cent)) }}"
                            placeholder="1,00"
                            class="input input-bordered w-full"
                            @readonly($basisFelderGesperrt)
                            required
                        >
                        @if ($basisFelderGesperrt)
                            <p class="mt-2 text-xs text-base-content/60">Der Mindestschritt ist nach dem ersten Gebot gesperrt.</p>
                        @endif
                    </div>
                </div>

                <div class="flex flex-wrap gap-3">
                    <button type="submit" class="btn btn-primary">{{ $isCreate ? 'Auktion anlegen' : 'Auktion speichern' }}</button>
                    <a href="{{ route('admin.auktionen.index') }}" class="btn btn-ghost">Abbrechen</a>
                </div>
            </form>
        </section>

        @if (! $isCreate)
            <section class="rounded-3xl border border-base-300 bg-base-100/90 p-6 shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h2 class="text-2xl font-semibold text-base-content">Ablauf</h2>
                        <p class="mt-2 text-sm text-base-content/70">Aktueller Status: {{ $auktion->status->label() }}</p>
                    </div>

                    @can('call', $auktion)
                        <div class="flex flex-wrap gap-2">
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
                        </div>
                    @endcan
                </div>
            </section>

            <section class="rounded-3xl border border-base-300 bg-base-100/90 p-6 shadow-sm">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-2xl font-semibold text-base-content">Gebotsverlauf</h2>
                        <p class="mt-2 text-sm text-base-content/70">Der Verlauf bleibt sichtbar und kann nicht nachträglich manipuliert werden.</p>
                    </div>
                    <span class="badge badge-outline">{{ $auktion->gebotsverlauf()->count() }} Einträge</span>
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
        @endif
    </div>
</x-app-layout>