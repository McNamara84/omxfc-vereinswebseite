import { describe, it, expect, beforeEach } from 'vitest';

/**
 * Vitest für die Kompendium-Suche: DOM-Selektor-Verhalten.
 *
 * Die maryUI <x-input>-Komponente generiert eine UUID statt der übergebenen id
 * auf dem <input>-Element. Deshalb muss data-testid statt getElementById verwendet werden.
 *
 * Diese Tests stellen sicher, dass das korrekte Selektor-Pattern funktioniert.
 */
describe('Kompendium Suche – DOM Selektoren', () => {
    beforeEach(() => {
        document.body.innerHTML = '';
    });

    it('findet das Suchfeld per data-testid Selektor', () => {
        // Simuliere die maryUI-Ausgabe: das <input> hat eine UUID als id,
        // aber data-testid wird korrekt durchgereicht
        document.body.innerHTML = `
            <fieldset class="fieldset py-0">
                <label>
                    <label class="input w-full">
                        <input id="mary4a5b6c7dsearch" placeholder="Suchbegriff eingeben … (Enter)"
                               data-testid="kompendium-search" type="text" />
                    </label>
                </label>
            </fieldset>
        `;

        const el = document.querySelector('[data-testid="kompendium-search"]');
        expect(el).not.toBeNull();
        expect(el.tagName).toBe('INPUT');
        expect(el.getAttribute('placeholder')).toContain('Suchbegriff');
    });

    it('getElementById("search") findet das maryUI-Input NICHT', () => {
        // Dies war der Bug: maryUI setzt id="mary<hash>search", nicht id="search"
        document.body.innerHTML = `
            <fieldset class="fieldset py-0">
                <label>
                    <label class="input w-full">
                        <input id="mary4a5b6c7dsearch" placeholder="Suchbegriff eingeben … (Enter)"
                               data-testid="kompendium-search" type="text" />
                    </label>
                </label>
            </fieldset>
        `;

        const el = document.getElementById('search');
        expect(el).toBeNull();
    });

    it('andere DOM-Elemente (results, loading) werden per id korrekt gefunden', () => {
        document.body.innerHTML = `
            <div id="results" class="space-y-6"></div>
            <div id="loading" class="hidden text-center py-4"></div>
            <div id="serien-filter" class="mb-4 hidden"></div>
            <div id="serien-checkboxes" class="flex flex-wrap"></div>
        `;

        expect(document.getElementById('results')).not.toBeNull();
        expect(document.getElementById('loading')).not.toBeNull();
        expect(document.getElementById('serien-filter')).not.toBeNull();
        expect(document.getElementById('serien-checkboxes')).not.toBeNull();
    });
});

describe('Kompendium Suche – escapeHtml', () => {
    // Die escapeHtml-Funktion aus kompendium.blade.php (inline JS)
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    it('escaped HTML-Sonderzeichen korrekt', () => {
        expect(escapeHtml('<script>alert("xss")</script>')).toBe(
            '&lt;script&gt;alert("xss")&lt;/script&gt;'
        );
    });

    it('escaped Ampersand korrekt', () => {
        expect(escapeHtml('Tom & Jerry')).toBe('Tom &amp; Jerry');
    });

    it('lässt normalen Text unverändert', () => {
        expect(escapeHtml('Maddrax - Die dunkle Zukunft der Erde')).toBe(
            'Maddrax - Die dunkle Zukunft der Erde'
        );
    });

    it('behandelt leeren String', () => {
        expect(escapeHtml('')).toBe('');
    });

    it('escaped Anführungszeichen im Kontext', () => {
        const result = escapeHtml('Er sagte "Hallo" & ging');
        expect(result).toContain('&amp;');
        expect(result).toContain('"');
    });
});

describe('Kompendium Suche – Serien-Checkboxen', () => {
    beforeEach(() => {
        document.body.innerHTML = `
            <div id="serien-filter" class="mb-4 hidden">
                <fieldset role="group">
                    <legend id="serien-filter-legend">Serien filtern:</legend>
                    <div id="serien-checkboxes" class="flex flex-wrap gap-x-4 gap-y-2" role="group"></div>
                </fieldset>
            </div>
        `;
    });

    it('verhindert Abwählen der letzten Checkbox', () => {
        const container = document.getElementById('serien-checkboxes');

        // Eine einzige Checkbox erstellen (bereits gecheckt)
        const checkbox = document.createElement('input');
        checkbox.type = 'checkbox';
        checkbox.name = 'serien';
        checkbox.value = 'maddrax';
        checkbox.checked = true;
        container.appendChild(checkbox);

        // Funktion aus dem Blade-Template nachgebaut
        function hasCheckedSerie() {
            return container.querySelectorAll('input[name="serien"]:checked').length > 0;
        }

        // Simuliere Abwählen
        checkbox.checked = false;

        // Prüfe: keine Checkbox mehr ausgewählt
        expect(hasCheckedSerie()).toBe(false);

        // Die Guard-Logik sollte dies verhindern:
        if (!checkbox.checked && !hasCheckedSerie()) {
            checkbox.checked = true;
        }

        expect(checkbox.checked).toBe(true);
        expect(hasCheckedSerie()).toBe(true);
    });

    it('erlaubt Abwählen wenn andere Checkboxen noch aktiv', () => {
        const container = document.getElementById('serien-checkboxes');

        const cb1 = document.createElement('input');
        cb1.type = 'checkbox';
        cb1.name = 'serien';
        cb1.value = 'maddrax';
        cb1.checked = true;

        const cb2 = document.createElement('input');
        cb2.type = 'checkbox';
        cb2.name = 'serien';
        cb2.value = 'missionmars';
        cb2.checked = true;

        container.appendChild(cb1);
        container.appendChild(cb2);

        function hasCheckedSerie() {
            return container.querySelectorAll('input[name="serien"]:checked').length > 0;
        }

        // Erste Checkbox abwählen – sollte erlaubt sein, weil cb2 noch aktiv
        cb1.checked = false;
        expect(hasCheckedSerie()).toBe(true);
    });

    it('getSelectedSerien gibt nur ausgewählte Serien zurück', () => {
        const container = document.getElementById('serien-checkboxes');

        ['maddrax', 'missionmars', 'hardcovers'].forEach((serie, i) => {
            const cb = document.createElement('input');
            cb.type = 'checkbox';
            cb.name = 'serien';
            cb.value = serie;
            cb.checked = i < 2; // nur maddrax und missionmars
            container.appendChild(cb);
        });

        // Funktion aus dem Blade-Template nachgebaut
        function getSelectedSerien() {
            const checkboxes = container.querySelectorAll('input[name="serien"]:checked');
            return Array.from(checkboxes).map(cb => cb.value);
        }

        const selected = getSelectedSerien();
        expect(selected).toEqual(['maddrax', 'missionmars']);
        expect(selected).not.toContain('hardcovers');
    });
});

describe('Kompendium Suche – URL-Parameter-Aufbau', () => {
    it('baut korrekte Suchparameter mit Serien-Filter auf', () => {
        const query = 'maddrax';
        const page = 1;
        const selectedSerien = ['maddrax', 'missionmars'];

        const params = new URLSearchParams();
        params.append('q', query);
        params.append('page', page);
        selectedSerien.forEach(s => params.append('serien[]', s));

        const url = `/kompendium/suche?${params.toString()}`;

        expect(url).toContain('q=maddrax');
        expect(url).toContain('page=1');
        expect(url).toContain('serien%5B%5D=maddrax');
        expect(url).toContain('serien%5B%5D=missionmars');
    });

    it('baut korrekte URL ohne Serien-Filter auf', () => {
        const params = new URLSearchParams();
        params.append('q', 'test');
        params.append('page', 1);

        const url = `/kompendium/suche?${params.toString()}`;

        expect(url).toBe('/kompendium/suche?q=test&page=1');
        expect(url).not.toContain('serien');
    });
});

describe('Kompendium Suche – Treffer-Template', () => {
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

    it('rendert Roman-Treffer korrekt', () => {
        const roman = {
            cycle: 'Maddrax - Die dunkle Zukunft der Erde',
            romanNr: '001',
            title: 'Der Gott aus dem Eis',
            snippets: ['…hier kommt der <mark>Suchbegriff</mark> im Text…'],
        };

        const html = tpl(roman);
        expect(html).toContain('Maddrax - Die dunkle Zukunft der Erde');
        expect(html).toContain('001');
        expect(html).toContain('Der Gott aus dem Eis');
        expect(html).toContain('<mark>Suchbegriff</mark>');
    });

    it('escaped XSS in Roman-Metadaten', () => {
        const roman = {
            cycle: '<script>alert("xss")</script>',
            romanNr: '001',
            title: 'Normal',
            snippets: [],
        };

        const html = tpl(roman);
        expect(html).not.toContain('<script>');
        expect(html).toContain('&lt;script&gt;');
    });

    it('rendert mehrere Snippets', () => {
        const roman = {
            cycle: 'Test',
            romanNr: '001',
            title: 'Test',
            snippets: ['Snippet 1', 'Snippet 2', 'Snippet 3'],
        };

        const html = tpl(roman);
        expect(html).toContain('Snippet 1');
        expect(html).toContain('Snippet 2');
        expect(html).toContain('Snippet 3');
        // Jeder Snippet in eigenem <p>-Tag
        expect((html.match(/<p class/g) || []).length).toBe(3);
    });

    it('rendert leere Snippet-Liste', () => {
        const roman = {
            cycle: 'Test',
            romanNr: '001',
            title: 'Test',
            snippets: [],
        };

        const html = tpl(roman);
        expect(html).not.toContain('<p class="mb-2');
    });
});
