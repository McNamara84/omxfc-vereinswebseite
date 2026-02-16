/* resources/js/statistik.js */
import Chart from 'chart.js/auto';
import './statistik-navigation';

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


/* ── Autostart nach DOM-Load / SPA-Navigation ────────────────────────────────── */
function initStatistikCharts() {
    const firstCanvas = document.getElementById('authorChart');
    if (!firstCanvas) {
        return; // Nicht auf der Statistik-Seite
    }
    const labels = window.authorChartLabels ?? [];
    const values = window.authorChartValues ?? [];
    drawAuthorChart('authorChart', labels, values);

    const hcAuthorLabels = window.hardcoverAuthorChartLabels ?? [];
    const hcAuthorValues = window.hardcoverAuthorChartValues ?? [];
    drawAuthorChart('hardcoverAuthorChart', hcAuthorLabels, hcAuthorValues);

    const missionMarsAuthorLabels = window.missionMarsAuthorChartLabels ?? [];
    const missionMarsAuthorValues = window.missionMarsAuthorChartValues ?? [];
    drawAuthorChart('missionMarsAuthorChart', missionMarsAuthorLabels, missionMarsAuthorValues);

    const volkDerTiefeAuthorLabels = window.volkDerTiefeAuthorChartLabels ?? [];
    const volkDerTiefeAuthorValues = window.volkDerTiefeAuthorChartValues ?? [];
    drawAuthorChart('volkDerTiefeAuthorChart', volkDerTiefeAuthorLabels, volkDerTiefeAuthorValues);

    const zweitausendzwoelfAuthorLabels = window.zweitausendzwoelfAuthorChartLabels ?? [];
    const zweitausendzwoelfAuthorValues = window.zweitausendzwoelfAuthorChartValues ?? [];
    drawAuthorChart('zweitausendzwoelfAuthorChart', zweitausendzwoelfAuthorLabels, zweitausendzwoelfAuthorValues);

    const abenteurerAuthorLabels = window.abenteurerAuthorChartLabels ?? [];
    const abenteurerAuthorValues = window.abenteurerAuthorChartValues ?? [];
    drawAuthorChart('abenteurerAuthorChart', abenteurerAuthorLabels, abenteurerAuthorValues);

    const cycles = ['euree', 'meeraka', 'expedition', 'kratersee', 'daaMuren', 'wandler', 'mars', 'ausala', 'afra', 'antarktis', 'schatten', 'ursprung', 'streiter', 'archivar', 'zeitsprung', 'fremdwelt', 'parallelwelt', 'weltenriss', 'amraka', 'weltrat', 'missionMars', 'volkDerTiefe', 'zweitausendzwoelf', 'abenteurer'];
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
}

document.addEventListener('DOMContentLoaded', initStatistikCharts);
document.addEventListener('livewire:navigated', initStatistikCharts);

/* ── optionale Named-Exports (falls du die Funktionen woanders brauchst) */
export { drawAuthorChart, drawCycleChart };
