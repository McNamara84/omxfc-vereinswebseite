/**
 * Romantausch Serien-Filter
 *
 * Filtert die Roman-Auswahl (<select>) basierend auf der gewählten Serie.
 * Wird sowohl im Angebots- als auch im Gesuchs-Formular verwendet.
 *
 * Erwartet pro Formular ein Element mit [data-romantausch-books-by-series]
 * als JSON-Datenquelle.
 */

function initSerienFilter() {
    document.querySelectorAll('[data-romantausch-books-by-series]').forEach(configEl => {
        const form = configEl.closest('form');
        if (!form) return;

        // Guard: nicht doppelt initialisieren
        if (form.dataset.serienFilterInitialized) return;
        form.dataset.serienFilterInitialized = 'true';

        const seriesSelect = form.querySelector('#series-select');
        const bookSelect = form.querySelector('#book-select');
        if (!seriesSelect || !bookSelect) return;

        let booksBySeries;
        try {
            booksBySeries = JSON.parse(configEl.dataset.romantauschBooksBySeries);
        } catch {
            return;
        }

        function filterBooks() {
            const series = seriesSelect.value;
            const allowedNumbers = new Set(booksBySeries[series] || []);
            let firstVisibleIndex = -1;
            let hasVisibleSelection = false;
            Array.from(bookSelect.options).forEach((option, idx) => {
                if (!option.value) return;
                const match = allowedNumbers.has(String(option.value));
                option.hidden = !match;
                option.disabled = !match;
                if (match) {
                    if (firstVisibleIndex === -1) {
                        firstVisibleIndex = idx;
                    }
                    if (option.selected) {
                        hasVisibleSelection = true;
                    }
                }
            });
            if (!hasVisibleSelection && firstVisibleIndex !== -1) {
                bookSelect.selectedIndex = firstVisibleIndex;
            }
        }

        filterBooks();
        seriesSelect.addEventListener('change', filterBooks);
    });
}

document.addEventListener('DOMContentLoaded', initSerienFilter);
document.addEventListener('livewire:navigated', initSerienFilter);
