<x-app-layout :title="'Kontakt zu '.$ag->name.' – Offizieller MADDRAX Fanclub e. V.'" description="Öffentliche Kontaktmöglichkeit zu einer Arbeitsgruppe des OMXFC, ohne die Zieladresse im Seitenquelltext offenzulegen.">
    <x-public-page class="space-y-8">
        <x-ui.page-header
            eyebrow="Arbeitsgruppenkontakt"
            :title="'Kontakt zu '.$ag->name"
            description="Nutze dieses Formular, um der Arbeitsgruppe direkt eine Nachricht zu schicken. Die Zieladresse bleibt dabei serverseitig geschützt."
        >
            <x-slot:actions>
                <a href="{{ route('arbeitsgruppen') }}" wire:navigate class="btn btn-ghost rounded-full bg-base-100/75">Zur Übersicht</a>
            </x-slot:actions>
        </x-ui.page-header>

        <section class="grid gap-8 lg:grid-cols-[minmax(0,1fr)_22rem] lg:items-start">
            <x-ui.panel title="Nachricht senden" description="Die Nachricht wird direkt an die hinterlegte Kontaktadresse der Arbeitsgruppe weitergeleitet.">
                @if (session('status'))
                    <div class="alert alert-success mb-6" data-testid="ag-contact-success">
                        <span>{{ session('status') }}</span>
                    </div>
                @endif

                @if ($errors->has('error'))
                    <div class="alert alert-error mb-6" data-testid="ag-contact-error">
                        <span>{{ $errors->first('error') }}</span>
                    </div>
                @endif

                <form method="POST" action="{{ route('arbeitsgruppen.kontakt.senden', $ag) }}" class="space-y-4" data-testid="ag-contact-form">
                    @csrf

                    <x-input
                        name="name"
                        label="Dein Name"
                        :value="old('name')"
                        required
                        autocomplete="name"
                    />
                    @error('name')
                        <p class="mt-1 text-sm text-error">{{ $message }}</p>
                    @enderror

                    <x-input
                        name="email"
                        label="Deine E-Mail-Adresse"
                        type="email"
                        :value="old('email')"
                        required
                        autocomplete="email"
                    />
                    @error('email')
                        <p class="mt-1 text-sm text-error">{{ $message }}</p>
                    @enderror

                    <x-textarea
                        name="message"
                        label="Nachricht"
                        rows="7"
                        required
                        data-testid="ag-contact-message"
                    >{{ old('message') }}</x-textarea>
                    @error('message')
                        <p class="mt-1 text-sm text-error">{{ $message }}</p>
                    @enderror

                    <div aria-hidden="true" style="position:absolute;left:-9999px;top:-9999px;">
                        <label for="website">Website</label>
                        <input type="text" name="website" id="website" value="" tabindex="-1" autocomplete="off" />
                    </div>

                    <div class="flex justify-end">
                        <x-button type="submit" label="Nachricht senden" icon="o-paper-airplane" class="btn-primary" />
                    </div>
                </form>
            </x-ui.panel>

            <div class="space-y-6">
                <x-ui.panel title="AG im Überblick" description="Die öffentliche Arbeitsgruppen-Seite zeigt weiterhin nur die Informationen, die für Interessierte relevant sind.">
                    <dl class="space-y-4" data-testid="ag-contact-summary">
                        <div>
                            <dt class="text-[0.72rem] font-semibold uppercase tracking-[0.2em] text-base-content/46">Arbeitsgruppe</dt>
                            <dd class="mt-1 text-base font-medium text-base-content">{{ $ag->name }}</dd>
                        </div>
                        <div>
                            <dt class="text-[0.72rem] font-semibold uppercase tracking-[0.2em] text-base-content/46">AG-Leitung</dt>
                            <dd class="mt-1 text-base font-medium text-base-content">{{ $ag->owner?->publicFirstName() ?: 'Wird im Team abgestimmt' }}</dd>
                        </div>
                        <div>
                            <dt class="text-[0.72rem] font-semibold uppercase tracking-[0.2em] text-base-content/46">Treffen</dt>
                            <dd class="mt-1 text-base font-medium text-base-content">{{ $ag->meeting_schedule ?: 'Nach Bedarf und Projektphase' }}</dd>
                        </div>
                    </dl>
                </x-ui.panel>
            </div>
        </section>
    </x-public-page>
</x-app-layout>