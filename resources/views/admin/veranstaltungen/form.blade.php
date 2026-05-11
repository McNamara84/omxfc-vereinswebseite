<x-app-layout :title="$isCreate ? 'Veranstaltung anlegen' : $veranstaltung->titel" description="Pflege strukturierte Veranstaltungsdaten und freie Markdown-Abschnitte.">
    <div class="mx-auto flex max-w-7xl flex-col gap-6 px-4 py-8 sm:px-6 lg:px-8">
        <x-ui.page-header
            eyebrow="Adminbereich"
            :title="$isCreate ? 'Neue Veranstaltung' : 'Veranstaltung bearbeiten'"
            :description="$isCreate ? 'Lege eine neue öffentliche oder interne Veranstaltung an.' : 'Bearbeite Inhalte, Module und Zeiträume dieser Veranstaltung.'"
        >
            <x-slot:actions>
                <x-button label="Zur Übersicht" link="{{ route('admin.veranstaltungen.index') }}" class="btn-ghost" />
                @unless ($isCreate)
                    <x-button label="Öffentliche Seite" link="{{ route('veranstaltungen.show', $veranstaltung) }}" class="btn-ghost" />
                @endunless
            </x-slot:actions>
        </x-ui.page-header>

        @if (session('success'))
            <x-alert icon="o-check-circle" class="alert-success" dismissible>
                {{ session('success') }}
            </x-alert>
        @endif

        @if ($errors->any())
            <x-alert icon="o-exclamation-triangle" class="alert-error">
                <ul class="space-y-1 text-sm">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </x-alert>
        @endif

        <x-ui.panel :title="$isCreate ? 'Grunddaten' : 'Grunddaten und Module'" description="Alle Felder sind ohne Codeänderung anpassbar.">
            <form method="POST" action="{{ $isCreate ? route('admin.veranstaltungen.store') : route('admin.veranstaltungen.update', $veranstaltung) }}" class="space-y-6">
                @csrf
                @unless ($isCreate)
                    @method('PUT')
                @endunless

                <div class="grid gap-4 md:grid-cols-2">
                    <label class="form-control w-full">
                        <span class="label-text mb-1 block text-sm font-medium">Titel</span>
                        <input name="titel" value="{{ old('titel', $veranstaltung->titel) }}" required class="input input-bordered w-full" />
                    </label>
                    <label class="form-control w-full">
                        <span class="label-text mb-1 block text-sm font-medium">Slug</span>
                        <input name="slug" value="{{ old('slug', $veranstaltung->slug) }}" required class="input input-bordered w-full" />
                    </label>
                    <label class="form-control w-full">
                        <span class="label-text mb-1 block text-sm font-medium">Veranstaltungsart</span>
                        <input name="veranstaltungsart" value="{{ old('veranstaltungsart', $veranstaltung->veranstaltungsart) }}" class="input input-bordered w-full" />
                    </label>
                    <label class="form-control w-full">
                        <span class="label-text mb-1 block text-sm font-medium">Status</span>
                        <select name="status" class="select select-bordered w-full">
                            @foreach (['entwurf' => 'Entwurf', 'veroeffentlicht' => 'Veröffentlicht', 'archiviert' => 'Archiviert'] as $value => $label)
                                <option value="{{ $value }}" @selected(old('status', $veranstaltung->status) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <label class="form-control w-full md:col-span-2">
                        <span class="label-text mb-1 block text-sm font-medium">Untertitel</span>
                        <input name="untertitel" value="{{ old('untertitel', $veranstaltung->untertitel) }}" class="input input-bordered w-full" />
                    </label>
                    <label class="form-control w-full md:col-span-2">
                        <span class="label-text mb-1 block text-sm font-medium">Teaser</span>
                        <textarea name="teaser" rows="3" class="textarea textarea-bordered w-full">{{ old('teaser', $veranstaltung->teaser) }}</textarea>
                    </label>
                    <label class="form-control w-full md:col-span-2">
                        <span class="label-text mb-1 block text-sm font-medium">Beschreibung</span>
                        <textarea name="beschreibung" rows="8" class="textarea textarea-bordered w-full">{{ old('beschreibung', $veranstaltung->beschreibung) }}</textarea>
                    </label>
                </div>

                <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    <label class="form-control w-full">
                        <span class="label-text mb-1 block text-sm font-medium">Beginn</span>
                        <input type="datetime-local" name="datum_von" value="{{ old('datum_von', $veranstaltung->datum_von?->format('Y-m-d\TH:i')) }}" class="input input-bordered w-full" />
                    </label>
                    <label class="form-control w-full">
                        <span class="label-text mb-1 block text-sm font-medium">Ende</span>
                        <input type="datetime-local" name="datum_bis" value="{{ old('datum_bis', $veranstaltung->datum_bis?->format('Y-m-d\TH:i')) }}" class="input input-bordered w-full" />
                    </label>
                    <label class="form-control w-full">
                        <span class="label-text mb-1 block text-sm font-medium">Sortierung</span>
                        <input type="number" min="0" name="sort_order" value="{{ old('sort_order', $veranstaltung->sort_order) }}" class="input input-bordered w-full" />
                    </label>
                    <label class="form-control w-full">
                        <span class="label-text mb-1 block text-sm font-medium">Ort</span>
                        <input name="ort_name" value="{{ old('ort_name', $veranstaltung->ort_name) }}" class="input input-bordered w-full" />
                    </label>
                    <label class="form-control w-full md:col-span-2">
                        <span class="label-text mb-1 block text-sm font-medium">Adresse / Hinweise</span>
                        <input name="ort_adresse" value="{{ old('ort_adresse', $veranstaltung->ort_adresse) }}" class="input input-bordered w-full" />
                    </label>
                    <label class="form-control w-full md:col-span-2 lg:col-span-3">
                        <span class="label-text mb-1 block text-sm font-medium">Maps-URL</span>
                        <input name="maps_url" value="{{ old('maps_url', $veranstaltung->maps_url) }}" class="input input-bordered w-full" />
                    </label>
                </div>

                <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    <label class="flex items-start gap-3 rounded-box border border-base-300 p-4">
                        <input type="checkbox" name="ist_highlight" value="1" @checked(old('ist_highlight', $veranstaltung->ist_highlight)) class="checkbox mt-1" />
                        <span><span class="block font-medium">Als aktuelle Hauptveranstaltung hervorheben</span><span class="block text-sm text-base-content/70">Wird für generische Links und Redirects bevorzugt.</span></span>
                    </label>
                    <label class="flex items-start gap-3 rounded-box border border-base-300 p-4">
                        <input type="checkbox" name="anmeldung_aktiv" value="1" @checked(old('anmeldung_aktiv', $veranstaltung->anmeldung_aktiv)) class="checkbox mt-1" />
                        <span><span class="block font-medium">Anmeldung aktiv</span><span class="block text-sm text-base-content/70">Öffnet das Formular auf der Veranstaltungsseite.</span></span>
                    </label>
                    <label class="flex items-start gap-3 rounded-box border border-base-300 p-4">
                        <input type="checkbox" name="zahlung_aktiv" value="1" @checked(old('zahlung_aktiv', $veranstaltung->zahlung_aktiv)) class="checkbox mt-1" />
                        <span><span class="block font-medium">Zahlung aktiv</span><span class="block text-sm text-base-content/70">Schaltet Gastgebühr und Zahlungsanzeige frei.</span></span>
                    </label>
                    <label class="flex items-start gap-3 rounded-box border border-base-300 p-4">
                        <input type="checkbox" name="tshirt_aktiv" value="1" @checked(old('tshirt_aktiv', $veranstaltung->tshirt_aktiv)) class="checkbox mt-1" />
                        <span><span class="block font-medium">T-Shirt-Modul aktiv</span><span class="block text-sm text-base-content/70">Ermöglicht T-Shirt-Bestellungen samt Deadline.</span></span>
                    </label>
                    <label class="flex items-start gap-3 rounded-box border border-base-300 p-4">
                        <input type="checkbox" name="vip_autoren_aktiv" value="1" @checked(old('vip_autoren_aktiv', $veranstaltung->vip_autoren_aktiv)) class="checkbox mt-1" />
                        <span><span class="block font-medium">VIP-Autoren-Modul aktiv</span><span class="block text-sm text-base-content/70">Zeigt und verwaltet eine eigene Gästeliste.</span></span>
                    </label>
                </div>

                <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    <label class="form-control w-full">
                        <span class="label-text mb-1 block text-sm font-medium">Anmeldung ab</span>
                        <input type="datetime-local" name="anmeldung_start" value="{{ old('anmeldung_start', $veranstaltung->anmeldung_start?->format('Y-m-d\TH:i')) }}" class="input input-bordered w-full" />
                    </label>
                    <label class="form-control w-full">
                        <span class="label-text mb-1 block text-sm font-medium">Anmeldung bis</span>
                        <input type="datetime-local" name="anmeldung_ende" value="{{ old('anmeldung_ende', $veranstaltung->anmeldung_ende?->format('Y-m-d\TH:i')) }}" class="input input-bordered w-full" />
                    </label>
                    <label class="form-control w-full">
                        <span class="label-text mb-1 block text-sm font-medium">T-Shirt-Deadline</span>
                        <input type="datetime-local" name="tshirt_deadline" value="{{ old('tshirt_deadline', $veranstaltung->tshirt_deadline?->format('Y-m-d\TH:i')) }}" class="input input-bordered w-full" />
                    </label>
                    <label class="form-control w-full">
                        <span class="label-text mb-1 block text-sm font-medium">Gastgebühr</span>
                        <input type="number" step="0.01" min="0" name="gastgebuehr" value="{{ old('gastgebuehr', $veranstaltung->gastgebuehr) }}" class="input input-bordered w-full" />
                    </label>
                    <label class="form-control w-full">
                        <span class="label-text mb-1 block text-sm font-medium">T-Shirt-Preis</span>
                        <input type="number" step="0.01" min="0" name="tshirt_preis" value="{{ old('tshirt_preis', $veranstaltung->tshirt_preis) }}" class="input input-bordered w-full" />
                    </label>
                    <label class="form-control w-full">
                        <span class="label-text mb-1 block text-sm font-medium">Benachrichtigungs-E-Mail</span>
                        <input type="email" name="benachrichtigungs_email" value="{{ old('benachrichtigungs_email', $veranstaltung->benachrichtigungs_email) }}" class="input input-bordered w-full" />
                    </label>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <label class="form-control w-full">
                        <span class="label-text mb-1 block text-sm font-medium">SEO-Titel</span>
                        <input name="seo_title" value="{{ old('seo_title', $veranstaltung->seo_title) }}" class="input input-bordered w-full" />
                    </label>
                    <label class="form-control w-full">
                        <span class="label-text mb-1 block text-sm font-medium">SEO-Beschreibung</span>
                        <textarea name="seo_description" rows="3" class="textarea textarea-bordered w-full">{{ old('seo_description', $veranstaltung->seo_description) }}</textarea>
                    </label>
                </div>

                <div class="flex justify-end">
                    <button type="submit" class="btn btn-primary rounded-full">{{ $isCreate ? 'Veranstaltung anlegen' : 'Änderungen speichern' }}</button>
                </div>
            </form>
        </x-ui.panel>

        @unless ($isCreate)
            <x-ui.panel title="Freie Inhalte" description="Ergänze beliebig viele Markdown-Abschnitte für Programm, FAQ, Hinweise oder Archivtexte.">
                <div class="space-y-4">
                    @foreach ($abschnitte as $abschnitt)
                        <form method="POST" action="{{ route('admin.veranstaltungen.abschnitte.update', [$veranstaltung, $abschnitt]) }}" class="rounded-box border border-base-300 p-4 space-y-3">
                            @csrf
                            @method('PUT')
                            <div class="grid gap-4 md:grid-cols-3">
                                <label class="form-control w-full">
                                    <span class="label-text mb-1 block text-sm font-medium">Titel</span>
                                    <input name="titel" value="{{ old('titel', $abschnitt->titel) }}" class="input input-bordered w-full" />
                                </label>
                                <label class="form-control w-full">
                                    <span class="label-text mb-1 block text-sm font-medium">Schlüssel</span>
                                    <input name="schluessel" value="{{ old('schluessel', $abschnitt->schluessel) }}" class="input input-bordered w-full" />
                                </label>
                                <label class="form-control w-full">
                                    <span class="label-text mb-1 block text-sm font-medium">Sortierung</span>
                                    <input type="number" min="0" name="sort_order" value="{{ old('sort_order', $abschnitt->sort_order) }}" class="input input-bordered w-full" />
                                </label>
                            </div>

                            <label class="form-control w-full">
                                <span class="label-text mb-1 block text-sm font-medium">Markdown-Inhalt</span>
                                <textarea name="markdown_inhalt" rows="6" class="textarea textarea-bordered w-full">{{ old('markdown_inhalt', $abschnitt->markdown_inhalt) }}</textarea>
                            </label>

                            <label class="flex items-center gap-3 text-sm">
                                <input type="checkbox" name="is_visible" value="1" @checked(old('is_visible', $abschnitt->is_visible)) class="checkbox" />
                                Abschnitt öffentlich anzeigen
                            </label>

                            <div class="flex flex-wrap justify-end gap-2">
                                <button type="submit" class="btn btn-primary btn-sm">Abschnitt speichern</button>
                            </div>
                        </form>

                        <form method="POST" action="{{ route('admin.veranstaltungen.abschnitte.destroy', [$veranstaltung, $abschnitt]) }}" class="flex justify-end">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-ghost btn-sm text-error">Abschnitt löschen</button>
                        </form>
                    @endforeach

                    <form method="POST" action="{{ route('admin.veranstaltungen.abschnitte.store', $veranstaltung) }}" class="rounded-box border border-dashed border-base-300 p-4 space-y-3">
                        @csrf
                        <h3 class="text-lg font-semibold">Neuen Abschnitt hinzufügen</h3>
                        <div class="grid gap-4 md:grid-cols-3">
                            <label class="form-control w-full">
                                <span class="label-text mb-1 block text-sm font-medium">Titel</span>
                                <input name="titel" class="input input-bordered w-full" />
                            </label>
                            <label class="form-control w-full">
                                <span class="label-text mb-1 block text-sm font-medium">Schlüssel</span>
                                <input name="schluessel" class="input input-bordered w-full" />
                            </label>
                            <label class="form-control w-full">
                                <span class="label-text mb-1 block text-sm font-medium">Sortierung</span>
                                <input type="number" min="0" name="sort_order" value="{{ $abschnitte->max('sort_order') + 1 }}" class="input input-bordered w-full" />
                            </label>
                        </div>

                        <label class="form-control w-full">
                            <span class="label-text mb-1 block text-sm font-medium">Markdown-Inhalt</span>
                            <textarea name="markdown_inhalt" rows="6" class="textarea textarea-bordered w-full"></textarea>
                        </label>

                        <label class="flex items-center gap-3 text-sm">
                            <input type="checkbox" name="is_visible" value="1" checked class="checkbox" />
                            Abschnitt öffentlich anzeigen
                        </label>

                        <div class="flex justify-end">
                            <button type="submit" class="btn btn-primary btn-sm">Abschnitt anlegen</button>
                        </div>
                    </form>
                </div>
            </x-ui.panel>
        @endunless
    </div>
</x-app-layout>