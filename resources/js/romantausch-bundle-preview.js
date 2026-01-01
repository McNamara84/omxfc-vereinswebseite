/**
 * Romantauschbörse Bundle Preview - Alpine.js Komponente
 *
 * Diese Datei enthält die bundlePreview() Funktion für die Live-Vorschau
 * der Roman-Nummern-Eingabe in Bundle-Formularen.
 *
 * Verwendung:
 * 1. In der Blade-View die Konstante MAX_RANGE_SPAN definieren
 * 2. Die Variable bundlePreviewInitialInput setzen
 * 3. Diese Datei einbinden
 *
 * @example
 * <script>
 *     const MAX_RANGE_SPAN = {{ App\Http\Controllers\RomantauschController::MAX_RANGE_SPAN }};
 *     const bundlePreviewInitialInput = {!! json_encode($bookNumbersInput) !!};
 * </script>
 * @vite(['resources/js/romantausch-bundle-preview.js'])
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
                    const start = parseInt(startStr.trim(), 10);
                    const end = parseInt(endStr.trim(), 10);

                    if (start > 0 && end > 0 && end >= start && (end - start) <= maxRangeSpan) {
                        for (let i = start; i <= end; i++) {
                            numbers.push(i);
                        }
                    }
                } else {
                    const num = parseInt(trimmed, 10);
                    if (num > 0) {
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
