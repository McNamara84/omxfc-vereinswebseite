/**
 * Romantauschbörse Bundle Preview - Alpine.js Komponente
 *
 * Diese Datei enthält die bundlePreview() Funktion für die Live-Vorschau
 * der Roman-Nummern-Eingabe in Bundle-Formularen.
 *
 * Verwendung in Blade-Views:
 * 1. In der Blade-View die Konstanten definieren:
 *    - window.MAX_RANGE_SPAN (vom Controller injiziert)
 *    - window.COMPACT_THRESHOLD (für Vorschau-Formatierung)
 * 2. Dieses Script via Vite einbinden: @vite(['resources/js/romantausch-bundle-preview.js'])
 * 3. Im HTML x-data="bundlePreview()" verwenden
 * 4. Im Input x-init verwenden um den Wert aus dem DOM zu lesen:
 *    x-init="input = $el.getAttribute('value') || input; parseNumbers()"
 *
 * WICHTIG - PHP/JavaScript Konstanten-Kopplung:
 * Die Konstante MAX_RANGE_SPAN ist in zwei Stellen definiert:
 * - PHP: App\Http\Controllers\RomantauschController::MAX_RANGE_SPAN
 * - JS: window.MAX_RANGE_SPAN (via Blade-Template injiziert)
 *
 * Bei Änderung des Wertes in PHP wird dieser automatisch übernommen, da die
 * Blade-Views den PHP-Wert lesen und an window.MAX_RANGE_SPAN übergeben.
 *
 * @example
 * <script>
 *     window.MAX_RANGE_SPAN = {{ App\Http\Controllers\RomantauschController::MAX_RANGE_SPAN }};
 *     window.COMPACT_THRESHOLD = {{ config('romantausch.compact_threshold', 20) }};
 * </script>
 * <input x-model="input" x-init="input = $el.getAttribute('value') || input; parseNumbers()" value="{{ $bookNumbersInput }}">
 * @vite(['resources/js/romantausch-bundle-preview.js'])
 *
 * @see resources/views/romantausch/create_bundle_offer.blade.php
 * @see resources/views/romantausch/edit_bundle.blade.php
 * @see App\Http\Controllers\RomantauschController::MAX_RANGE_SPAN (PHP-Quelle)
 */

window.bundlePreview = function bundlePreview() {
    return {
        input: '',
        numbers: [],

        // init() wird nicht mehr benötigt da x-init auf dem Input-Element verwendet wird

        parseNumbers() {
            const numbers = [];
            const parts = this.input.split(',');
            const maxRangeSpan = window.MAX_RANGE_SPAN ?? 500;

            for (const part of parts) {
                const trimmed = part.trim();
                if (!trimmed) continue;

                if (trimmed.includes('-')) {
                    const [startStr, endStr] = trimmed.split('-');
                    // parseInt mit radix 10 ignoriert führende Nullen:
                    // parseInt("01", 10) → 1, parseInt("08", 10) → 8
                    // Entspricht dem PHP-Backend-Verhalten mit ltrim('0').
                    const start = parseInt(startStr.trim(), 10);
                    const end = parseInt(endStr.trim(), 10);

                    // NaN-Handling: parseInt gibt NaN für ungültige Eingaben zurück.
                    // Die Bedingung start > 0 && end > 0 filtert NaN automatisch aus,
                    // da NaN > 0 === false.
                    //
                    // Die expliziten isNaN-Checks sind technisch redundant (Performance-Overhead
                    // von ~1-2 Nanosekunden pro Iteration), werden aber für Code-Klarheit beibehalten.
                    // Bei Eingaben wie "1-500" mit 500 Iterationen ist der Overhead vernachlässigbar.
                    // Falls Performance kritisch wird, können die isNaN-Checks entfernt werden.
                    if (!isNaN(start) && !isNaN(end) && start > 0 && end > 0 && end >= start && (end - start) <= maxRangeSpan) {
                        for (let i = start; i <= end; i++) {
                            numbers.push(i);
                        }
                    }
                } else {
                    // parseInt gibt NaN für ungültige Eingaben (z.B. "abc", "").
                    // num > 0 filtert sowohl NaN als auch 0 und negative Werte aus.
                    const num = parseInt(trimmed, 10);
                    if (!isNaN(num) && num > 0) {
                        numbers.push(num);
                    }
                }
            }

            this.numbers = [...new Set(numbers)].sort((a, b) => a - b);
        },

        formatPreview() {
            if (this.numbers.length === 0) return '';
            
            // THRESHOLD für Kompakt-Darstellung: Bei mehr Nummern als dem Threshold
            // wechseln wir von individueller Auflistung (1, 2, 3, ...) zu Bereichen (1-20, ...).
            //
            // Der Wert kann via window.COMPACT_THRESHOLD aus PHP injiziert werden,
            // ähnlich wie MAX_RANGE_SPAN. Falls nicht definiert, Default 20.
            //
            // Begründung für Default 20:
            // - Typische Bildschirmbreite erlaubt ~20-25 Zahlen gut lesbar
            // - Bei 5 Romanen pro Zeile passen 4 Zeilen auf einen Blick
            // - Größere Stapel (z.B. 50+ Romane) wären als Liste unübersichtlich
            //
            // Konfiguration in Blade-View (optional):
            //   window.COMPACT_THRESHOLD = {{ config('romantausch.compact_threshold', 20) }};
            const compactThreshold = window.COMPACT_THRESHOLD ?? 20;
            
            if (this.numbers.length <= compactThreshold) {
                return this.numbers.join(', ');
            }

            // Kompakte Darstellung als Bereiche
            const ranges = [];
            let start = this.numbers[0];
            let end = this.numbers[0];

            for (let i = 1; i < this.numbers.length; i++) {
                if (this.numbers[i] === end + 1) {
                    end = this.numbers[i];
                } else {
                    ranges.push(start === end ? String(start) : `${start}-${end}`);
                    start = this.numbers[i];
                    end = this.numbers[i];
                }
            }
            ranges.push(start === end ? String(start) : `${start}-${end}`);

            return ranges.join(', ');
        },
    };
};
