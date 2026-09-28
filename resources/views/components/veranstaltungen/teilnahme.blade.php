@props(['anmeldung', 'veranstaltung', 'berechtigt', 'canConfirmAttendance', 'vergabe' => null])

<div class="space-y-1 text-sm" wire:key="teilnahme-{{ $anmeldung->id }}-{{ (int) $anmeldung->teilgenommen }}-{{ $veranstaltung->baxx_status->value }}">
    @if ($canConfirmAttendance && ($berechtigt || $anmeldung->teilgenommen))
        <label class="inline-flex items-center gap-2">
            <input type="checkbox" class="checkbox checkbox-sm" @checked($anmeldung->teilgenommen)
                data-confirmed="{{ (int) $anmeldung->teilgenommen }}"
                x-on:change="const confirmed = $el.checked; $el.checked = $el.dataset.confirmed === '1'; $wire.setTeilnahme({{ $anmeldung->id }}, confirmed)"
                wire:loading.attr="disabled" wire:target="setTeilnahme"
                aria-label="Teilnahme von {{ $anmeldung->full_name }} bestätigen" />
            <span>{{ $anmeldung->teilgenommen ? 'Bestätigt' : 'Nicht bestätigt' }}</span>
        </label>
    @else
        <span>{{ $anmeldung->teilgenommen ? 'Bestätigt' : 'Nicht bestätigt' }}</span>
    @endif
    @if ($anmeldung->teilnahme_bestaetigt_am)
        <div class="text-xs text-base-content/60">{{ $anmeldung->teilnahme_bestaetigt_am->format('d.m.Y H:i') }}</div>
    @endif
    @if ($vergabe)
        <div class="font-medium">{{ $vergabe->points }} Baxx vergeben</div>
    @elseif ($veranstaltung->baxx_status === \App\Enums\VeranstaltungsBaxxStatus::Abgeschlossen)
        <div class="text-xs text-base-content/60">Keine Gutschrift</div>
    @elseif (! $anmeldung->user_id)
        <div class="text-xs text-base-content/60">Kein Mitgliedskonto</div>
    @elseif (! $berechtigt)
        <div class="text-xs text-base-content/60">Aktuell nicht Baxx-berechtigt</div>
    @endif
</div>
