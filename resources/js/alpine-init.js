/**
 * Alpine.js Initialisierung — zentrale Logik
 *
 * Livewire 4 bündelt Alpine und setzt `window.Alpine` synchron,
 * BEVOR ES-Module (wie app.js) ausgeführt werden.
 * Auf Livewire-Seiten dürfen wir Alpine NICHT überschreiben oder erneut starten,
 * da Livewires Alpine `$wire`, `@entangle` und weitere Extensions enthält.
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
        for (const plugin of plugins) {
            window.Alpine.plugin(plugin);
        }
        return { mode: 'livewire', alpine: window.Alpine };
    }

    // Kein Livewire auf dieser Seite — Alpine selbst initialisieren.
    window.Alpine = alpineModule;
    for (const plugin of plugins) {
        alpineModule.plugin(plugin);
    }
    alpineModule.start();
    return { mode: 'standalone', alpine: alpineModule };
}
