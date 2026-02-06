import { beforeEach, describe, expect, it, vi } from 'vitest';
import { initAlpine, scheduleInitAlpine } from '@/alpine-init.js';

/**
 * Tests für die Alpine.js-Initialisierungslogik.
 *
 * Kernproblem das verhindert werden muss:
 * Livewire 4 setzt window.Alpine synchron mit seiner eigenen Alpine-Instanz,
 * die $wire, @entangle und andere Livewire-Extensions enthält.
 * Wenn app.js diese Instanz überschreibt, brechen MaryUI-Komponenten
 * wie <x-file> (die @entangle nutzen).
 */
describe('alpine-init', () => {
    let mockAlpineModule;

    beforeEach(() => {
        // Sauberer Zustand — kein Alpine auf window
        delete window.Alpine;

        // Mock eines Alpine-Moduls (wie `import Alpine from 'alpinejs'`)
        mockAlpineModule = {
            plugin: vi.fn(),
            start: vi.fn(),
            version: '3.x-app-bundle',
        };
    });

    describe('Standalone-Modus (Nicht-Livewire-Seiten)', () => {
        it('setzt window.Alpine auf das übergebene Modul', () => {
            initAlpine(mockAlpineModule);

            expect(window.Alpine).toBe(mockAlpineModule);
        });

        it('gibt mode "standalone" zurück', () => {
            const result = initAlpine(mockAlpineModule);

            expect(result.mode).toBe('standalone');
            expect(result.alpine).toBe(mockAlpineModule);
        });

        it('ruft Alpine.start() auf', () => {
            initAlpine(mockAlpineModule);

            expect(mockAlpineModule.start).toHaveBeenCalledTimes(1);
        });

        it('registriert übergebene Plugins auf dem eigenen Alpine', () => {
            const pluginA = vi.fn();
            const pluginB = vi.fn();

            initAlpine(mockAlpineModule, [pluginA, pluginB]);

            expect(mockAlpineModule.plugin).toHaveBeenCalledTimes(2);
            expect(mockAlpineModule.plugin).toHaveBeenCalledWith(pluginA);
            expect(mockAlpineModule.plugin).toHaveBeenCalledWith(pluginB);
        });

        it('funktioniert ohne Plugins', () => {
            const result = initAlpine(mockAlpineModule, []);

            expect(result.mode).toBe('standalone');
            expect(mockAlpineModule.plugin).not.toHaveBeenCalled();
            expect(mockAlpineModule.start).toHaveBeenCalledTimes(1);
        });
    });

    describe('Livewire-Modus (Seiten mit Livewire-Komponenten)', () => {
        let livewireAlpine;

        beforeEach(() => {
            // Simuliert was Livewire 4 tut: setzt window.Alpine synchron
            // mit seiner eigenen Alpine-Instanz (enthält $wire, @entangle, etc.)
            livewireAlpine = {
                plugin: vi.fn(),
                start: vi.fn(),
                version: '3.x-livewire-bundle',
                // Livewire-spezifische Marker
                __fromLivewire: true,
            };
            window.Alpine = livewireAlpine;
        });

        it('überschreibt window.Alpine NICHT', () => {
            initAlpine(mockAlpineModule);

            // window.Alpine muss immer noch Livewires Instanz sein
            expect(window.Alpine).toBe(livewireAlpine);
            expect(window.Alpine).not.toBe(mockAlpineModule);
        });

        it('gibt mode "livewire" zurück', () => {
            const result = initAlpine(mockAlpineModule);

            expect(result.mode).toBe('livewire');
            expect(result.alpine).toBe(livewireAlpine);
        });

        it('ruft NICHT Alpine.start() auf dem App-Modul auf', () => {
            initAlpine(mockAlpineModule);

            expect(mockAlpineModule.start).not.toHaveBeenCalled();
        });

        it('ruft NICHT Alpine.start() auf Livewires Alpine auf', () => {
            initAlpine(mockAlpineModule);

            expect(livewireAlpine.start).not.toHaveBeenCalled();
        });

        it('registriert Plugins auf Livewires Alpine, nicht auf dem App-Modul', () => {
            const focus = vi.fn();

            initAlpine(mockAlpineModule, [focus]);

            // Plugin wird auf Livewires Alpine registriert
            expect(livewireAlpine.plugin).toHaveBeenCalledWith(focus);
            expect(livewireAlpine.plugin).toHaveBeenCalledTimes(1);

            // NICHT auf dem App-Modul
            expect(mockAlpineModule.plugin).not.toHaveBeenCalled();
        });

        it('registriert mehrere Plugins auf Livewires Alpine', () => {
            const focus = vi.fn();
            const persist = vi.fn();
            const collapse = vi.fn();

            initAlpine(mockAlpineModule, [focus, persist, collapse]);

            expect(livewireAlpine.plugin).toHaveBeenCalledTimes(3);
            expect(livewireAlpine.plugin).toHaveBeenCalledWith(focus);
            expect(livewireAlpine.plugin).toHaveBeenCalledWith(persist);
            expect(livewireAlpine.plugin).toHaveBeenCalledWith(collapse);
        });
    });

    describe('Edge Cases', () => {
        it('erkennt Livewire-Alpine auch ohne __fromLivewire Marker', () => {
            // Livewire setzt __fromLivewire erst nach der Zuweisung,
            // aber window.Alpine existiert bereits — das reicht als Guard
            window.Alpine = {
                plugin: vi.fn(),
                start: vi.fn(),
                version: '3.x-livewire',
            };

            const result = initAlpine(mockAlpineModule);

            expect(result.mode).toBe('livewire');
            expect(window.Alpine.version).toBe('3.x-livewire');
        });

        it('behandelt leeres Alpine-Objekt auf window korrekt', () => {
            // Auch ein minimales Alpine-Objekt auf window zählt als "Livewire aktiv"
            window.Alpine = { plugin: vi.fn() };

            const result = initAlpine(mockAlpineModule);

            expect(result.mode).toBe('livewire');
            expect(mockAlpineModule.start).not.toHaveBeenCalled();
        });

        it('window.Alpine = undefined wird als Nicht-Livewire behandelt', () => {
            window.Alpine = undefined;

            const result = initAlpine(mockAlpineModule);

            expect(result.mode).toBe('standalone');
            expect(window.Alpine).toBe(mockAlpineModule);
        });

        it('window.Alpine = null wird als Nicht-Livewire behandelt', () => {
            window.Alpine = null;

            const result = initAlpine(mockAlpineModule);

            expect(result.mode).toBe('standalone');
            expect(window.Alpine).toBe(mockAlpineModule);
        });

        it('window.Alpine = false wird als Nicht-Livewire behandelt', () => {
            window.Alpine = false;

            const result = initAlpine(mockAlpineModule);

            expect(result.mode).toBe('standalone');
            expect(window.Alpine).toBe(mockAlpineModule);
        });

        it('Plugins-Reihenfolge wird beibehalten', () => {
            const callOrder = [];
            const pluginA = () => callOrder.push('A');
            const pluginB = () => callOrder.push('B');
            const pluginC = () => callOrder.push('C');

            // Im Standalone-Modus
            mockAlpineModule.plugin = vi.fn((fn) => fn());
            initAlpine(mockAlpineModule, [pluginA, pluginB, pluginC]);

            expect(callOrder).toEqual(['A', 'B', 'C']);
        });

        it('Plugins-Reihenfolge bleibt im Livewire-Modus erhalten', () => {
            const callOrder = [];
            const pluginA = () => callOrder.push('A');
            const pluginB = () => callOrder.push('B');

            window.Alpine = { plugin: vi.fn((fn) => fn()) };

            initAlpine(mockAlpineModule, [pluginA, pluginB]);

            expect(callOrder).toEqual(['A', 'B']);
        });
    });

    describe('Regression: Doppelte Alpine-Instanz (MaryUI @entangle Bug)', () => {
        it('überschreibt NIEMALS ein existierendes window.Alpine', () => {
            // Dies ist der exakte Bug der aufgetreten ist:
            // Livewire setzt window.Alpine mit Extensions ($wire, @entangle)
            // app.js darf es NICHT mit einem "nackten" Alpine überschreiben
            const livewireAlpine = {
                plugin: vi.fn(),
                start: vi.fn(),
                $wire: { entangle: vi.fn() }, // Livewire-Extension
                _x_dataStack: ['existing'], // Bereits initialisiert
            };
            window.Alpine = livewireAlpine;

            initAlpine(mockAlpineModule);

            expect(window.Alpine).toBe(livewireAlpine);
            expect(window.Alpine.$wire).toBeDefined();
            expect(window.Alpine.$wire.entangle).toBeDefined();
        });

        it('verhindert doppeltes Alpine.start()', () => {
            window.Alpine = {
                plugin: vi.fn(),
                start: vi.fn(),
            };

            initAlpine(mockAlpineModule);

            // Weder Livewires Alpine noch das App-Modul dürfen start() aufrufen
            expect(window.Alpine.start).not.toHaveBeenCalled();
            expect(mockAlpineModule.start).not.toHaveBeenCalled();
        });

        it('prüft nicht _x_dataStack als Guard (alter fehlerhafter Guard)', () => {
            // Der alte Guard war: if (!window.Alpine?._x_dataStack)
            // Das schlug fehl weil _x_dataStack erst bei Alpine.start() gesetzt wird
            // Neuer Guard prüft nur: if (window.Alpine)
            window.Alpine = {
                plugin: vi.fn(),
                start: vi.fn(),
                // _x_dataStack fehlt absichtlich (Alpine noch nicht gestartet)
            };

            initAlpine(mockAlpineModule);

            // Trotz fehlendem _x_dataStack darf window.Alpine NICHT überschrieben werden
            expect(window.Alpine).not.toBe(mockAlpineModule);
            expect(mockAlpineModule.start).not.toHaveBeenCalled();
        });

        it('Standalone-Modus setzt Alpine korrekt auf Nicht-Livewire-Seiten', () => {
            // Auf Seiten ohne Livewire muss Alpine trotzdem funktionieren
            // (z.B. Kassenbuch, reine Blade+Alpine Seiten)
            delete window.Alpine;

            initAlpine(mockAlpineModule, [vi.fn()]);

            expect(window.Alpine).toBe(mockAlpineModule);
            expect(mockAlpineModule.start).toHaveBeenCalledTimes(1);
            expect(mockAlpineModule.plugin).toHaveBeenCalledTimes(1);
        });
    });

    describe('Defensive Guards (typeof-Prüfungen)', () => {
        it('crasht nicht wenn window.Alpine.plugin keine Funktion ist', () => {
            window.Alpine = { version: '3.x', plugin: 'not-a-function' };

            expect(() => initAlpine(mockAlpineModule, [vi.fn()])).not.toThrow();
            expect(window.Alpine.version).toBe('3.x');
        });

        it('crasht nicht wenn window.Alpine.plugin fehlt', () => {
            window.Alpine = { version: '3.x' };

            expect(() => initAlpine(mockAlpineModule, [vi.fn()])).not.toThrow();
        });

        it('crasht nicht wenn alpineModule.plugin keine Funktion ist', () => {
            delete window.Alpine;
            mockAlpineModule.plugin = undefined;

            expect(() => initAlpine(mockAlpineModule, [vi.fn()])).not.toThrow();
        });

        it('crasht nicht wenn alpineModule.start keine Funktion ist', () => {
            delete window.Alpine;
            mockAlpineModule.start = 'not-a-function';

            expect(() => initAlpine(mockAlpineModule, [vi.fn()])).not.toThrow();
        });

        it('crasht nicht wenn alpineModule.start fehlt', () => {
            delete window.Alpine;
            mockAlpineModule.start = undefined;

            expect(() => initAlpine(mockAlpineModule)).not.toThrow();
            expect(window.Alpine).toBe(mockAlpineModule);
        });

        it('registriert Plugins im Livewire-Modus nur wenn plugin eine Funktion ist', () => {
            const pluginFn = vi.fn();
            window.Alpine = { plugin: vi.fn() };

            initAlpine(mockAlpineModule, [pluginFn]);

            expect(window.Alpine.plugin).toHaveBeenCalledWith(pluginFn);
        });

        it('überspringt Plugins im Livewire-Modus wenn plugin keine Funktion ist', () => {
            window.Alpine = { plugin: 42 };

            const result = initAlpine(mockAlpineModule, [vi.fn()]);

            // Darf nicht crashen, Modus wird korrekt erkannt
            expect(result.mode).toBe('livewire');
        });

        it('setzt window.Alpine auch ohne start/plugin', () => {
            delete window.Alpine;
            const bareModule = {};

            initAlpine(bareModule, [vi.fn()]);

            expect(window.Alpine).toBe(bareModule);
        });
    });
});

describe('scheduleInitAlpine', () => {
    let mockAlpineModule;

    beforeEach(() => {
        delete window.Alpine;
        // readyState auf 'complete' zurücksetzen (Standard in jsdom)
        Object.defineProperty(document, 'readyState', {
            value: 'complete',
            writable: true,
            configurable: true,
        });
        // Verwaiste { once: true } DOMContentLoaded-Listener auflösen,
        // die von vorherigen Tests registriert aber nicht gefeuert wurden.
        document.dispatchEvent(new Event('DOMContentLoaded'));
        delete window.Alpine;

        mockAlpineModule = {
            plugin: vi.fn(),
            start: vi.fn(),
            version: '3.x-app-bundle',
        };
    });

    describe('DOM noch am Laden (readyState === "loading")', () => {
        it('verzögert auf DOMContentLoaded wenn window.Alpine nicht gesetzt ist', () => {
            // Simuliert: DOM wird noch geparst, Livewires Script hat noch nicht geladen
            Object.defineProperty(document, 'readyState', {
                value: 'loading',
                writable: true,
                configurable: true,
            });
            const addEventSpy = vi.spyOn(document, 'addEventListener');

            scheduleInitAlpine(mockAlpineModule);

            // Alpine darf noch NICHT gestartet worden sein
            expect(mockAlpineModule.start).not.toHaveBeenCalled();
            expect(window.Alpine).toBeUndefined();

            // Stattdessen wurde DOMContentLoaded-Listener registriert
            expect(addEventSpy).toHaveBeenCalledWith(
                'DOMContentLoaded',
                expect.any(Function),
                { once: true },
            );

            addEventSpy.mockRestore();
            Object.defineProperty(document, 'readyState', {
                value: 'complete',
                writable: true,
                configurable: true,
            });
        });

        it('registriert Plugins sofort wenn Livewire-Alpine bereits vorhanden (Short-Circuit)', () => {
            // Szenario: Livewires reguläres Script wurde bereits synchron ausgeführt
            // und hat window.Alpine gesetzt, aber DOM ist noch am Laden.
            // Plugins müssen sofort registriert werden, damit sie wirksam sind
            // bevor Livewire Alpine.start() bei DOMContentLoaded aufruft.
            Object.defineProperty(document, 'readyState', {
                value: 'loading',
                writable: true,
                configurable: true,
            });
            const addEventSpy = vi.spyOn(document, 'addEventListener');

            const livewireAlpine = {
                plugin: vi.fn(),
                start: vi.fn(),
                __fromLivewire: true,
            };
            window.Alpine = livewireAlpine;

            const focus = vi.fn();
            scheduleInitAlpine(mockAlpineModule, [focus]);

            // Plugin wurde SOFORT auf Livewires Alpine registriert
            expect(livewireAlpine.plugin).toHaveBeenCalledWith(focus);

            // window.Alpine wurde NICHT überschrieben
            expect(window.Alpine).toBe(livewireAlpine);

            // Kein DOMContentLoaded-Listener nötig
            const domContentLoadedCalls = addEventSpy.mock.calls.filter(
                ([event]) => event === 'DOMContentLoaded',
            );
            expect(domContentLoadedCalls).toHaveLength(0);

            // Kein start() auf dem App-Modul
            expect(mockAlpineModule.start).not.toHaveBeenCalled();

            addEventSpy.mockRestore();
            Object.defineProperty(document, 'readyState', {
                value: 'complete',
                writable: true,
                configurable: true,
            });
        });

        it('nutzt { once: true } um doppelte Ausführung zu vermeiden', () => {
            Object.defineProperty(document, 'readyState', {
                value: 'loading',
                writable: true,
                configurable: true,
            });
            const addEventSpy = vi.spyOn(document, 'addEventListener');

            scheduleInitAlpine(mockAlpineModule);

            const call = addEventSpy.mock.calls.find(
                ([event]) => event === 'DOMContentLoaded',
            );
            expect(call).toBeDefined();
            expect(call[2]).toEqual({ once: true });

            addEventSpy.mockRestore();
            Object.defineProperty(document, 'readyState', {
                value: 'complete',
                writable: true,
                configurable: true,
            });
        });

        it('initialisiert Alpine im Standalone-Modus wenn DOMContentLoaded feuert (kein Livewire)', () => {
            Object.defineProperty(document, 'readyState', {
                value: 'loading',
                writable: true,
                configurable: true,
            });

            scheduleInitAlpine(mockAlpineModule);

            // Noch nicht initialisiert
            expect(window.Alpine).toBeUndefined();

            // Simuliere DOMContentLoaded
            Object.defineProperty(document, 'readyState', {
                value: 'interactive',
                writable: true,
                configurable: true,
            });
            document.dispatchEvent(new Event('DOMContentLoaded'));

            // Jetzt muss Alpine initialisiert sein
            expect(window.Alpine).toBe(mockAlpineModule);
            expect(mockAlpineModule.start).toHaveBeenCalledTimes(1);

            Object.defineProperty(document, 'readyState', {
                value: 'complete',
                writable: true,
                configurable: true,
            });
        });

        it('erkennt Livewires Alpine wenn DOMContentLoaded feuert', () => {
            Object.defineProperty(document, 'readyState', {
                value: 'loading',
                writable: true,
                configurable: true,
            });
            const focus = vi.fn();

            scheduleInitAlpine(mockAlpineModule, [focus]);

            // Livewire setzt window.Alpine bevor DOMContentLoaded feuert
            // (reguläres Script, wird synchron vor Modules ausgeführt)
            const livewireAlpine = {
                plugin: vi.fn(),
                start: vi.fn(),
                __fromLivewire: true,
            };
            window.Alpine = livewireAlpine;

            // DOMContentLoaded feuert
            document.dispatchEvent(new Event('DOMContentLoaded'));

            // window.Alpine muss Livewires Instanz bleiben
            expect(window.Alpine).toBe(livewireAlpine);
            expect(window.Alpine).not.toBe(mockAlpineModule);

            // Plugin wurde auf Livewires Alpine registriert
            expect(livewireAlpine.plugin).toHaveBeenCalledWith(focus);

            // Kein start() auf dem App-Modul
            expect(mockAlpineModule.start).not.toHaveBeenCalled();

            Object.defineProperty(document, 'readyState', {
                value: 'complete',
                writable: true,
                configurable: true,
            });
        });
    });

    describe('DOM bereits geladen (readyState !== "loading")', () => {
        it('führt initAlpine sofort aus bei readyState "interactive"', () => {
            Object.defineProperty(document, 'readyState', {
                value: 'interactive',
                writable: true,
                configurable: true,
            });

            scheduleInitAlpine(mockAlpineModule);

            expect(window.Alpine).toBe(mockAlpineModule);
            expect(mockAlpineModule.start).toHaveBeenCalledTimes(1);

            Object.defineProperty(document, 'readyState', {
                value: 'complete',
                writable: true,
                configurable: true,
            });
        });

        it('führt initAlpine sofort aus bei readyState "complete"', () => {
            Object.defineProperty(document, 'readyState', {
                value: 'complete',
                writable: true,
                configurable: true,
            });

            scheduleInitAlpine(mockAlpineModule);

            expect(window.Alpine).toBe(mockAlpineModule);
            expect(mockAlpineModule.start).toHaveBeenCalledTimes(1);
        });

        it('erkennt vorhandenes Livewire-Alpine sofort', () => {
            const livewireAlpine = { plugin: vi.fn(), __fromLivewire: true };
            window.Alpine = livewireAlpine;

            Object.defineProperty(document, 'readyState', {
                value: 'complete',
                writable: true,
                configurable: true,
            });

            const focus = vi.fn();
            scheduleInitAlpine(mockAlpineModule, [focus]);

            expect(window.Alpine).toBe(livewireAlpine);
            expect(livewireAlpine.plugin).toHaveBeenCalledWith(focus);
            expect(mockAlpineModule.start).not.toHaveBeenCalled();
        });
    });

    describe('Regression: Ladereihenfolge-Sicherheit', () => {
        it('verhindert Standalone-Start wenn Livewire-Script noch laden muss', () => {
            // Szenario: app.js (Module) wird geparst während DOM noch lädt.
            // Livewires reguläres Script kommt danach im DOM.
            // scheduleInitAlpine darf Alpine NICHT sofort starten.
            Object.defineProperty(document, 'readyState', {
                value: 'loading',
                writable: true,
                configurable: true,
            });

            scheduleInitAlpine(mockAlpineModule);

            // Alpine noch nicht gestartet — wartet auf DOMContentLoaded
            expect(mockAlpineModule.start).not.toHaveBeenCalled();
            expect(window.Alpine).toBeUndefined();

            // Livewire-Script lädt und setzt window.Alpine
            window.Alpine = { plugin: vi.fn(), start: vi.fn(), __fromLivewire: true };

            // DOMContentLoaded feuert
            document.dispatchEvent(new Event('DOMContentLoaded'));

            // initAlpine läuft jetzt im Livewire-Modus
            expect(window.Alpine.__fromLivewire).toBe(true);
            expect(mockAlpineModule.start).not.toHaveBeenCalled();

            Object.defineProperty(document, 'readyState', {
                value: 'complete',
                writable: true,
                configurable: true,
            });
        });

        it('startet Alpine standalone wenn kein Livewire auf der Seite ist', () => {
            // Szenario: Nicht-Livewire-Seite (z.B. Kassenbuch)
            // inject_assets injiziert kein Script, window.Alpine bleibt leer
            Object.defineProperty(document, 'readyState', {
                value: 'loading',
                writable: true,
                configurable: true,
            });

            const focus = vi.fn();
            scheduleInitAlpine(mockAlpineModule, [focus]);

            // DOMContentLoaded ohne dass Livewire window.Alpine gesetzt hat
            document.dispatchEvent(new Event('DOMContentLoaded'));

            expect(window.Alpine).toBe(mockAlpineModule);
            expect(mockAlpineModule.start).toHaveBeenCalledTimes(1);
            expect(mockAlpineModule.plugin).toHaveBeenCalledWith(focus);

            Object.defineProperty(document, 'readyState', {
                value: 'complete',
                writable: true,
                configurable: true,
            });
        });
    });
});
