/**
 * Tests für resources/js/polls/charts.js
 * Issue #494: Browser-Crash-Prevention durch Guards für leere Daten
 */
import { vi } from 'vitest';

vi.mock('chart.js', () => {
    // Klasse wird hier innerhalb der Factory definiert
    class MockChart {
        constructor(ctx, config) {
            // Verwende globale Variable statt Klasseneigenschaft
            globalThis.__chartMockCalls = globalThis.__chartMockCalls || [];
            globalThis.__chartMockCalls.push([ctx, config]);
            this.type = config?.type;
        }

        destroy() {
            globalThis.__destroyCallCount = (globalThis.__destroyCallCount || 0) + 1;
        }
    }
    MockChart.register = vi.fn();

    return {
        ArcElement: vi.fn(),
        BarController: vi.fn(),
        BarElement: vi.fn(),
        CategoryScale: vi.fn(),
        Chart: MockChart,
        DoughnutController: vi.fn(),
        Filler: vi.fn(),
        Legend: vi.fn(),
        LineController: vi.fn(),
        LineElement: vi.fn(),
        LinearScale: vi.fn(),
        PointElement: vi.fn(),
        Tooltip: vi.fn(),
    };
});

describe('polls/charts.js - updateCharts Guards', () => {
    let originalGetContext;
    let mount, cleanup;

    // Modul nur einmal importieren, um Event-Listener-Akkumulation zu vermeiden
    beforeAll(async () => {
        ({ mountPollCharts: mount } = await import('@/polls/charts.js'));
    });

    beforeEach(() => {
        originalGetContext = HTMLCanvasElement.prototype.getContext;
        // Reset DOM
        document.body.innerHTML = '';
        // Reset Mock-Aufrufe über globalThis
        globalThis.__chartMockCalls = [];
        globalThis.__destroyCallCount = 0;

        // Mock getContext für Canvas-Elemente
        HTMLCanvasElement.prototype.getContext = vi.fn(() => ({}));
        cleanup = mount(document.body);
    });

    afterEach(() => {
        cleanup();
        HTMLCanvasElement.prototype.getContext = originalGetContext;
        vi.restoreAllMocks();
    });

    /**
     * Helper: Erstellt die benötigten Canvas-Elemente und Theme-Color-Marker
     */
    const setupChartDOM = () => {
        // Canvas-Elemente
        const optionsCanvas = document.createElement('canvas');
        optionsCanvas.id = 'poll-options-chart';
        document.body.appendChild(optionsCanvas);

        const timelineCanvas = document.createElement('canvas');
        timelineCanvas.id = 'poll-timeline-chart';
        document.body.appendChild(timelineCanvas);

        const segmentCanvas = document.createElement('canvas');
        segmentCanvas.id = 'poll-segment-chart';
        document.body.appendChild(segmentCanvas);

        // Theme-Color-Marker
        const membersMarker = document.createElement('span');
        membersMarker.setAttribute('data-omxfc-poll-color', 'members');
        membersMarker.style.color = 'rgb(79, 70, 229)';
        document.body.appendChild(membersMarker);

        const guestsMarker = document.createElement('span');
        guestsMarker.setAttribute('data-omxfc-poll-color', 'guests');
        guestsMarker.style.color = 'rgb(75, 85, 99)';
        document.body.appendChild(guestsMarker);
    };

    it('reads current DOM data on mount and after a Livewire morph, and ignores invalid configuration', async () => {
        setupChartDOM();
        const data = document.createElement('div');
        data.setAttribute('data-omxfc-poll-chart-data', '');
        document.body.appendChild(data);
        const flush = async () => { for (let i = 0; i < 5; i++) await Promise.resolve(); };
        await flush();
        data.setAttribute('data-omxfc-poll-chart-data', '{bad');
        await flush();
        expect(globalThis.__chartMockCalls).toHaveLength(0);
        const payload = { options: { labels: ['A'], members: [2], guests: [1] }, totals: { members: 2, guests: 1 }, timeline: [] };
        data.setAttribute('data-omxfc-poll-chart-data', JSON.stringify(payload));
        await flush();
        expect(globalThis.__chartMockCalls).toHaveLength(3);
        data.setAttribute('data-omxfc-poll-chart-data', JSON.stringify({ ...payload, options: { ...payload.options, labels: ['B'] } }));
        await flush();
        expect(globalThis.__chartMockCalls.at(-3)[1].data.labels).toEqual(['B']);
        expect(globalThis.__destroyCallCount).toBeGreaterThanOrEqual(3);
    });

    it.each(['rgba(12, 34, 56, 0.4)', 'oklch(0.5 0.1 20)', ''])('supports theme color %s and missing color markers', color => {
        setupChartDOM();
        vi.spyOn(window, 'getComputedStyle').mockReturnValue({ color });
        window.dispatchEvent(new CustomEvent('poll-results-updated', { detail: { options: { labels: ['A'] } } }));
        expect(globalThis.__chartMockCalls).toHaveLength(2);
        document.querySelectorAll('[data-omxfc-poll-color]').forEach(el => el.remove());
        window.dispatchEvent(new CustomEvent('poll-results-updated', { detail: { options: { labels: ['A'] } } }));
        expect(globalThis.__chartMockCalls).toHaveLength(4);
    });

    it('sollte bei null-Daten nicht crashen', () => {
        setupChartDOM();

        // Simuliere Event mit null-Daten
        const event = new CustomEvent('poll-results-updated', {
            detail: { data: null },
        });

        // Sollte nicht werfen
        expect(() => {
            window.dispatchEvent(event);
        }).not.toThrow();

        // Chart sollte nicht erstellt worden sein
        expect(globalThis.__chartMockCalls.length).toBe(0);
    });

    it('sollte bei leerem Objekt nicht crashen', () => {
        setupChartDOM();

        const event = new CustomEvent('poll-results-updated', {
            detail: { data: {} },
        });

        expect(() => {
            window.dispatchEvent(event);
        }).not.toThrow();

        expect(globalThis.__chartMockCalls.length).toBe(0);
    });

    it('sollte bei fehlendem options-Objekt nicht crashen', () => {
        setupChartDOM();

        const event = new CustomEvent('poll-results-updated', {
            detail: {
                data: {
                    totals: { members: 0, guests: 0 },
                    timeline: [],
                },
            },
        });

        expect(() => {
            window.dispatchEvent(event);
        }).not.toThrow();

        expect(globalThis.__chartMockCalls.length).toBe(0);
    });

    it('sollte bei leerem labels-Array nicht crashen', () => {
        setupChartDOM();

        const event = new CustomEvent('poll-results-updated', {
            detail: {
                data: {
                    options: {
                        labels: [],
                        members: [],
                        guests: [],
                        total: [],
                    },
                    totals: { members: 0, guests: 0, totalVotes: 0 },
                    timeline: [],
                },
            },
        });

        expect(() => {
            window.dispatchEvent(event);
        }).not.toThrow();

        expect(globalThis.__chartMockCalls.length).toBe(0);
    });

    it('sollte bei labels als nicht-Array nicht crashen', () => {
        setupChartDOM();

        const event = new CustomEvent('poll-results-updated', {
            detail: {
                data: {
                    options: {
                        labels: 'not-an-array',
                        members: [],
                        guests: [],
                    },
                },
            },
        });

        expect(() => {
            window.dispatchEvent(event);
        }).not.toThrow();

        expect(globalThis.__chartMockCalls.length).toBe(0);
    });

    it('sollte bei gültigen Labels aber 0 Stimmen keinen Doughnut-Chart erstellen', () => {
        setupChartDOM();

        // Merke den aktuellen Stand VOR dem Event
        const callCountBefore = globalThis.__chartMockCalls.length;

        const event = new CustomEvent('poll-results-updated', {
            detail: {
                data: {
                    options: {
                        labels: ['Option A', 'Option B'],
                        members: [0, 0],
                        guests: [0, 0],
                        total: [0, 0],
                    },
                    totals: { members: 0, guests: 0, totalVotes: 0 },
                    timeline: [],
                },
            },
        });

        expect(() => {
            window.dispatchEvent(event);
        }).not.toThrow();

        // Hole nur die neuen Aufrufe seit dem Event
        const newCalls = globalThis.__chartMockCalls.slice(callCountBefore);

        // Es sollten neue Charts erstellt worden sein
        expect(newCalls.length).toBeGreaterThan(0);

        // Prüfe dass unter den neuen Aufrufen KEIN doughnut ist (das ist der Kern des Tests)
        const chartTypes = newCalls.map(call => call[1].type);
        expect(chartTypes).not.toContain('doughnut');
        // Aber bar und line sollten dabei sein
        expect(chartTypes).toContain('bar');
        expect(chartTypes).toContain('line');
    });

    it('sollte bei fehlenden Canvas-Elementen bestehende Charts zerstören', () => {
        // Zuerst mit Canvas-Elementen initialisieren
        setupChartDOM();

        // Erstes Update mit gültigen Daten und Stimmen
        const firstEvent = new CustomEvent('poll-results-updated', {
            detail: {
                data: {
                    options: {
                        labels: ['Option A'],
                        members: [5],
                        guests: [3],
                        total: [8],
                    },
                    totals: { members: 5, guests: 3, totalVotes: 8 },
                    timeline: [{ day: '2026-01-27', total: 8 }],
                },
            },
        });

        window.dispatchEvent(firstEvent);

        // Jetzt Canvas-Elemente entfernen (simuliert Umfrage ohne Stimmen)
        document.getElementById('poll-options-chart')?.remove();
        document.getElementById('poll-timeline-chart')?.remove();
        document.getElementById('poll-segment-chart')?.remove();

        const destroyCountBeforeSecondEvent = globalThis.__destroyCallCount;

        // Zweites Update - sollte bestehende Charts zerstören
        const secondEvent = new CustomEvent('poll-results-updated', {
            detail: {
                data: {
                    options: {
                        labels: ['Option B'],
                        members: [0],
                        guests: [0],
                        total: [0],
                    },
                    totals: { members: 0, guests: 0, totalVotes: 0 },
                    timeline: [],
                },
            },
        });

        expect(() => {
            window.dispatchEvent(secondEvent);
        }).not.toThrow();

        // destroy sollte aufgerufen worden sein (mindestens 3x für die 3 Charts)
        const destroyCalls = globalThis.__destroyCallCount - destroyCountBeforeSecondEvent;
        expect(destroyCalls).toBeGreaterThanOrEqual(3);
    });
});
