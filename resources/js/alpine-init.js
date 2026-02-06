/**
 * Alpine.js Initialisierung — zentrale Logik
 *
 * Livewire 4 bündelt Alpine und setzt `window.Alpine` synchron beim Laden
 * seines Script-Tags. Auf Livewire-Seiten dürfen wir Alpine NICHT überschreiben
 * oder erneut starten, da Livewires Alpine `$wire`, `@entangle` und weitere
 * Extensions enthält.
 *
 * Auf Nicht-Livewire-Seiten (z.B. Kassenbuch mit reinem Blade + Alpine)
 * muss Alpine selbst geladen und gestartet werden.
 *
 * @param {object} alpineModule - Das importierte Alpine-Modul (z.B. `import Alpine from 'alpinejs'`)
 * @param {Array<Function|object>} plugins - Zu registrierende Plugins (z.B. [focus])
 * @returns {{ mode: 'livewire'|'standalone', alpine: object }}
 */
export function initAlpine(alpineModule, plugins = []) {
    if (window.Alpine) {
        // Livewire hat Alpine bereits geladen — nur Plugins registrieren.
        // WICHTIG: window.Alpine NICHT überschreiben, da es Livewire-Extensions enthält.
        if (typeof window.Alpine.plugin === 'function') {
            for (const plugin of plugins) {
                window.Alpine.plugin(plugin);
            }
        }
        return { mode: 'livewire', alpine: window.Alpine };
    }

    // Kein Livewire auf dieser Seite — Alpine selbst initialisieren.
    window.Alpine = alpineModule;
    if (typeof alpineModule.plugin === 'function') {
        for (const plugin of plugins) {
            alpineModule.plugin(plugin);
        }
    }
    if (typeof alpineModule.start === 'function') {
        alpineModule.start();
    }
    return { mode: 'standalone', alpine: alpineModule };
}

/**
 * Plant die Alpine-Initialisierung zeitlich korrekt ein.
 *
 * Livewire 4 mit `inject_assets: true` injiziert ein reguläres `<script>` Tag,
 * das `window.Alpine` synchron setzt. Da app.js als ES-Modul (deferred) läuft,
 * ist `window.Alpine` normalerweise schon gesetzt. Als Absicherung gegen
 * Ladereihenfolge-Änderungen (z.B. Preloads, Bundler-Änderungen, wire:navigate)
 * wird die Initialisierung auf `DOMContentLoaded` verzögert — zu diesem Zeitpunkt
 * haben garantiert alle synchronen Scripts (inkl. Livewires) ausgeführt.
 *
 * @param {object} alpineModule - Das importierte Alpine-Modul
 * @param {Array<Function|object>} plugins - Zu registrierende Plugins
 */
export function scheduleInitAlpine(alpineModule, plugins = []) {
    const run = () => initAlpine(alpineModule, plugins);

    if (document.readyState === 'loading') {
        // DOM wird noch geparst — warte auf DOMContentLoaded,
        // damit Livewires synchrones Script zuerst window.Alpine setzen kann.
        document.addEventListener('DOMContentLoaded', run, { once: true });
    } else {
        // DOM bereits fertig (z.B. bei dynamischem Nachladen) — sofort ausführen.
        run();
    }
}
