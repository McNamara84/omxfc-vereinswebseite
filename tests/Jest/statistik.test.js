import { jest } from '@jest/globals';

describe('statistik module', () => {
  let drawAuthorChart;
  let drawCycleChart;
  let mockChart;

  beforeEach(async () => {
    jest.resetModules();
    const chartModule = await import('chart.js/auto');
    mockChart = chartModule.default;
    mockChart.mockClear();

    const mod = await import('../../resources/js/statistik.js');
    ({ drawAuthorChart, drawCycleChart } = mod);

    HTMLCanvasElement.prototype.getContext = jest.fn(() => ({}));
  });

  test('drawAuthorChart renders bar chart with given labels and data', () => {
    document.body.innerHTML = '<canvas id="chart"></canvas>';
    drawAuthorChart('chart', ['A', 'B'], [1, 2]);

    expect(mockChart).toHaveBeenCalledTimes(1);
    const [ctx, config] = mockChart.mock.calls[0];
    expect(config.type).toBe('bar');
    expect(config.data.labels).toEqual(['A', 'B']);
    expect(config.data.datasets[0].data).toEqual([1, 2]);
  });

  test('drawCycleChart renders line chart with given labels and average line', () => {
    document.body.innerHTML = '<canvas id="cycle"></canvas>';
    drawCycleChart('cycle', ['X', 'Y'], [4, 6]);

    expect(mockChart).toHaveBeenCalledTimes(1);
    const config = mockChart.mock.calls[0][1];
    expect(config.type).toBe('line');
    expect(config.data.labels).toEqual(['X', 'Y']);
    expect(config.data.datasets[0].data).toEqual([4, 6]);
    expect(config.data.datasets[0].label).toBe('⌀ Bewertung');
    expect(config.data.datasets[1].data).toEqual([5, 5]);
    expect(config.data.datasets[1].label).toBe('Durchschnitt');
    expect(config.options.plugins.legend.display).toBe(true);
  });

  test('drawCycleChart ignores null values when calculating average', () => {
    document.body.innerHTML = '<canvas id="cycle"></canvas>';
    drawCycleChart('cycle', ['A', 'B', 'C'], [4, null, 6]);

    const config = mockChart.mock.calls[0][1];
    expect(config.data.datasets[0].data).toEqual([4, null, 6]);
    expect(config.data.datasets[1].data).toEqual([5, 5, 5]);
  });

  test('drawAuthorChart does nothing when canvas missing', () => {
    drawAuthorChart('missing', ['A'], [1]);
    expect(mockChart).not.toHaveBeenCalled();
  });

  test('drawCycleChart replaces data with random values when user points too low', () => {
    document.body.innerHTML = '<div data-min-points="5"><canvas id="cycle"></canvas></div>';
    window.userPoints = 0;
    jest.spyOn(Math, 'random').mockReturnValue(0.5);
    drawCycleChart('cycle', ['A', 'B'], [1, 2]);
    const config = mockChart.mock.calls[0][1];
    expect(config.data.datasets[0].data).toEqual([3, 3]);
    Math.random.mockRestore();
    delete window.userPoints;
  });

  test('drawCycleChart keeps data when user points sufficient', () => {
    document.body.innerHTML = '<div data-min-points="5"><canvas id="cycle"></canvas></div>';
    window.userPoints = 5;
    drawCycleChart('cycle', ['A', 'B'], [1, 2]);
    const config = mockChart.mock.calls[0][1];
    expect(config.data.datasets[0].data).toEqual([1, 2]);
    delete window.userPoints;
  });

  test('DOMContentLoaded draws hardcover chart', async () => {
    jest.resetModules();
    const chartModule = await import('chart.js/auto');
    mockChart = chartModule.default;
    mockChart.mockClear();

    document.body.innerHTML = '<canvas id="hardcoverChart"></canvas>';
    window.hardcoverChartLabels = ['1'];
    window.hardcoverChartValues = [4];

    await import('../../resources/js/statistik.js');
    document.dispatchEvent(new Event('DOMContentLoaded'));

    expect(mockChart).toHaveBeenCalled();
    const config = mockChart.mock.calls[0][1];
    expect(config.type).toBe('line');
  });

  test('DOMContentLoaded draws Mission Mars chart when data available', async () => {
    jest.resetModules();
    const chartModule = await import('chart.js/auto');
    mockChart = chartModule.default;
    mockChart.mockClear();

    document.body.innerHTML = '<canvas id="missionMarsChart"></canvas>';
    window.missionMarsChartLabels = ['1'];
    window.missionMarsChartValues = [4.2];

    await import('../../resources/js/statistik.js');
    document.dispatchEvent(new Event('DOMContentLoaded'));

    expect(mockChart).toHaveBeenCalled();
    const config = mockChart.mock.calls[0][1];
    expect(config.type).toBe('line');
  });

  test('DOMContentLoaded draws Mission Mars author chart', async () => {
    jest.resetModules();
    const chartModule = await import('chart.js/auto');
    mockChart = chartModule.default;
    mockChart.mockClear();

    document.body.innerHTML = '<canvas id="missionMarsAuthorChart"></canvas>';
    window.missionMarsAuthorChartLabels = ['Autor'];
    window.missionMarsAuthorChartValues = [3];

    await import('../../resources/js/statistik.js');
    document.dispatchEvent(new Event('DOMContentLoaded'));

    expect(mockChart).toHaveBeenCalled();
    const config = mockChart.mock.calls[0][1];
    expect(config.type).toBe('bar');
  });

  test('DOMContentLoaded draws hardcover author chart', async () => {
    jest.resetModules();
    const chartModule = await import('chart.js/auto');
    mockChart = chartModule.default;
    mockChart.mockClear();

    document.body.innerHTML = '<canvas id="hardcoverAuthorChart"></canvas>';
    window.hardcoverAuthorChartLabels = ['A'];
    window.hardcoverAuthorChartValues = [2];

    await import('../../resources/js/statistik.js');
    document.dispatchEvent(new Event('DOMContentLoaded'));

    expect(mockChart).toHaveBeenCalled();
    const config = mockChart.mock.calls[0][1];
    expect(config.type).toBe('bar');
  });
});
