import {
    ArcElement,
    BarController,
    BarElement,
    CategoryScale,
    Chart,
    DoughnutController,
    Filler,
    Legend,
    LineController,
    LineElement,
    LinearScale,
    PointElement,
    Tooltip,
} from 'chart.js';

Chart.register(
    ArcElement,
    BarController,
    BarElement,
    CategoryScale,
    DoughnutController,
    Filler,
    Legend,
    LineController,
    LineElement,
    LinearScale,
    PointElement,
    Tooltip,
);

const chartStore = {
    options: null,
    timeline: null,
    segment: null,
};

// Fallbacks used when theme colors cannot be extracted (e.g. missing CSS).
const FALLBACK_MEMBERS_COLOR = 'rgb(79, 70, 229)';
const FALLBACK_GUESTS_COLOR = 'rgb(75, 85, 99)';

const destroyIfExists = (chart) => {
    if (chart && typeof chart.destroy === 'function') {
        chart.destroy();
    }
};

const getCanvas = (id) => {
    const el = document.getElementById(id);
    return el instanceof HTMLCanvasElement ? el : null;
};

const getThemeColor = (key) => {
    const el = document.querySelector(`[data-omxfc-poll-color="${key}"]`);
    if (!el) {
        return null;
    }

    const color = window.getComputedStyle(el).color;
    return color || null;
};

const toRgba = (cssColor, alpha = 0.75) => {
    if (!cssColor) {
        return null;
    }

    const rgbMatch = cssColor.match(/^rgb\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*\)$/);
    if (rgbMatch) {
        const r = Number(rgbMatch[1]);
        const g = Number(rgbMatch[2]);
        const b = Number(rgbMatch[3]);
        return `rgba(${r}, ${g}, ${b}, ${alpha})`;
    }

    const rgbaMatch = cssColor.match(/^rgba\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*,\s*([0-9.]+)\s*\)$/);
    if (rgbaMatch) {
        const r = Number(rgbaMatch[1]);
        const g = Number(rgbaMatch[2]);
        const b = Number(rgbaMatch[3]);
        return `rgba(${r}, ${g}, ${b}, ${alpha})`;
    }

    return cssColor;
};

const toLabelsAndSeries = (timeline = []) => {
    const labels = [];
    const totals = [];

    for (const point of timeline) {
        labels.push(point.day);
        totals.push(point.total);
    }

    return { labels, totals };
};

const updateCharts = (data) => {
    // Guard: Abbrechen wenn keine gültigen Daten vorhanden
    if (!data || typeof data !== 'object') {
        return;
    }

    // Guard: Abbrechen wenn keine Options oder leere Labels
    if (!data.options || !Array.isArray(data.options.labels) || data.options.labels.length === 0) {
        return;
    }

    const membersColor = getThemeColor('members') ?? FALLBACK_MEMBERS_COLOR;
    const guestsColor = getThemeColor('guests') ?? FALLBACK_GUESTS_COLOR;
    const membersBg = toRgba(membersColor, 0.75);
    const guestsBg = toRgba(guestsColor, 0.45);

    const optionsCanvas = getCanvas('poll-options-chart');
    const timelineCanvas = getCanvas('poll-timeline-chart');
    const segmentCanvas = getCanvas('poll-segment-chart');

    if (!optionsCanvas || !timelineCanvas || !segmentCanvas) {
        return;
    }

    destroyIfExists(chartStore.options);
    destroyIfExists(chartStore.timeline);
    destroyIfExists(chartStore.segment);

    const labels = data.options.labels ?? [];
    const members = data.options.members ?? [];
    const guests = data.options.guests ?? [];

    chartStore.options = new Chart(optionsCanvas.getContext('2d'), {
        type: 'bar',
        data: {
            labels,
            datasets: [
                {
                    label: 'Mitglieder',
                    data: members,
                    backgroundColor: membersBg,
                },
                {
                    label: 'Gäste',
                    data: guests,
                    backgroundColor: guestsBg,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                x: { stacked: true },
                y: { stacked: true, beginAtZero: true, ticks: { precision: 0 } },
            },
            plugins: {
                legend: { position: 'bottom' },
            },
        },
    });

    const { labels: timeLabels, totals } = toLabelsAndSeries(data.timeline);

    chartStore.timeline = new Chart(timelineCanvas.getContext('2d'), {
        type: 'line',
        data: {
            labels: timeLabels,
            datasets: [
                {
                    label: 'Stimmen',
                    data: totals,
                    borderColor: membersColor,
                    backgroundColor: toRgba(membersColor, 0.15),
                    tension: 0.25,
                    fill: true,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: { beginAtZero: true, ticks: { precision: 0 } },
            },
            plugins: {
                legend: { position: 'bottom' },
            },
        },
    });

    const membersTotal = data.totals?.members ?? 0;
    const guestsTotal = data.totals?.guests ?? 0;

    // Guard: Doughnut-Chart nicht erstellen wenn keine Stimmen vorhanden
    // Chart.js hat Probleme mit leeren Doughnut-Charts (unendliches Wachstum)
    if (membersTotal === 0 && guestsTotal === 0) {
        return;
    }

    chartStore.segment = new Chart(segmentCanvas.getContext('2d'), {
        type: 'doughnut',
        data: {
            labels: ['Mitglieder', 'Gäste'],
            datasets: [
                {
                    data: [membersTotal, guestsTotal],
                    backgroundColor: [membersBg, guestsBg],
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom' },
            },
        },
    });
};

const readInitialChartData = () => {
    const el = document.querySelector('[data-omxfc-poll-chart-data]');
    if (!el) {
        return null;
    }

    const raw = el.getAttribute('data-omxfc-poll-chart-data');
    if (!raw) {
        return null;
    }

    try {
        return JSON.parse(raw);
    } catch {
        return null;
    }
};

const init = () => {
    const initial = readInitialChartData();
    if (initial) {
        updateCharts(initial);
    }
};

let initScheduled = false;
let initRunning = false;
const scheduleInit = () => {
    if (initScheduled || initRunning) {
        return;
    }

    initScheduled = true;
    queueMicrotask(() => {
        initScheduled = false;
        initRunning = true;
        try {
            init();
        } finally {
            initRunning = false;
        }
    });
};

document.addEventListener('DOMContentLoaded', scheduleInit);
document.addEventListener('livewire:navigated', scheduleInit);

window.addEventListener('poll-results-updated', (event) => {
    const payload = event?.detail?.data ?? event?.detail;
    updateCharts(payload);
});
