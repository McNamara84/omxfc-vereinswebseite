{{-- Flash-zu-Toast-Bridge: Wandelt strukturierte Toast-Flashes in maryUI-Toasts um.
     Opt-in: Nur session()->flash('toast', ['type' => ..., 'title' => ...]) wird verarbeitet.
     Klassische String-Flashes ('status'/'success'/'error'/'warning'/'info') bleiben den
     Views überlassen, die sie als <x-alert> rendern – das verhindert doppelte Meldungen
     (Alert + Toast) auf Seiten, die bereits eigenes Inline-Feedback ausgeben. --}}
@php
    $__toastFlash = session('toast');
    $__typeCssMap = [
        'success' => ['css' => 'alert-success', 'timeout' => 3000],
        'error'   => ['css' => 'alert-error',   'timeout' => 5000],
        'warning' => ['css' => 'alert-warning', 'timeout' => 4000],
        'info'    => ['css' => 'alert-info',    'timeout' => 3000],
    ];
    $__toastFlashes = [];
    if (is_array($__toastFlash)) {
        $__items = (isset($__toastFlash['type']) || isset($__toastFlash['title']))
            ? [$__toastFlash]
            : $__toastFlash;
        foreach ($__items as $__t) {
            if (! is_array($__t) || empty($__t['title'])) {
                continue;
            }
            $__type = $__t['type'] ?? 'info';
            $__cfg = $__typeCssMap[$__type] ?? $__typeCssMap['info'];
            $__toastFlashes[] = [
                'title' => $__t['title'],
                'description' => $__t['description'] ?? null,
                'css' => $__cfg['css'],
                'timeout' => $__t['timeout'] ?? $__cfg['timeout'],
            ];
        }
    }
@endphp
@if(! empty($__toastFlashes))
<script>
    (function () {
        var fired = false;
        var fire = function () {
            if (fired) return;
            fired = true;
            // Nach Ausführung die globale Referenz löschen, damit der
            // livewire:navigated-Listener auf späteren Seiten ohne neue
            // Flash-Daten nicht denselben Toast erneut auslöst.
            window.__omxfcFlashToastFire = null;
            if (typeof window.toast !== 'function') return;
            @foreach($__toastFlashes as $__t)
            window.toast({toast: Object.assign({title: @json($__t['title']), css: @json($__t['css']), timeout: {{ (int) $__t['timeout'] }}, position: 'toast-top toast-end', noProgress: false}, @json($__t['description']) ? {description: @json($__t['description'])} : {})});
            @endforeach
        };
        // Direkt feuern, falls toast() bereits verfügbar ist (z.B. nach wire:navigate Re-Execution).
        if (document.readyState !== 'loading') {
            fire();
        } else {
            document.addEventListener('DOMContentLoaded', fire, { once: true });
        }
        // livewire:navigated nur einmal global registrieren, damit der Listener bei
        // mehreren Navigationen nicht stapelt, aber bei jeder Navigation feuert.
        if (!window.__omxfcFlashToastNavBound) {
            window.__omxfcFlashToastNavBound = true;
            document.addEventListener('livewire:navigated', function () {
                if (typeof window.__omxfcFlashToastFire === 'function') {
                    window.__omxfcFlashToastFire();
                }
            });
        }
        // Aktuelle Fire-Funktion mit den frischen Flash-Daten exponieren.
        window.__omxfcFlashToastFire = fire;
    })();
</script>
@endif
