/* resources/js/statistik.js */
import Chart from 'chart.js/auto';

/**
 * Rendert ein Balkendiagramm,
 * wenn das Canvas existiert.
 */
function drawAuthorChart(canvasId, labels, data) {
    const canvas = typeof canvasId === 'string' ? document.getElementById(canvasId) : canvasId;
    if (!canvas) return;

    const existing = Chart.getChart(canvas);
    if (existing) existing.destroy();

    return new Chart(canvas.getContext('2d'), {
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
    const canvas = typeof canvasId === 'string' ? document.getElementById(canvasId) : canvasId;
    if (!canvas) return;

    const normalizedData = data.map((val) => (Number.isFinite(val) ? val : null));
    const validValues = normalizedData.filter((val) => val !== null);
    const average = validValues.length ? validValues.reduce((sum, val) => sum + val, 0) / validValues.length : 0;
    const averageData = Array(labels.length).fill(average);

    const existing = Chart.getChart(canvas);
    if (existing) existing.destroy();

    return new Chart(canvas.getContext('2d'), {
        type: 'line',
        data: {
            labels,
            datasets: [
                {
                    data: normalizedData,
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


export function mountStatistikChart(canvas) {
    const labels = JSON.parse(canvas.dataset.labels || '[]');
    const values = JSON.parse(canvas.dataset.values || '[]');
    const draw = canvas.dataset.statistikChart === 'author' ? drawAuthorChart : drawCycleChart;
    const chart = draw(canvas, labels, values);
    return () => chart.destroy();
}

export { drawAuthorChart, drawCycleChart };
