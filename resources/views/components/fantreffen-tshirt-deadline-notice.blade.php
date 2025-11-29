@props([
    'tshirtDeadlinePassed' => false,
    'tshirtDeadlineFormatted' => '',
    'daysUntilDeadline' => 0,
    'variant' => 'compact' // 'compact' für Kostenübersicht, 'prominent' für Formular
])

@if (!$tshirtDeadlinePassed)
    @if ($variant === 'compact')
        {{-- Kompakte Version für die Kostenübersicht --}}
        <div class="mt-2 p-2 bg-orange-100 dark:bg-orange-900/40 rounded border border-orange-300 dark:border-orange-700"
             @if($daysUntilDeadline <= 7) role="alert" @endif>
            <p class="text-xs text-orange-800 dark:text-orange-200 font-semibold">
                ⏰ Bestellfrist: {{ $tshirtDeadlineFormatted }}
            </p>
            <p class="text-xs text-orange-700 dark:text-orange-300 mt-0.5">
                T-Shirts können nur bis zur Bestellfrist mitbestellt werden!
            </p>
        </div>
    @else
        {{-- Prominente Version für das Anmeldeformular --}}
        <div class="mb-3 p-3 bg-gradient-to-r from-orange-100 to-yellow-100 dark:from-orange-900/40 dark:to-yellow-900/40 rounded-lg border border-orange-300 dark:border-orange-600"
             @if($daysUntilDeadline <= 7) role="alert" @endif>
            <div class="flex items-center gap-2">
                <span class="text-2xl" aria-hidden="true">👕</span>
                <div>
                    <p class="text-sm font-bold text-orange-800 dark:text-orange-200">
                        T-Shirt nur bis {{ $tshirtDeadlineFormatted }} bestellbar!
                    </p>
                    @if ($daysUntilDeadline > 0)
                        <p class="text-xs text-orange-700 dark:text-orange-300">
                            Noch <strong>{{ $daysUntilDeadline }} {{ $daysUntilDeadline === 1 ? 'Tag' : 'Tage' }}</strong> Zeit für deine T-Shirt-Bestellung.
                        </p>
                    @else
                        <p class="text-xs text-orange-700 dark:text-orange-300">
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
        <div class="mt-2 p-2 bg-gray-100 dark:bg-gray-700 rounded">
            <p class="text-xs text-gray-600 dark:text-gray-400">
                ❌ Bestellfrist abgelaufen – T-Shirts können nicht mehr bestellt werden.
            </p>
        </div>
    @endif
    {{-- Bei 'prominent' wird bei abgelaufener Deadline nichts angezeigt, 
         da der gesamte T-Shirt-Bereich im Formular ausgeblendet wird --}}
@endif
