<x-app-layout
    :title="$veranstaltung->seo_title ?: $veranstaltung->titel"
    :description="$veranstaltung->seo_description ?: ($veranstaltung->teaser ?: $veranstaltung->untertitel)">
    <div class="mx-auto flex w-full max-w-7xl flex-col gap-8 px-4 py-8 sm:px-6 lg:px-8">
        <section class="rounded-[2rem] bg-gradient-to-br from-[#8B0116] via-[#6e0d14] to-[#3d1014] px-6 py-8 text-white shadow-xl sm:px-8 sm:py-10">
            <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                <div class="space-y-4">
                    <p class="text-xs font-semibold uppercase tracking-[0.3em] text-white/65">
                        {{ $veranstaltung->veranstaltungsart ?: 'Veranstaltung' }}
                    </p>
                    <div class="space-y-3">
                        <h1 class="font-display text-4xl font-semibold tracking-tight sm:text-5xl">{{ $veranstaltung->titel }}</h1>
                        @if ($veranstaltung->untertitel)
                            <p class="max-w-3xl text-lg text-white/85">{{ $veranstaltung->untertitel }}</p>
                        @endif
                        @if ($veranstaltung->teaser)
                            <p class="max-w-3xl text-sm leading-relaxed text-white/80 sm:text-base">{{ $veranstaltung->teaser }}</p>
                        @endif
                    </div>
                </div>

                <div class="grid gap-3 rounded-[1.5rem] border border-white/15 bg-white/10 p-4 backdrop-blur-sm sm:min-w-80">
                    @if ($veranstaltung->datum_von)
                        <div>
                            <p class="text-xs uppercase tracking-[0.2em] text-white/60">Datum</p>
                            <p class="text-lg font-semibold">{{ $veranstaltung->datum_von->locale('de')->isoFormat('D. MMMM YYYY, HH:mm') }} Uhr</p>
                        </div>
                    @endif
                    @if ($veranstaltung->ort_name)
                        <div>
                            <p class="text-xs uppercase tracking-[0.2em] text-white/60">Ort</p>
                            <p class="text-lg font-semibold">{{ $veranstaltung->ort_name }}</p>
                            @if ($veranstaltung->ort_adresse)
                                <p class="text-sm text-white/72">{{ $veranstaltung->ort_adresse }}</p>
                            @endif
                        </div>
                    @endif
                    @if ($veranstaltung->maps_url)
                        <div>
                            <a href="{{ $veranstaltung->maps_url }}" target="_blank" rel="noopener noreferrer" class="btn btn-sm rounded-full border-white text-white hover:bg-white hover:text-[#8B0116]">Route öffnen</a>
                        </div>
                    @endif
                    @if ($user?->canManageVeranstaltungen())
                        <div class="space-y-2 rounded-[1.25rem] border border-white/15 bg-white/8 p-3">
                            <p class="text-xs uppercase tracking-[0.2em] text-white/60">Verwaltung</p>
                            <div class="flex flex-wrap gap-2">
                                <a href="{{ route('admin.veranstaltungen.edit', $veranstaltung) }}" wire:navigate class="btn btn-sm rounded-full border-white text-white hover:bg-white hover:text-[#8B0116]">Veranstaltung bearbeiten</a>
                                <a href="{{ route('admin.veranstaltungen.anmeldungen', $veranstaltung) }}" wire:navigate class="btn btn-sm rounded-full bg-white text-[#8B0116] hover:bg-white/90">Anmeldeliste</a>
                                @if ($veranstaltung->vip_autoren_aktiv)
                                    <a href="{{ route('admin.veranstaltungen.vip-authors', $veranstaltung) }}" wire:navigate class="btn btn-sm rounded-full border-white text-white hover:bg-white hover:text-[#8B0116]">VIP-Autoren</a>
                                @endif
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </section>

        @if ($veranstaltung->status === 'archiviert')
            <x-alert icon="o-clock" class="alert-warning">
                Diese Veranstaltung ist archiviert. Bestehende Informationen bleiben sichtbar, neue Anmeldungen sind geschlossen.
            </x-alert>
        @endif

        @if ($vipAuthors->isNotEmpty())
            <x-ui.panel title="Gästeliste" description="Aktuell bestätigte oder angefragte Gäste für diese Veranstaltung.">
                <div class="flex flex-wrap gap-3">
                    @foreach ($vipAuthors as $author)
                        <x-badge :value="$author->display_name . ($author->is_tentative ? ' (unter Vorbehalt)' : '')" class="badge-lg border-amber-500/20 bg-amber-400/15 font-semibold text-amber-900 dark:text-amber-200" icon="o-star" />
                    @endforeach
                </div>
            </x-ui.panel>
        @endif

        <div class="grid gap-8 lg:grid-cols-[minmax(0,1.45fr)_minmax(22rem,0.95fr)]">
            <div class="space-y-6">
                @php($htmlBeschreibung = $veranstaltung->html_beschreibung)

                @if ($htmlBeschreibung !== '')
                    <x-ui.panel title="Über die Veranstaltung" description="Der aktuelle Überblick.">
                        <div class="prose max-w-none dark:prose-invert">{!! $htmlBeschreibung !!}</div>
                    </x-ui.panel>
                @endif

                @foreach ($sections as $section)
                    <x-ui.panel :title="$section->titel" description="">
                        <div class="prose max-w-none dark:prose-invert">{!! $section->html_inhalt !!}</div>
                    </x-ui.panel>
                @endforeach
            </div>

            <div>
                <div class="sticky top-6 space-y-4">
                    <x-ui.panel title="Anmeldung" description="Reserviere deinen Platz direkt online.">
                        @if(session('success'))
                            <x-alert icon="o-check-circle" class="alert-success mb-4" dismissible>
                                {{ session('success') }}
                            </x-alert>
                        @endif

                        @if ($errors->any())
                            <x-alert icon="o-exclamation-triangle" class="alert-error mb-4">
                                <ul class="space-y-1 text-sm">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </x-alert>
                        @endif

                        @if ($veranstaltung->isRegistrationOpen())
                            @guest
                                <x-alert icon="o-information-circle" class="alert-info mb-4">
                                    Wenn du Mitglied bist, kannst du dich nach dem Login direkt mit deinen Profildaten anmelden.
                                </x-alert>
                            @else
                                <x-alert icon="o-user-circle" class="alert-success mb-4">
                                    Angemeldet als {{ $user->vorname }} {{ $user->nachname }}.
                                </x-alert>
                            @endguest

                            <form method="POST" action="{{ route('veranstaltungen.anmeldung.store', $veranstaltung) }}" id="fantreffen-form" class="space-y-4">
                                @csrf

                                <div aria-hidden="true" style="position:absolute;left:-9999px;top:-9999px;">
                                    <label for="website">Website</label>
                                    <input type="text" name="website" id="website" value="" tabindex="-1" autocomplete="off" />
                                </div>

                                <input type="hidden" name="_form_token" value="{{ old('_form_token', $formLoadedAt) }}" />

                                @guest
                                    <label class="form-control w-full">
                                        <span class="label-text mb-1 block text-sm font-medium">Vorname</span>
                                        <input name="vorname" value="{{ old('vorname') }}" required class="input input-bordered w-full" data-testid="fantreffen-vorname" />
                                    </label>

                                    <label class="form-control w-full">
                                        <span class="label-text mb-1 block text-sm font-medium">Nachname</span>
                                        <input name="nachname" value="{{ old('nachname') }}" required class="input input-bordered w-full" data-testid="fantreffen-nachname" />
                                    </label>

                                    <label class="form-control w-full">
                                        <span class="label-text mb-1 block text-sm font-medium">E-Mail</span>
                                        <input type="email" name="email" value="{{ old('email') }}" required class="input input-bordered w-full" data-testid="fantreffen-email" />
                                    </label>
                                @endguest

                                <label class="form-control w-full">
                                    <span class="label-text mb-1 block text-sm font-medium">Mobilnummer</span>
                                    <input name="mobile" value="{{ old('mobile') }}" class="input input-bordered w-full" />
                                </label>

                                @if ($merchArtikel->isNotEmpty())
                                    @php($submittedMerch = old('merch', []))

                                    <div class="space-y-3">
                                        @if ($tshirtDeadlineFormatted && $merchBestellbar)
                                            <x-fantreffen-tshirt-deadline-notice
                                                :tshirtDeadlinePassed="$tshirtDeadlinePassed"
                                                :tshirtDeadlineFormatted="$tshirtDeadlineFormatted"
                                                :daysUntilDeadline="$daysUntilDeadline"
                                                variant="prominent"
                                            />
                                        @elseif (! $merchBestellbar)
                                            <x-alert icon="o-clock" class="alert-warning">
                                                Die Bestellfrist für zusätzliches Merchandise ist abgelaufen.
                                            </x-alert>
                                        @endif

                                        @if ($merchBestellbar)
                                            <div class="space-y-3">
                                                <p class="text-sm font-semibold text-base-content">Zusätzliches Merchandise</p>

                                                @foreach ($merchArtikel as $artikel)
                                                    @php($isLegacyTshirt = mb_strtolower($artikel->bezeichnung) === 't-shirt')
                                                    @php($isSelected = (bool) data_get($submittedMerch, $artikel->id.'.selected', $isLegacyTshirt ? old('tshirt_bestellt', false) : false))
                                                    @php($selectedVariant = data_get($submittedMerch, $artikel->id.'.variant_id', $isLegacyTshirt ? old('tshirt_groesse') : null))
                                                    @php($displayName = $isLegacyTshirt ? 'Event-T-Shirt' : $artikel->bezeichnung)

                                                    <div x-data="{ selected: @js($isSelected) }" class="rounded-box border border-base-300 p-3 space-y-3">
                                                        <label class="flex items-start gap-3">
                                                            <input
                                                                type="checkbox"
                                                                name="merch[{{ $artikel->id }}][selected]"
                                                                value="1"
                                                                x-model="selected"
                                                                class="checkbox mt-1"
                                                                @if ($isLegacyTshirt) data-testid="fantreffen-tshirt-checkbox" @endif
                                                            />
                                                            <span class="flex-1">
                                                                <span class="block font-medium">{{ $displayName }} bestellen</span>
                                                                <span class="block text-sm text-base-content/70">{{ number_format((float) $artikel->preis, 2, ',', '.') }} €</span>
                                                                @if ($artikel->beschreibung)
                                                                    <span class="mt-1 block text-sm text-base-content/60">{{ $artikel->beschreibung }}</span>
                                                                @endif
                                                            </span>
                                                        </label>

                                                        @if ($artikel->requiresVariant())
                                                            <div x-show="selected" x-cloak @if ($isLegacyTshirt) data-testid="fantreffen-tshirt-container" @endif>
                                                                <label class="form-control w-full">
                                                                    <span class="label-text mb-1 block text-sm font-medium">Variante</span>
                                                                    <select
                                                                        name="merch[{{ $artikel->id }}][variant_id]"
                                                                        class="select select-bordered w-full"
                                                                        :required="selected"
                                                                        @if ($isLegacyTshirt) data-testid="fantreffen-tshirt-groesse" @endif
                                                                    >
                                                                        <option value="">Bitte wählen</option>
                                                                        @foreach ($artikel->varianten as $variante)
                                                                            <option value="{{ $variante->id }}" @selected((string) $selectedVariant === (string) $variante->id || (string) $selectedVariant === (string) $variante->bezeichnung)>{{ $variante->bezeichnung }}</option>
                                                                        @endforeach
                                                                    </select>
                                                                </label>
                                                            </div>
                                                        @endif
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                @endif

                                @if ($veranstaltung->zahlung_aktiv || $merchArtikel->isNotEmpty())
                                    <div class="rounded-box bg-base-200/80 p-4 text-sm text-base-content/75">
                                        @if ($veranstaltung->zahlung_aktiv)
                                            <p>Mitglieder: kostenlose Teilnahme</p>
                                            <p>Gäste: {{ number_format((float) $veranstaltung->gastgebuehr, 2, ',', '.') }} € Teilnahmegebühr</p>
                                        @endif
                                        @if ($merchArtikel->isNotEmpty())
                                            <p>Merchandise wird zusätzlich gemäß Auswahl berechnet.</p>
                                        @endif
                                    </div>
                                @endif

                                <button type="submit" class="btn btn-primary w-full rounded-full" data-testid="fantreffen-submit">
                                    Jetzt anmelden
                                </button>
                            </form>
                        @else
                            <x-alert icon="o-lock-closed" class="alert-warning">
                                Die Anmeldung ist derzeit nicht geöffnet.
                            </x-alert>
                        @endif
                    </x-ui.panel>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>