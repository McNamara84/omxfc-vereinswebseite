import Chart from 'chart.js/auto';

const numberFormatter = new Intl.NumberFormat('de-DE');

const paletteBase = Object.freeze([
    'oklch(0.65 0.24 16)',
    'oklch(0.7 0.18 250)',
    'oklch(0.75 0.15 160)',
    'oklch(0.6 0.2 30)',
    'oklch(0.65 0.15 200)',
    'oklch(0.7 0.12 80)',
    'oklch(0.55 0.2 300)',
    'oklch(0.6 0.18 350)',
    'oklch(0.7 0.14 140)',
    'oklch(0.65 0.16 50)',
]);

const barDatasetStyles = Object.freeze({
    backgroundColor: 'oklch(0.65 0.2 250 / 0.7)',
    borderColor: 'oklch(0.65 0.2 250)',
    borderWidth: 1,
});

const lineDatasetStyles = Object.freeze({
    borderColor: 'oklch(0.65 0.2 250)',
    backgroundColor: 'oklch(0.65 0.2 250 / 0.2)',
    fill: false,
    tension: 0.3,
    pointRadius: 3,
});

const dayNames = ['Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag', 'Sonntag'];
const hours = Array.from({ length: 24 }, (_, i) => String(i).padStart(2, '0') + ':00');

function getAxisColors() {
    const isDarkMode = document.documentElement.dataset.theme === 'coffee';
    return {
        axisColor: isDarkMode ? 'oklch(0.85 0 0)' : 'oklch(0.4 0 0)',
        gridColor: isDarkMode ? 'oklch(0.3 0 0 / 0.3)' : 'oklch(0.7 0 0 / 0.3)',
    };
}

function formatTooltipLabel(context) {
    const value = context.parsed.y ?? context.parsed;
    return `${context.dataset.label}: ${numberFormatter.format(value)}`;
}

function getCommonOptions({ showLegend = false, additionalOptions = {} } = {}) {
    const { axisColor, gridColor } = getAxisColors();

    const baseOptions = {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            x: {
                ticks: { color: axisColor },
                grid: { color: gridColor },
            },
            y: {
                beginAtZero: true,
                ticks: {
                    color: axisColor,
                    callback: value => numberFormatter.format(value),
                },
                grid: { color: gridColor },
            },
        },
        plugins: {
            legend: { display: false },
            tooltip: { callbacks: { label: formatTooltipLabel } },
        },
    };

    if (showLegend) {
        baseOptions.plugins.legend = { display: true, labels: { color: axisColor } };
    }

    return {
        ...baseOptions,
        ...additionalOptions,
        scales: {
            ...baseOptions.scales,
            ...(additionalOptions.scales ?? {}),
        },
        plugins: {
            ...baseOptions.plugins,
            ...(additionalOptions.plugins ?? {}),
        },
    };
}

function getPalette(length) {
    if (length <= paletteBase.length) {
        return paletteBase.slice(0, length);
    }

    const extended = [...paletteBase];
    while (extended.length < length) {
        extended.push(paletteBase[extended.length % paletteBase.length]);
    }

    return extended.slice(0, length);
}

function renderDoughnutChart(canvasId, entries) {
    const { axisColor } = getAxisColors();
    const canvas = document.getElementById(canvasId);
    if (!canvas || !Array.isArray(entries) || entries.length === 0) {
        return;
    }

    const labels = entries.map(entry => entry.label);
    const values = entries.map(entry => entry.value);
    const colors = getPalette(labels.length);
    const total = values.reduce((sum, value) => sum + value, 0) || 1;

    new Chart(canvas.getContext('2d'), {
        type: 'doughnut',
        data: {
            labels,
            datasets: [{
                data: values,
                backgroundColor: colors,
                borderWidth: 0,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        color: axisColor,
                        usePointStyle: true,
                        padding: 16,
                    },
                },
                tooltip: {
                    callbacks: {
                        label: (context) => {
                            const value = context.parsed;
                            const percentage = total === 0 ? 0 : ((value / total) * 100).toFixed(1);
                            return `${context.label}: ${numberFormatter.format(value)} Mitglieder (${percentage}%)`;
                        },
                    },
                },
            },
        },
    });
}

/** Alle Chart-Instanzen, um sie bei erneuter Initialisierung zu zerstören */
const chartInstances = [];

function destroyAllCharts() {
    while (chartInstances.length > 0) {
        const chart = chartInstances.pop();
        try { chart.destroy(); } catch { /* already destroyed */ }
    }
}

function initAdminCharts() {
    const configEl = document.getElementById('admin-charts-config');
    if (!configEl) return;

    // Vorherige Chart-Instanzen aufräumen
    destroyAllCharts();

    // Daten aus data-Attributen lesen
    const visitData = JSON.parse(configEl.dataset.visitData || '[]');
    const userVisitData = JSON.parse(configEl.dataset.userVisitData || '[]');
    const activityData = JSON.parse(configEl.dataset.activityData || '{}');
    const activityTimeline = JSON.parse(configEl.dataset.activityTimeline || '[]');
    const browserUsageByBrowser = JSON.parse(configEl.dataset.browserUsageByBrowser || '[]');
    const browserUsageByFamily = JSON.parse(configEl.dataset.browserUsageByFamily || '[]');
    const deviceUsage = JSON.parse(configEl.dataset.deviceUsage || '[]');

    // Seitenaufrufe nach Route
    const visitsChartElement = document.getElementById('visitsChart');
    if (visitsChartElement) {
        const labels = visitData.map(v => v.path);
        const counts = visitData.map(v => v.total);

        chartInstances.push(new Chart(visitsChartElement.getContext('2d'), {
            type: 'bar',
            data: {
                labels,
                datasets: [{
                    label: 'Seitenaufrufe',
                    data: counts,
                    ...barDatasetStyles,
                }],
            },
            options: getCommonOptions(),
        }));
    }

    // Browser-Charts
    renderDoughnutChart('browserUsageChart', browserUsageByBrowser);
    renderDoughnutChart('browserFamilyChart', browserUsageByFamily);
    renderDoughnutChart('deviceUsageChart', deviceUsage);

    // Nutzer-Auswahl
    const userSelect = document.getElementById('userSelect');
    const userVisitsEmptyMessage = document.getElementById('userVisitsEmptyMessage');
    const userVisitsChartElement = document.getElementById('userVisitsChart');
    const userPaths = [...new Set(userVisitData.map(v => v.path))];
    const userOptions = [...new Map(userVisitData.map(v => [String(v.user_id), v.user.name]))];

    if (userVisitsChartElement && userSelect) {
        // Alte Optionen aufräumen
        userSelect.innerHTML = '';

        const ctx2 = userVisitsChartElement.getContext('2d');
        if (ctx2) {
            const userChart = new Chart(ctx2, {
                type: 'bar',
                data: {
                    labels: userPaths,
                    datasets: [],
                },
                options: getCommonOptions(),
            });
            chartInstances.push(userChart);

            if (userOptions.length === 0 || userPaths.length === 0) {
                const option = document.createElement('option');
                option.textContent = 'Keine Daten verfügbar';
                option.disabled = true;
                option.selected = true;
                userSelect.appendChild(option);
                userSelect.disabled = true;
                userSelect.setAttribute('aria-disabled', 'true');
                userVisitsEmptyMessage?.classList.remove('hidden');
            } else {
                userOptions.forEach(([id, name]) => {
                    const option = document.createElement('option');
                    option.value = id;
                    option.textContent = name;
                    userSelect.appendChild(option);
                });

                const updateUserChart = (selectedUserId) => {
                    const datasetLabel = userOptions.find(([id]) => id === String(selectedUserId))?.[1] ?? 'Seitenaufrufe';
                    const data = userPaths.map(path => {
                        const record = userVisitData.find(v => v.path === path && String(v.user_id) === String(selectedUserId));
                        return record ? record.total : 0;
                    });

                    userChart.data.datasets = [{
                        label: datasetLabel,
                        data,
                        ...barDatasetStyles,
                    }];
                    userChart.update();
                };

                updateUserChart(userOptions[0][0]);
                userSelect.addEventListener('change', (event) => updateUserChart(event.target.value));
                userVisitsEmptyMessage?.classList.add('hidden');
            }
        }
    }

    // Wochentag-Auswahl
    const weekdaySelect = document.getElementById('weekdaySelect');
    if (weekdaySelect) {
        // Alte Optionen aufräumen
        weekdaySelect.innerHTML = '';

        const allOption = document.createElement('option');
        allOption.value = 'all';
        allOption.textContent = 'Alle';
        allOption.selected = true;
        weekdaySelect.appendChild(allOption);

        dayNames.forEach((day, index) => {
            const option = document.createElement('option');
            option.value = String(index);
            option.textContent = day;
            weekdaySelect.appendChild(option);
        });
    }

    // Aktive Mitglieder nach Uhrzeit
    const activeUsersChartElement = document.getElementById('activeUsersChart');
    const ctx3 = activeUsersChartElement?.getContext?.('2d');
    if (ctx3) {
        const activeChart = new Chart(ctx3, {
            type: 'line',
            data: {
                labels: hours,
                datasets: [{
                    label: 'Alle',
                    data: activityData['all'] ?? [],
                    ...lineDatasetStyles,
                }],
            },
            options: getCommonOptions({ showLegend: true }),
        });
        chartInstances.push(activeChart);

        const updateActiveChart = (selected) => {
            const dataset = activityData[selected] ?? [];
            activeChart.data.datasets[0].data = dataset;
            if (selected === 'all') {
                activeChart.data.datasets[0].label = 'Alle';
            } else {
                const dayIndex = Number.parseInt(selected, 10);
                activeChart.data.datasets[0].label = Number.isNaN(dayIndex) ? 'Alle' : dayNames[dayIndex];
            }
            activeChart.update();
        };

        updateActiveChart('all');
        weekdaySelect?.addEventListener('change', (event) => updateActiveChart(event.target.value));
    }

    // Aktive Mitglieder nach Wochentag
    const { axisColor, gridColor } = getAxisColors();
    const activeUsersWeekdayElement = document.getElementById('activeUsersWeekdayChart');
    const ctx4 = activeUsersWeekdayElement?.getContext?.('2d');
    if (ctx4) {
        const weekdayLabels = [];
        const weekdayValues = [];

        activityTimeline.forEach((entry) => {
            const dayLabel = dayNames[entry.weekday] ?? '';
            const hourLabel = hours[entry.hour] ?? '';
            weekdayLabels.push(`${dayLabel}\n${hourLabel}`);
            weekdayValues.push(entry.total ?? 0);
        });

        chartInstances.push(new Chart(ctx4, {
            type: 'line',
            data: {
                labels: weekdayLabels,
                datasets: [{
                    label: 'Aktive Mitglieder',
                    data: weekdayValues,
                    ...lineDatasetStyles,
                }],
            },
            options: getCommonOptions({
                showLegend: true,
                additionalOptions: {
                    scales: {
                        x: {
                            ticks: {
                                color: axisColor,
                                autoSkip: true,
                                maxRotation: 0,
                                callback(value) {
                                    const label = this.getLabelForValue(value);
                                    return typeof label === 'string' ? label.split('\n') : label;
                                },
                            },
                            grid: {
                                color: gridColor,
                                drawOnChartArea: false,
                            },
                        },
                    },
                },
            }),
        }));
    }
}

document.addEventListener('DOMContentLoaded', initAdminCharts);
document.addEventListener('livewire:navigated', initAdminCharts);

// Cleanup bei Navigation weg von der Seite
document.addEventListener('livewire:navigating', destroyAllCharts);
