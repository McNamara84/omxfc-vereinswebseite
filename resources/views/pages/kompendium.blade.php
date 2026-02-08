<x-app-layout title="Kompendium – Offizieller MADDRAX Fanclub e. V." description="Volltextsuche durch Maddrax-Romane für Mitglieder.">
    <x-member-page class="max-w-4xl">
        <x-header title="Maddrax-Kompendium" />

        <x-card shadow>
            {{-- Info-Card --------------------------------------------------- --}}
            <div class="mb-6 p-4 border-l-4 border-primary bg-base-200 rounded">
                @if($indexierteRomaneSummary->isEmpty())
                    <p class="text-base-content/60">
                        Aktuell sind keine Romane für die Suche indexiert.
                    </p>
                @else
                    <p class="mb-2">Aktuell sind die folgenden Romane für die Suche indexiert:</p>
                    <ul class="list-disc ml-6">
                        @foreach($indexierteRomaneSummary as $gruppe)
                            <li>
                                <strong>{{ $gruppe['name'] }}</strong>
                                (Band {{ $gruppe['bandbereich'] }})
                            </li>
                        @endforeach
                    </ul>
                @endif

                @if($istAdmin ?? false)
                    <x-button label="Kompendium verwalten" link="{{ route('kompendium.admin') }}" icon="o-cog-6-tooth" class="btn-ghost btn-sm text-primary mt-4" />
                @endif
            </div>

            {{-- Suchschlitz (ab 100 Baxx) -------------------------------- --}}
            @if($showSearch)
                <div class="mb-4">
                    <x-input id="search" placeholder="Suchbegriff eingeben … (Enter)" icon="o-magnifying-glass" />
                </div>

                {{-- Serien-Filter (wird per JS befüllt) ----------------------- --}}
                <div id="serien-filter" class="mb-4 hidden">
                    <fieldset role="group" aria-labelledby="serien-filter-legend">
                        <legend id="serien-filter-legend" class="text-sm font-medium text-base-content/70 mb-2">
                            Serien filtern:
                        </legend>
                        <div id="serien-checkboxes" class="flex flex-wrap gap-x-4 gap-y-2" role="group">
                            {{-- Wird per JavaScript dynamisch befüllt --}}
                        </div>
                    </fieldset>
                </div>
            @else
                <x-alert icon="o-lock-closed" class="alert-warning mb-4">
                    Die Suche wird ab <strong>{{ $required }}</strong> Baxx freigeschaltet.
                    Dein aktueller Stand: <strong>{{ $userPoints }}</strong>.
                </x-alert>
            @endif

            {{-- Trefferliste ---------------------------------------------- --}}
            <div id="results" class="space-y-6"></div>

            {{-- Loader ----------------------------------------------------- --}}
            <div id="loading" class="hidden text-center py-4">
                <x-loading class="loading-spinner loading-md" />
            </div>
        </x-card>
    </x-member-page>

    {{-- JavaScript nur, wenn Suche erlaubt ---------------------------------- --}}
    @if($userPoints >= 100)
        <script>
            (() => {
                let page   = 1;
                let query  = '';
                let busy   = false;
                let verfuegbareSerien = {};
                let serienCounts = {};
                let lastPage = 1;

                const perFetchOffset = 200;                       // px vor Seitenende
                const $search  = document.getElementById('search');
                const $results = document.getElementById('results');
                const $loading = document.getElementById('loading');
                const $serienFilter = document.getElementById('serien-filter');
                const $serienCheckboxes = document.getElementById('serien-checkboxes');

                // Fehlermeldung anzeigen (XSS-sicher mit DOM-Methoden)
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

                // Verfügbare Serien beim Laden abrufen
                async function loadSerien() {
                    try {
                        const res = await fetch('{{ route('kompendium.serien') }}');

                        if (!res.ok) {
                            // Bei Autorisierungsfehler: Filter bleibt versteckt, kein Fehler anzeigen
                            // (User hat nicht genug Punkte – wird bereits über showSearch gesteuert)
                            console.warn('Serien konnten nicht geladen werden:', res.status);
                            return;
                        }

                        verfuegbareSerien = await res.json();

                        // Filter nur anzeigen wenn mindestens 2 Serien verfügbar
                        const keys = Object.keys(verfuegbareSerien);
                        if (keys.length >= 2) {
                            renderCheckboxes();
                            $serienFilter.classList.remove('hidden');
                        }
                    } catch (e) {
                        console.error('Fehler beim Laden der Serien:', e);
                    }
                }

                // Prüfen ob mindestens eine Checkbox ausgewählt ist
                function hasCheckedSerie() {
                    return $serienCheckboxes.querySelectorAll('input[name="serien"]:checked').length > 0;
                }

                // Checkboxen für Serien rendern
                function renderCheckboxes() {
                    $serienCheckboxes.innerHTML = '';

                    for (const [key, name] of Object.entries(verfuegbareSerien)) {
                        const count = serienCounts[key];
                        const countText = count !== undefined ? ` (${count})` : '';
                        const checkboxId = `serie-checkbox-${key}`;

                        const label = document.createElement('label');
                        label.className = 'inline-flex items-center text-sm text-base-content/70 cursor-pointer';
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

                        // Bei Änderung: Verhindere Abwählen der letzten Checkbox und starte Suche neu
                        let isHandlingChange = false;  // Guard gegen rekursive Event-Calls
                        checkbox.addEventListener('change', (e) => {
                            if (isHandlingChange) return;
                            isHandlingChange = true;

                            try {
                                // Verhindere Abwählen der letzten Checkbox
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

                // Checkbox-Labels mit Trefferanzahl aktualisieren
                function updateCheckboxLabels() {
                    for (const [key, name] of Object.entries(verfuegbareSerien)) {
                        const count = serienCounts[key] ?? 0;
                        const span = $serienCheckboxes.querySelector(`span[data-serie="${CSS.escape(key)}"]`);
                        if (span) {
                            span.textContent = `${name} (${count})`;
                        }
                    }
                }

                // Ausgewählte Serien ermitteln
                function getSelectedSerien() {
                    const checkboxes = $serienCheckboxes.querySelectorAll('input[name="serien"]:checked');
                    return Array.from(checkboxes).map(cb => cb.value);
                }

                // HTML-Escape-Funktion für sichere Ausgabe
                function escapeHtml(text) {
                    const div = document.createElement('div');
                    div.textContent = text;
                    return div.innerHTML;
                }

                // HTML-Template pro Roman (mit Escaping für User-Daten)
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

                    // URL mit Serien-Filter bauen
                    const params = new URLSearchParams();
                    params.append('q', query);
                    params.append('page', page);

                    const selectedSerien = getSelectedSerien();
                    selectedSerien.forEach(s => params.append('serien[]', s));

                    const url = `{{ route('kompendium.search') }}?${params.toString()}`;

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

                        // Trefferanzahl pro Serie speichern und Labels aktualisieren
                        if (json.serienCounts) {
                            serienCounts = json.serienCounts;
                            updateCheckboxLabels();
                        }

                        json.data.forEach(r => $results.insertAdjacentHTML('beforeend', tpl(r)));

                        lastPage = json.lastPage;
                        page++;
                        busy = false;
                        $loading.classList.add('hidden');

                        // Ende erreicht? → Scroll-Listener entfernen
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

                // Suche starten
                $search.addEventListener('keyup', e => {
                    if (e.key === 'Enter' && $search.value.trim().length >= 2) {
                        query  = $search.value.trim();
                        page   = 1;
                        $results.innerHTML = '';
                        window.removeEventListener('scroll', onScroll);
                        fetchHits().then(() => window.addEventListener('scroll', onScroll));
                    }
                });

                // Serien lazy laden wenn Suchfeld fokussiert wird
                let serienLoaded = false;
                $search.addEventListener('focus', () => {
                    if (!serienLoaded) {
                        serienLoaded = true;
                        loadSerien();
                    }
                });
            })();
        </script>
    @endif
</x-app-layout>
