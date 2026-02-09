/**
 * E2E-Helfer für Livewire 4 und Alpine.js Interaktionen.
 *
 * Strategie (Round 17 – HTTP-basiert):
 * - KEIN page.waitForFunction(): Crasht Chromium-Renderer auf CI.
 * - waitForLivewire: page.waitForLoadState('networkidle').
 * - livewireCall/livewireSet: ZWEI Pfade:
 *   1. Fast Path: Livewire.find() → direkt nutzen (Firefox, <1ms)
 *   2. HTTP Fallback: Direkter POST an /livewire/update Endpoint
 *      → liest wire:snapshot + CSRF aus DOM (server-gerendert, immer verfügbar)
 *      → sendet HTTP-Request via fetch() (Browser-Kontext, Cookies inkl.)
 *      → ersetzt Component-HTML mit Server-Response
 *      → Livewire JS wird NICHT benötigt!
 * - setupLivewirePage: Injiziert __livewireHttp Helper via addInitScript.
 * - APP_URL + actionTimeout in playwright.config.js.
 * - forceShowModal: Direkte DOM-Manipulation für Alpine-Modals.
 */

/**
 * Registriert Diagnostik-Listener und injiziert den Livewire-HTTP-Helper.
 * MUSS einmal pro Test VOR der ersten page.goto() aufgerufen werden.
 *
 * addInitScript fügt ein <script>-Tag zum DOM hinzu (KEIN CDP Runtime.evaluate
 * wie waitForFunction). Es ist sicher und wird bei jeder Navigation neu injiziert.
 *
 * @param {import('@playwright/test').Page} page
 */
export async function setupLivewirePage(page) {
    page.on('pageerror', (err) => {
        console.log(`[E2E pageerror] ${err.message}`);
    });
    page.on('console', (msg) => {
        if (msg.type() === 'error') {
            console.log(`[E2E console.error] ${msg.text()}`);
        }
    });

    // Injiziere HTTP-Helper in den Browser-Kontext.
    // addInitScript ist sicher — es fügt ein <script>-Tag hinzu, KEINE CDP Script-Injection.
    // Wird bei jeder Navigation (goto, reload) automatisch neu injiziert.
    await page.addInitScript(() => {
        /**
         * Livewire-HTTP-Helper: Greift auf die Haupt-Livewire-Komponente zu.
         * Fast Path: Livewire.find() (wenn JS initialisiert, z.B. Firefox)
         * Fallback: HTTP POST an /livewire/update (wenn JS nie bootet, z.B. Chromium CI)
         *
         * @param {{ calls?: Array, updates?: object }} config
         * @returns {Promise<{ wire?: object, fast?: boolean, redirect?: string, ok?: boolean }>}
         */
        window.__livewireHttp = async function (config) {
            // Finde die Haupt-Komponente (nicht in der Navigation)
            const wireEls = [...document.querySelectorAll('[wire\\:id]')];
            const mainEl = wireEls.find((el) => !el.closest('nav')) || wireEls[0];
            if (!mainEl) {
                throw new Error(
                    `[livewireHttp] No [wire:id] element found. ` +
                        `Elements in DOM: ${document.querySelectorAll('*').length}`,
                );
            }

            const wireId = mainEl.getAttribute('wire:id');

            // ── Fast Path: Livewire JS funktioniert (Firefox, lokale Tests) ──
            if (window.Livewire?.initialRenderIsFinished) {
                const wire = window.Livewire.find(wireId);
                if (wire) return { wire, fast: true };
            }

            // ── HTTP Fallback: Livewire JS nicht initialisiert (Chromium CI) ──
            const snapshot = mainEl.getAttribute('wire:snapshot');
            if (!snapshot) {
                throw new Error(
                    `[livewireHttp] No wire:snapshot on wire:id="${wireId}". ` +
                        `Tag: ${mainEl.tagName}, classes: ${mainEl.className?.substring?.(0, 80)}`,
                );
            }

            // CSRF Token: primär vom Livewire Script-Tag, Fallback: <meta>
            const csrfToken =
                document.querySelector('[data-csrf]')?.getAttribute('data-csrf') ||
                document.querySelector('meta[name="csrf-token"]')?.content;
            if (!csrfToken) {
                throw new Error('[livewireHttp] No CSRF token (data-csrf / meta tag)');
            }

            // Update URI vom Livewire Script-Tag
            const updateUri =
                document.querySelector('[data-update-uri]')?.getAttribute('data-update-uri') ||
                '/livewire/update';

            // ── Formularwerte einsammeln ──
            // Ohne dies gehen client-seitig gesetzte Werte (.fill()) bei
            // DOM-Replacement verloren, weil der Server sie nicht kennt.
            const formValues = {};
            mainEl.querySelectorAll('input, textarea, select').forEach((el) => {
                const wireAttr = [...el.attributes].find((a) => a.name.startsWith('wire:model'));
                if (!wireAttr) return;
                const prop = wireAttr.value;
                if (!prop) return;
                if (el.type === 'checkbox') {
                    formValues[prop] = el.checked;
                } else if (el.type === 'radio') {
                    if (el.checked) formValues[prop] = el.value;
                } else {
                    formValues[prop] = el.value;
                }
            });

            // Explizite updates überschreiben auto-gesammelte Werte
            const mergedUpdates = { ...formValues, ...(config.updates || {}) };

            const response = await fetch(updateUri, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'text/html, application/xhtml+xml',
                    'X-Livewire': '',
                },
                body: JSON.stringify({
                    _token: csrfToken,
                    components: [
                        {
                            snapshot,
                            calls: config.calls || [],
                            updates: mergedUpdates,
                        },
                    ],
                }),
            });

            if (!response.ok) {
                const text = await response.text();
                throw new Error(
                    `[livewireHttp] HTTP ${response.status} ${updateUri}: ${text.substring(0, 300)}`,
                );
            }

            const data = await response.json();
            const comp = data.components?.[0];

            // Handle Redirect (z.B. nach save())
            if (comp?.effects?.redirect) {
                return { redirect: comp.effects.redirect };
            }

            // Apply HTML Update
            if (comp?.effects?.html) {
                mainEl.outerHTML = comp.effects.html;
            }

            // Update Snapshot + Re-Hydrate Formularwerte auf dem neuen Element
            const newEl = document.querySelector(`[wire\\:id="${wireId}"]`);
            if (newEl) {
                if (comp?.snapshot) {
                    newEl.setAttribute('wire:snapshot', comp.snapshot);
                }

                // Re-Hydrate: Setze Formularwerte aus mergedUpdates auf die neuen
                // DOM-Elemente. maryUI rendert KEINE Werte in wire:model Elementen
                // (verlässt sich auf Livewire JS, das auf Chromium CI nie bootet).
                // Ohne dies lesen nachfolgende auto-collects leere Werte und
                // überschreiben den Server-State bei sequentiellen Aufrufen.
                if (comp?.effects?.html) {
                    newEl.querySelectorAll('input, textarea, select').forEach((el) => {
                        const wireAttr = [...el.attributes].find((a) =>
                            a.name.startsWith('wire:model'),
                        );
                        if (!wireAttr) return;
                        const prop = wireAttr.value;
                        if (!prop || !(prop in mergedUpdates)) return;
                        const val = mergedUpdates[prop];
                        if (val === undefined || val === null) return;
                        if (el.type === 'checkbox') el.checked = !!val;
                        else if (el.type === 'radio')
                            el.checked = el.value === String(val);
                        else el.value = String(val);
                    });
                }
            }

            return { ok: true };
        };
    });
}

/**
 * Wartet bis die Seite fertig geladen ist (inkl. Livewire/Alpine AJAX-Requests).
 * Wirft NIEMALS.
 *
 * KEIN page.waitForFunction() — das injeziert Scripts via CDP Runtime.evaluate
 * und crasht den Chromium-Renderer auf CI mit "Target page, context or browser
 * has been closed". Stattdessen verwenden wir page.waitForLoadState('networkidle'),
 * das nur CDP-Netzwerk-Events überwacht ohne Script-Injection.
 *
 * @param {import('@playwright/test').Page} page
 * @param {object} [options]
 * @param {number} [options.timeout=10000] – max Wartezeit in ms
 */
export async function waitForLivewire(page, { timeout = 10000 } = {}) {
    try {
        await page.waitForLoadState('networkidle', { timeout });
    } catch {
        // Netzwerk nicht idle innerhalb des Timeouts — kein Problem.
        // Playwright's actionTimeout (15s) sorgt dafür dass Aktionen auf
        // Elemente warten. Die Tests haben eigene Fallback-Chains
        // (livewireCall, livewireSet, toPass-Retries, etc.).
    }
}

/**
 * Ruft eine Livewire-Methode direkt auf der Haupt-Seitenkomponente auf.
 * Umgeht wire:click Event-Handler komplett.
 *
 * Fast Path (Firefox): Livewire.find() → wire.method() (<1ms)
 * HTTP Fallback (Chromium CI): POST /livewire/update → DOM-Update (~100ms)
 *
 * @param {import('@playwright/test').Page} page
 * @param {string} method – Name der Livewire-Methode
 * @param {...any} args – Argumente für die Methode
 */
export async function livewireCall(page, method, ...args) {
    const result = await page.evaluate(
        async ({ method, args }) => {
            const res = await window.__livewireHttp({
                calls: [{ method, params: args, metadata: {} }],
                updates: {},
            });

            // Fast path: Livewire JS works → call method directly
            if (res.fast && res.wire) {
                await res.wire[method](...args);
                return {};
            }

            // HTTP result (redirect or HTML update already applied)
            return res.redirect ? { redirect: res.redirect } : {};
        },
        { method, args },
    );

    // Handle Redirect by navigating with Playwright
    if (result?.redirect) {
        await page.goto(result.redirect);
        try {
            await page.waitForLoadState('networkidle', { timeout: 10000 });
        } catch {}
    }
}

/**
 * Setzt eine Livewire-Property direkt (umgeht wire:model).
 *
 * Fast Path (Firefox): Livewire.find() → wire.$set() (<1ms)
 * HTTP Fallback (Chromium CI): POST /livewire/update → DOM-Update (~100ms)
 *
 * @param {import('@playwright/test').Page} page
 * @param {string} property – Name der Livewire-Property
 * @param {any} value – Neuer Wert
 */
export async function livewireSet(page, property, value) {
    const result = await page.evaluate(
        async ({ property, value }) => {
            const res = await window.__livewireHttp({
                calls: [],
                updates: { [property]: value },
            });

            // Fast path: Livewire JS works → use $set directly
            if (res.fast && res.wire) {
                await res.wire.$set(property, value);
                return {};
            }

            // HTTP result (redirect or HTML update already applied)
            return res.redirect ? { redirect: res.redirect } : {};
        },
        { property, value },
    );

    // Handle Redirect by navigating with Playwright
    if (result?.redirect) {
        await page.goto(result.redirect);
        try {
            await page.waitForLoadState('networkidle', { timeout: 10000 });
        } catch {}
    }
}

/**
 * Setzt mehrere Livewire-Properties und ruft optional eine Methode auf —
 * alles in EINEM einzigen HTTP-Request. Vermeidet Probleme mit Livewire's
 * updated*-Lifecycle-Hooks die bei sequentiellen Einzelaufrufen Werte
 * überschreiben können (z.B. updatedAuthorType setzt authorName = '').
 *
 * @param {import('@playwright/test').Page} page
 * @param {object} updates – Key-Value-Paare der Properties
 * @param {string} [method] – Optionale Methode die nach den Updates aufgerufen wird
 * @param {...any} args – Argumente für die Methode
 */
export async function livewireUpdate(page, updates, method, ...args) {
    const result = await page.evaluate(
        async ({ updates, method, args }) => {
            const config = {
                calls: method ? [{ method, params: args || [], metadata: {} }] : [],
                updates,
            };
            const res = await window.__livewireHttp(config);

            // Fast path: Livewire JS works → use $set + method call
            if (res.fast && res.wire) {
                for (const [prop, val] of Object.entries(updates)) {
                    await res.wire.$set(prop, val);
                }
                if (method) await res.wire[method](...(args || []));
                return {};
            }

            // HTTP result
            return res.redirect ? { redirect: res.redirect } : {};
        },
        { updates, method, args },
    );

    if (result?.redirect) {
        await page.goto(result.redirect);
        try {
            await page.waitForLoadState('networkidle', { timeout: 10000 });
        } catch {}
    }
}

/**
 * Setzt Alpine.js Komponenten-Daten direkt über das reaktive Proxy-Objekt.
 * Umgeht @click/$dispatch Events.
 *
 * @param {import('@playwright/test').Page} page
 * @param {string} selector – CSS-Selector innerhalb/auf dem x-data Element
 * @param {object} dataToSet – Key-Value-Paare die gemerged werden
 */
export async function setAlpineData(page, selector, dataToSet) {
    await page.evaluate(
        ({ selector, dataToSet }) => {
            const el = document.querySelector(selector);
            if (!el) throw new Error(`[setAlpineData] Element not found: ${selector}`);
            const xDataEl = el.closest('[x-data]') || el;

            // Try Alpine's internal reactive data stack (Proxy)
            if (xDataEl._x_dataStack && xDataEl._x_dataStack.length > 0) {
                Object.assign(xDataEl._x_dataStack[0], dataToSet);
                return;
            }

            // Fallback: Alpine.evaluate()
            if (window.Alpine?.evaluate) {
                const expr = Object.entries(dataToSet)
                    .map(([k, v]) => `${k} = ${JSON.stringify(v)}`)
                    .join('; ');
                window.Alpine.evaluate(xDataEl, expr);
                return;
            }

            throw new Error('[setAlpineData] No Alpine data access available');
        },
        { selector, dataToSet },
    );
}

/**
 * Dispatcht ein CustomEvent auf dem Window-Objekt.
 * Ersetzt Alpine's $dispatch für zuverlässigere E2E-Tests.
 *
 * @param {import('@playwright/test').Page} page
 * @param {string} eventName
 * @param {object} [detail]
 */
export async function dispatchWindowEvent(page, eventName, detail = {}) {
    await page.evaluate(
        ([name, det]) => {
            window.dispatchEvent(new CustomEvent(name, { detail: det, bubbles: true }));
        },
        [eventName, detail],
    );
}

/**
 * Zeigt ein Modal direkt per DOM-Manipulation an.
 * Umgeht Alpine x-show komplett für den Fall dass Alpine nicht initialisiert.
 *
 * Sucht das data-testid Element, findet den nächsten [x-data] Wrapper,
 * entfernt display:none von allen Elementen in der Kette.
 *
 * @param {import('@playwright/test').Page} page
 * @param {string} testId – data-testid des Dialog-Elements
 * @param {object} [data] – Optionale Daten die im Modal gesetzt werden
 */
export async function forceShowModal(page, testId, data = {}) {
    await page.evaluate(
        ({ testId, data }) => {
            const dialog = document.querySelector(`[data-testid="${testId}"]`);
            if (!dialog) throw new Error(`[forceShowModal] data-testid="${testId}" not found`);

            // Finde den x-data Wrapper (äußerstes Element das x-data hat)
            const xDataWrapper = dialog.closest('[x-data]');
            const target = xDataWrapper || dialog;

            // Entferne display:none vom Wrapper und allen x-show Kindern
            target.style.setProperty('display', 'block', 'important');
            target.querySelectorAll('[x-show]').forEach((el) => {
                el.style.setProperty('display', '', 'important');
                el.style.removeProperty('display');
                if (getComputedStyle(el).display === 'none') {
                    el.style.setProperty('display', 'block', 'important');
                }
            });
            // Dialog selbst sichtbar machen
            dialog.style.setProperty('display', 'block', 'important');

            // Versuche Alpine-Daten zu setzen (open + eventuelle Daten)
            if (xDataWrapper?._x_dataStack?.length > 0) {
                Object.assign(xDataWrapper._x_dataStack[0], { open: true, ...data });
            }

            // Setze Formulardaten direkt auf die Input-Elemente
            if (data && Object.keys(data).length > 0) {
                const form = dialog.querySelector('form') || target.querySelector('form');
                if (form) {
                    for (const [key, value] of Object.entries(data)) {
                        const input = form.querySelector(`[name="${key}"], [x-model="${key}"], #${key}`);
                        if (input) {
                            input.value = value;
                            input.dispatchEvent(new Event('input', { bubbles: true }));
                        }
                    }
                }
            }
        },
        { testId, data },
    );
}

/**
 * Setzt die action-URL eines Formulars innerhalb eines Modals direkt.
 * Umgeht Alpine :action Bindings.
 *
 * @param {import('@playwright/test').Page} page
 * @param {string} testId – data-testid des Dialog-/Form-Containers
 * @param {string} action – Die URL für das form action
 */
export async function setFormAction(page, testId, action) {
    await page.evaluate(
        ({ testId, action }) => {
            const container = document.querySelector(`[data-testid="${testId}"]`);
            if (!container) return;
            const form = container.querySelector('form') || container.closest('form');
            if (form) {
                form.setAttribute('action', action);
            }
        },
        { testId, action },
    );
}
