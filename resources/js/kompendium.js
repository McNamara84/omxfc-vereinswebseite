/**
 * Kompendium: Volltextsuche mit Infinite Scroll und Serien-Filter.
 *
 * Wird über app.js gebundelt geladen. Guard-Pattern: Initialisierung
 * läuft nur, wenn das Suchfeld (data-testid="kompendium-search") existiert.
 *
 * Benötigt data-Attribute im HTML:
 * - #kompendium-config[data-search-url]  → Route für die Suche
 * - #kompendium-config[data-serien-url]  → Route für verfügbare Serien
 */

function initKompendium() {
    const $search = document.querySelector('[data-testid="kompendium-search"]');
    if (!$search) return;

    // Verhindere doppelte Initialisierung
    if ($search.dataset.initialized) return;
    $search.dataset.initialized = 'true';

    const configEl = document.getElementById('kompendium-config');
    const searchUrl = configEl?.dataset.searchUrl || '/kompendium/search';
    const serienUrl = configEl?.dataset.serienUrl || '/kompendium/serien';

    let page = 1;
    let query = '';
    let busy = false;
    let verfuegbareSerien = {};
    let serienCounts = {};
    let lastPage = 1;

    const perFetchOffset = 200;
    const $results = document.getElementById('results');
    const $loading = document.getElementById('loading');
    const $serienFilter = document.getElementById('serien-filter');
    const $serienCheckboxes = document.getElementById('serien-checkboxes');

    function showError(message) {
        $results.innerHTML = '';
        const errorDiv = document.createElement('div');
        errorDiv.className = 'p-4 border-l-4 border-error bg-error/10 rounded';
        const errorP = document.createElement('p');
        errorP.className = 'text-error';
        errorP.textContent = message;
        errorDiv.appendChild(errorP);
        $results.appendChild(errorDiv);
    }

    async function loadSerien() {
        try {
            const res = await fetch(serienUrl);

            if (!res.ok) {
                console.warn('Serien konnten nicht geladen werden:', res.status);
                return;
            }

            verfuegbareSerien = await res.json();

            const keys = Object.keys(verfuegbareSerien);
            if (keys.length >= 2) {
                renderCheckboxes();
                $serienFilter.classList.remove('hidden');
            }
        } catch (e) {
            console.error('Fehler beim Laden der Serien:', e);
        }
    }

    function hasCheckedSerie() {
        return $serienCheckboxes.querySelectorAll('input[name="serien"]:checked').length > 0;
    }

    function renderCheckboxes() {
        $serienCheckboxes.innerHTML = '';

        for (const [key, name] of Object.entries(verfuegbareSerien)) {
            const count = serienCounts[key];
            const countText = count !== undefined ? ` (${count})` : '';
            const checkboxId = `serie-checkbox-${key}`;

            const label = document.createElement('label');
            label.className = 'inline-flex items-center text-sm text-base-content cursor-pointer';
            label.setAttribute('for', checkboxId);

            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.id = checkboxId;
            checkbox.name = 'serien';
            checkbox.value = key;
            checkbox.checked = true;
            checkbox.className = 'checkbox checkbox-primary checkbox-sm mr-1.5';
            checkbox.setAttribute('aria-describedby', 'serien-filter-legend');

            const span = document.createElement('span');
            span.dataset.serie = key;
            span.textContent = name + countText;

            label.appendChild(checkbox);
            label.appendChild(span);

            let isHandlingChange = false;
            checkbox.addEventListener('change', (e) => {
                if (isHandlingChange) return;
                isHandlingChange = true;

                try {
                    if (!e.target.checked && !hasCheckedSerie()) {
                        e.target.checked = true;
                        return;
                    }

                    if (query) {
                        page = 1;
                        $results.innerHTML = '';
                        window.removeEventListener('scroll', onScroll);
                        fetchHits().then(() => window.addEventListener('scroll', onScroll));
                    }
                } finally {
                    isHandlingChange = false;
                }
            });

            $serienCheckboxes.appendChild(label);
        }
    }

    function updateCheckboxLabels() {
        for (const [key, name] of Object.entries(verfuegbareSerien)) {
            const count = serienCounts[key] ?? 0;
            const span = $serienCheckboxes.querySelector(`span[data-serie="${CSS.escape(key)}"]`);
            if (span) {
                span.textContent = `${name} (${count})`;
            }
        }
    }

    function getSelectedSerien() {
        const checkboxes = $serienCheckboxes.querySelectorAll('input[name="serien"]:checked');
        return Array.from(checkboxes).map(cb => cb.value);
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    const tpl = (roman) => `
        <div class="border border-base-content/10 rounded p-4">
            <h2 class="font-semibold text-primary mb-2">
                ${escapeHtml(roman.cycle)} – ${escapeHtml(roman.romanNr)}: ${escapeHtml(roman.title)}
            </h2>
            ${roman.snippets.map(s => `<p class="mb-2 text-sm leading-relaxed">${s}</p>`).join('')}
        </div>`;

    async function fetchHits() {
        if (busy) return;
        busy = true;
        $loading.classList.remove('hidden');

        const params = new URLSearchParams();
        params.append('q', query);
        params.append('page', page);

        const selectedSerien = getSelectedSerien();
        selectedSerien.forEach(s => params.append('serien[]', s));

        const url = `${searchUrl}?${params.toString()}`;

        try {
            const res = await fetch(url);

            if (!res.ok) {
                const errorJson = await res.json().catch(() => ({}));
                const message = errorJson.message || 'Fehler bei der Suche. Bitte versuche es später erneut.';
                showError(message);
                busy = false;
                $loading.classList.add('hidden');
                return;
            }

            const json = await res.json();

            if (json.serienCounts) {
                serienCounts = json.serienCounts;
                updateCheckboxLabels();
            }

            json.data.forEach(r => $results.insertAdjacentHTML('beforeend', tpl(r)));

            lastPage = json.lastPage;
            page++;
            busy = false;
            $loading.classList.add('hidden');

            if (page > lastPage) {
                window.removeEventListener('scroll', onScroll);
            }
        } catch (e) {
            console.error('Fehler bei der Suche:', e);
            showError('Verbindungsfehler. Bitte überprüfe deine Internetverbindung.');
            busy = false;
            $loading.classList.add('hidden');
        }
    }

    function onScroll() {
        if ((window.innerHeight + window.scrollY) >= document.body.offsetHeight - perFetchOffset) {
            fetchHits();
        }
    }

    $search.addEventListener('keyup', e => {
        if (e.key === 'Enter' && $search.value.trim().length >= 2) {
            query = $search.value.trim();
            page = 1;
            $results.innerHTML = '';
            window.removeEventListener('scroll', onScroll);
            fetchHits().then(() => window.addEventListener('scroll', onScroll));
        }
    });

    let serienLoaded = false;
    $search.addEventListener('focus', () => {
        if (!serienLoaded) {
            serienLoaded = true;
            loadSerien();
        }
    });
}

document.addEventListener('DOMContentLoaded', initKompendium);
document.addEventListener('livewire:navigated', initKompendium);
