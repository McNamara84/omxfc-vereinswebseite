/* resources/js/statistik.js */
import Chart from 'chart.js/auto';
import { DataTable } from 'simple-datatables';
import 'simple-datatables/dist/style.css';

/**
 * Rendert das Balkendiagramm „Romane je Autor:in“,
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

    new Chart(canvas.getContext('2d'), {
        type: 'line',
        data: {
            labels,
            datasets: [{
                data,
                borderColor: 'rgba(139, 1, 22, .8)',
                backgroundColor: 'rgba(139, 1, 22, .3)',
                tension: 0.3,
            }],
        },
        options: {
            plugins: { legend: { display: false } },
            scales:  { y: { beginAtZero: true, ticks: { stepSize: 1 } } },
        },
    });
}

/**
 * Initialisiert die sortier-/paginierbare Romane-Tabelle.
 */
function initRomaneTable() {
    const tableEl = document.getElementById('romaneTable');
    if (!tableEl) return;

    const dt = new DataTable(tableEl, {
        perPage:       25,
        perPageSelect: [10, 25, 50, 100],
        searchable:    true,
        sortable:      true,
    });

    // nach Bewertung (Spalte 3) absteigend sortieren
    dt.sortColumn(3, 'desc');
}

/* ── Autostart nach DOM-Load ───────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', () => {
    const labels = window.authorChartLabels ?? [];
    const values = window.authorChartValues ?? [];
    drawAuthorChart('authorChart', labels, values);

    const cycles = ['euree', 'meeraka', 'expedition', 'kratersee', 'daaMuren', 'wandler', 'mars', 'ausala', 'afra', 'antarktis', 'schatten', 'ursprung', 'streiter', 'archivar', 'zeitsprung', 'fremdwelt'];
    cycles.forEach((cycle) => {
        const cycleLabels = window[`${cycle}ChartLabels`] ?? [];
        const cycleValues = window[`${cycle}ChartValues`] ?? [];
        drawCycleChart(`${cycle}Chart`, cycleLabels, cycleValues);
    });

    initRomaneTable();
});

/* ── optionale Named-Exports (falls du die Funktionen woanders brauchst) */
export { drawAuthorChart, drawCycleChart, initRomaneTable };
