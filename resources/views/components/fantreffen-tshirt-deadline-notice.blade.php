@props([
    'tshirtDeadlinePassed' => false,
    'tshirtDeadlineFormatted' => '',
    'daysUntilDeadline' => 0,
    'variant' => 'compact' // 'compact' für Kostenübersicht, 'prominent' für Formular
])

@if (!$tshirtDeadlinePassed)
    @if ($variant === 'compact')
        {{-- Kompakte Version für die Kostenübersicht --}}
        <div class="mt-2 p-2 bg-warning/10 rounded border border-warning"
             @if($daysUntilDeadline <= 7) role="alert" @endif>
            <p class="text-xs text-warning font-semibold">
                ⏰ Bestellfrist: {{ $tshirtDeadlineFormatted }}
            </p>
            <p class="text-xs text-warning/80 mt-0.5">
                T-Shirts können nur bis zur Bestellfrist mitbestellt werden!
            </p>
        </div>
    @else
        {{-- Prominente Version für das Anmeldeformular --}}
        <div class="mb-3 p-3 bg-warning/10 rounded-lg border border-warning"
             @if($daysUntilDeadline <= 7) role="alert" @endif>
            <div class="flex items-center gap-2">
                <span class="text-2xl" aria-hidden="true">👕</span>
                <div>
                    <p class="text-sm font-bold text-warning">
                        T-Shirt nur bis {{ $tshirtDeadlineFormatted }} bestellbar!
                    </p>
                    @if ($daysUntilDeadline > 0)
                        <p class="text-xs text-warning/80">
                            Noch <strong>{{ $daysUntilDeadline }} {{ $daysUntilDeadline === 1 ? 'Tag' : 'Tage' }}</strong> Zeit für deine T-Shirt-Bestellung.
                        </p>
                    @else
                        <p class="text-xs text-warning/80">
                            Heute ist der letzte Tag für T-Shirt-Bestellungen!
                        </p>
                    @endif
                </div>
            </div>
        </div>
    @endif
@else
    @if ($variant === 'compact')
        {{-- Kompakte Meldung für abgelaufene Deadline --}}
        <div class="mt-2 p-2 bg-base-200 rounded">
            <p class="text-xs text-base-content">
                ❌ Bestellfrist abgelaufen – T-Shirts können nicht mehr bestellt werden.
            </p>
        </div>
    @endif
    {{-- Bei 'prominent' wird bei abgelaufener Deadline nichts angezeigt, 
         da der gesamte T-Shirt-Bereich im Formular ausgeblendet wird --}}
@endif
