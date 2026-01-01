/**
 * Romantauschbörse Bundle Preview - Alpine.js Komponente
 *
 * Diese Datei enthält die bundlePreview() Funktion für die Live-Vorschau
 * der Roman-Nummern-Eingabe in Bundle-Formularen.
 *
 * Verwendung in Blade-Views:
 * 1. In der Blade-View vor diesem Script die Konstanten definieren:
 *    - window.MAX_RANGE_SPAN (vom Controller injiziert)
 *    - window.bundlePreviewInitialInput (initialer Wert aus Formular)
 * 2. Dieses Script via Vite einbinden: @vite(['resources/js/romantausch-bundle-preview.js'])
 * 3. Im HTML x-data="bundlePreview()" verwenden
 *
 * Hinweis: Die Werte werden direkt in den Blade-Views definiert, da sie
 * server-seitig aus PHP-Variablen kommen (z.B. old('book_numbers')).
 *
 * @example
 * <script>
 *     window.MAX_RANGE_SPAN = {{ App\Http\Controllers\RomantauschController::MAX_RANGE_SPAN }};
 *     window.bundlePreviewInitialInput = {{ Js::from($bookNumbersInput) }};
 * </script>
 * @vite(['resources/js/romantausch-bundle-preview.js'])
 *
 * @see resources/views/romantausch/create_bundle_offer.blade.php
 * @see resources/views/romantausch/edit_bundle.blade.php
 */

window.bundlePreview = function bundlePreview() {
    return {
        input: window.bundlePreviewInitialInput ?? '',
        numbers: [],

        init() {
            if (this.input) {
                this.parseNumbers();
            }
        },

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
                    // da NaN > 0 === false. Explizite isNaN-Checks für Klarheit:
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
            if (this.numbers.length <= 20) {
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
