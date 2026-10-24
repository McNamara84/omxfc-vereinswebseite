@props(['days'])
<ol {{ $attributes->class('grid grid-cols-7 gap-1.5') }} aria-label="Aktivitätstage im Maddraxikon">
    @foreach($days as $day)
        @php($statusLabel = ['confirmed' => 'Bestätigt', 'pending' => 'Wird geprüft', 'empty' => 'Keine gültige Aktivität', 'unknown' => 'Noch offen'][$day['status']])
        <li class="min-w-0 rounded-xl border p-1.5 text-center {{ $day['status'] === 'confirmed' ? 'border-primary/40 bg-primary/10' : 'border-base-content/15 bg-base-100' }}"
            aria-label="{{ $day['full'] }}: {{ $statusLabel }}">
            <span class="block text-xs text-base-content/80" aria-hidden="true">{{ $day['label'] }}</span>
            <span class="block text-lg font-semibold" aria-hidden="true">{{ ['confirmed' => '✓', 'pending' => '◷', 'empty' => '–', 'unknown' => '·'][$day['status']] }}</span>
            <span class="block text-[0.65rem] text-base-content/75" aria-hidden="true">{{ substr($day['date'], 8) }}.</span>
        </li>
    @endforeach
</ol>
