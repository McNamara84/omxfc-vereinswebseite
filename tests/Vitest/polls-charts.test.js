/**
 * Tests für resources/js/polls/charts.js
 * Issue #494: Browser-Crash-Prevention durch Guards für leere Daten
 */
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';

// Mock Chart.js mit allen benötigten Exports und register-Funktion
const mockChartInstance = {
    destroy: vi.fn(),
};

vi.mock('chart.js', () => {
    const ChartMock = vi.fn(() => mockChartInstance);
    ChartMock.register = vi.fn();

    return {
        ArcElement: vi.fn(),
        BarController: vi.fn(),
        BarElement: vi.fn(),
        CategoryScale: vi.fn(),
        Chart: ChartMock,
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

import { Chart } from 'chart.js';

describe('polls/charts.js - updateCharts Guards', () => {
    beforeEach(() => {
        // Reset DOM
        document.body.innerHTML = '';
        vi.clearAllMocks();

        // Mock getContext für Canvas-Elemente
        HTMLCanvasElement.prototype.getContext = vi.fn(() => ({}));
    });

    afterEach(() => {
        vi.resetModules();
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

    it('sollte bei null-Daten nicht crashen', async () => {
        setupChartDOM();

        // Importiere das Modul frisch
        await import('@/polls/charts.js');

        // Simuliere Event mit null-Daten
        const event = new CustomEvent('poll-results-updated', {
            detail: { data: null },
        });

        // Sollte nicht werfen
        expect(() => {
            window.dispatchEvent(event);
        }).not.toThrow();

        // Chart sollte nicht erstellt worden sein
        expect(Chart).not.toHaveBeenCalled();
    });

    it('sollte bei leerem Objekt nicht crashen', async () => {
        setupChartDOM();

        await import('@/polls/charts.js');

        const event = new CustomEvent('poll-results-updated', {
            detail: { data: {} },
        });

        expect(() => {
            window.dispatchEvent(event);
        }).not.toThrow();

        expect(Chart).not.toHaveBeenCalled();
    });

    it('sollte bei fehlendem options-Objekt nicht crashen', async () => {
        setupChartDOM();

        await import('@/polls/charts.js');

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

        expect(Chart).not.toHaveBeenCalled();
    });

    it('sollte bei leerem labels-Array nicht crashen', async () => {
        setupChartDOM();

        await import('@/polls/charts.js');

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

        expect(Chart).not.toHaveBeenCalled();
    });

    it('sollte bei labels als nicht-Array nicht crashen', async () => {
        setupChartDOM();

        await import('@/polls/charts.js');

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

        expect(Chart).not.toHaveBeenCalled();
    });
});
