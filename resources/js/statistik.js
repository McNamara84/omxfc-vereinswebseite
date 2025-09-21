/* resources/js/statistik.js */
import Chart from 'chart.js/auto';

/**
 * Rendert ein Balkendiagramm,
 * wenn das Canvas existiert.
 */
function drawAuthorChart(canvasId, labels, data) {
    const canvas = document.getElementById(canvasId);
    if (!canvas) return;

    new Chart(canvas.getContext('2d'), {
        type: 'bar',
        data: {
            labels,
            datasets: [{
                data,
                borderWidth: 1,
                backgroundColor: 'rgba(139, 1, 22, .5)',
                hoverBackgroundColor: 'rgba(139, 1, 22, .7)'
            }]
        },
        options: {
            plugins: { legend: { display: false } },
            scales:  { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
        }
    });
}

function generatePalette(length) {
    const base = [
        '#8B0116',
        '#FF6B81',
        '#1E3A8A',
        '#0E7490',
        '#2F855A',
        '#CA8A04',
        '#6B21A8',
        '#BE123C',
        '#2563EB',
        '#F97316',
        '#0891B2',
        '#22C55E',
        '#EAB308',
        '#A21CAF',
        '#DC2626',
        '#0284C7',
        '#C026D3',
        '#14B8A6',
        '#FB923C',
    ];

    if (length <= base.length) {
        return base.slice(0, length);
    }

    const extended = [...base];
    while (extended.length < length) {
        extended.push(base[extended.length % base.length]);
    }

    return extended.slice(0, length);
}

function drawPieChart(canvasId, labels, data) {
    const canvas = document.getElementById(canvasId);
    if (!canvas || !labels.length || !data.length) return;

    const colors = generatePalette(labels.length);
    const total = data.reduce((sum, value) => sum + value, 0) || 1;

    new Chart(canvas.getContext('2d'), {
        type: 'doughnut',
        data: {
            labels,
            datasets: [
                {
                    data,
                    backgroundColor: colors,
                    hoverOffset: 8,
                    borderWidth: 0,
                },
            ],
        },
        options: {
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        usePointStyle: true,
                        font: { family: 'Inter, system-ui, sans-serif' },
                    },
                },
                tooltip: {
                    callbacks: {
                        label: (context) => {
                            const value = context.parsed;
                            const percentage = ((value / total) * 100).toFixed(1);
                            return `${context.label}: ${value} Mitglieder (${percentage}%)`;
                        },
                    },
                },
            },
        },
    });
}

/**
 * Rendert ein Liniendiagramm für die Zyklus-Bewertungen,
 * wenn das Canvas existiert.
 */
function drawCycleChart(canvasId, labels, data) {
    const canvas = document.getElementById(canvasId);
    if (!canvas) return;

    const parent = canvas.closest('[data-min-points]');
    const min = parent ? Number(parent.dataset.minPoints) : 0;
    const userPoints = window.userPoints ?? 0;

    let values = data;
    if (userPoints < min) {
        values = data.map(() => +(Math.random() * 6).toFixed(2));
    }
    const validValues = values.filter((val) => Number.isFinite(val));
    const average = validValues.length ? validValues.reduce((sum, val) => sum + val, 0) / validValues.length : 0;
    const averageData = Array(labels.length).fill(average);

    new Chart(canvas.getContext('2d'), {
        type: 'line',
        data: {
            labels,
            datasets: [
                {
                    data: values,
                    borderColor: 'rgba(139, 1, 22, .8)',
                    backgroundColor: 'rgba(139, 1, 22, .3)',
                    tension: 0.3,
                    label: '⌀ Bewertung',
                },
                {
                    data: averageData,
                    borderColor: 'rgba(54, 162, 235, .8)',
                    pointRadius: 0,
                    fill: false,
                    label: 'Durchschnitt',
                },
            ],
        },
        options: {
            plugins: { legend: { display: true } },
            scales:  { y: { beginAtZero: true, ticks: { stepSize: 1 } } },
        },
    });
}


/* ── Autostart nach DOM-Load ───────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', () => {
    const labels = window.authorChartLabels ?? [];
    const values = window.authorChartValues ?? [];
    drawAuthorChart('authorChart', labels, values);

    const hcAuthorLabels = window.hardcoverAuthorChartLabels ?? [];
    const hcAuthorValues = window.hardcoverAuthorChartValues ?? [];
    drawAuthorChart('hardcoverAuthorChart', hcAuthorLabels, hcAuthorValues);

    const cycles = ['euree', 'meeraka', 'expedition', 'kratersee', 'daaMuren', 'wandler', 'mars', 'ausala', 'afra', 'antarktis', 'schatten', 'ursprung', 'streiter', 'archivar', 'zeitsprung', 'fremdwelt', 'parallelwelt', 'weltenriss', 'amraka', 'weltrat'];
    cycles.forEach((cycle) => {
        const cycleLabels = window[`${cycle}ChartLabels`] ?? [];
        const cycleValues = window[`${cycle}ChartValues`] ?? [];
        drawCycleChart(`${cycle}Chart`, cycleLabels, cycleValues);
    });

    const hardcoverCanvas = document.getElementById('hardcoverChart');
    if (hardcoverCanvas) {
        const hardcoverLabels = window.hardcoverChartLabels ?? [];
        const hardcoverValues = window.hardcoverChartValues ?? [];
        drawCycleChart('hardcoverChart', hardcoverLabels, hardcoverValues);
    }

    drawPieChart(
        'browserUsageChart',
        window.browserUsageLabels ?? [],
        window.browserUsageValues ?? []
    );

    drawPieChart(
        'browserFamilyChart',
        window.browserFamilyLabels ?? [],
        window.browserFamilyValues ?? []
    );

});

/* ── optionale Named-Exports (falls du die Funktionen woanders brauchst) */
export { drawAuthorChart, drawCycleChart, drawPieChart };
