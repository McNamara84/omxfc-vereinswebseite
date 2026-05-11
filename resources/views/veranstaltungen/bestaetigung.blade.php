<x-app-layout
    :title="'Anmeldung bestätigt – '.$veranstaltung->titel"
    :description="'Deine Anmeldung wurde gespeichert.'">
    <div class="mx-auto max-w-3xl px-4 py-10 sm:px-6 lg:px-8">
        <x-ui.page-header
            :eyebrow="$veranstaltung->titel"
            title="Anmeldung erfolgreich"
            description="Deine Anmeldung wurde gespeichert. Hier findest du die wichtigsten Angaben im Überblick."
            class="mb-6"
        />

        <x-ui.panel>
            <div class="mb-6 text-center">
                <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-success/20">
                    <x-icon name="o-check" class="h-8 w-8 text-success" />
                </div>
                <p class="text-base-content/70">Wir freuen uns auf dich bei {{ $veranstaltung->titel }}.</p>
            </div>

            <dl class="space-y-3 border-t border-base-content/10 pt-6">
                <div class="flex justify-between gap-4">
                    <dt class="text-base-content/60">Name</dt>
                    <dd class="font-medium text-base-content">{{ $anmeldung->full_name }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-base-content/60">E-Mail</dt>
                    <dd class="font-medium text-base-content">{{ $anmeldung->email }}</dd>
                </div>
                @if ($anmeldung->mobile)
                    <div class="flex justify-between gap-4">
                        <dt class="text-base-content/60">Mobilnummer</dt>
                        <dd class="font-medium text-base-content">{{ $anmeldung->mobile }}</dd>
                    </div>
                @endif
                @if ($anmeldung->tshirt_bestellt)
                    <div class="flex justify-between gap-4">
                        <dt class="text-base-content/60">T-Shirt</dt>
                        <dd class="font-medium text-base-content">Größe {{ $anmeldung->tshirt_groesse }}</dd>
                    </div>
                @endif
            </dl>

            @if ($anmeldung->payment_amount > 0)
                <div class="mt-6 rounded-box bg-info/10 p-4">
                    <p class="text-sm text-base-content/70">Zu zahlender Betrag</p>
                    <p class="text-xl font-semibold text-base-content">{{ number_format((float) $anmeldung->payment_amount, 2, ',', '.') }} €</p>
                </div>
            @endif

            <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                <x-button label="Zur Startseite" link="{{ route('home') }}" wire:navigate class="btn-ghost flex-1" />
                <x-button label="Veranstaltungsseite" link="{{ route('veranstaltungen.show', $veranstaltung) }}" wire:navigate class="btn-primary flex-1" />
            </div>
        </x-ui.panel>
    </div>
</x-app-layout>