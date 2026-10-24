import { vi } from 'vitest';

function mockChartModule() {
  const MockChart = vi.fn();
  MockChart.getChart = vi.fn(() => null);
  vi.doMock('chart.js/auto', () => ({ default: MockChart }));

  return MockChart;
}

describe('statistik module', () => {
  test('mount cleanup destroys its own chart and ignores stale window data', async () => {
    const destroy = vi.fn();
    mockChart.mockImplementation(function () { this.destroy = destroy; });
    document.body.innerHTML = '<canvas data-statistik-chart="cycle" data-labels=\'["Neu"]\' data-values=\'[null]\'></canvas>';
    window.authorChartLabels = ['Alt'];
    const { mountStatistikChart } = await import('../../resources/js/statistik');
    const cleanup = mountStatistikChart(document.querySelector('canvas'));
    expect(mockChart.mock.calls[0][1].data.labels).toEqual(['Neu']);
    expect(mockChart.mock.calls[0][1].data.datasets[1].data).toEqual([0]);
    cleanup();
    expect(destroy).toHaveBeenCalledTimes(1);
    drawCycleChart('missing', [], []);
    mockChart.getChart.mockReturnValue({ destroy });
    drawCycleChart(document.querySelector('canvas'), [], []);
    drawAuthorChart(document.querySelector('canvas'), [], []);
    expect(destroy).toHaveBeenCalledTimes(3);
  });
  let drawAuthorChart;
  let drawCycleChart;
  let mockChart;
  let originalGetContext;

  beforeEach(async () => {
    originalGetContext = HTMLCanvasElement.prototype.getContext;
    vi.resetModules();
    mockChart = mockChartModule();

    const mod = await import('../../resources/js/statistik.js');
    ({ drawAuthorChart, drawCycleChart } = mod);

    HTMLCanvasElement.prototype.getContext = vi.fn(() => ({}));
  });

  afterEach(() => {
    HTMLCanvasElement.prototype.getContext = originalGetContext;
    vi.restoreAllMocks();
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

  test('drawCycleChart normalizes NaN, Infinity and string values to null', () => {
    document.body.innerHTML = '<canvas id="cycle"></canvas>';
    drawCycleChart('cycle', ['A', 'B', 'C', 'D', 'E'], [3, NaN, Infinity, -Infinity, 7]);

    const config = mockChart.mock.calls[0][1];
    expect(config.data.datasets[0].data).toEqual([3, null, null, null, 7]);
    // Durchschnitt nur aus den gültigen Werten 3 und 7
    expect(config.data.datasets[1].data).toEqual([5, 5, 5, 5, 5]);
  });

  test('drawAuthorChart does nothing when canvas missing', () => {
    drawAuthorChart('missing', ['A'], [1]);
    expect(mockChart).not.toHaveBeenCalled();
  });

  test('mount draws hardcover chart', async () => {
    vi.resetModules();
    mockChart = mockChartModule();

    document.body.innerHTML = '<canvas id="hardcoverChart"></canvas>';
    const canvas = document.getElementById('hardcoverChart');
    canvas.dataset.statistikChart = 'cycle';
    canvas.dataset.labels = JSON.stringify(['1']);
    canvas.dataset.values = JSON.stringify([4]);

    const { mountStatistikChart } = await import('../../resources/js/statistik.js');
    mountStatistikChart(canvas);

    expect(mockChart).toHaveBeenCalled();
    const config = mockChart.mock.calls[0][1];
    expect(config.type).toBe('line');
  });

  test('mount draws Mission Mars chart when data available', async () => {
    vi.resetModules();
    mockChart = mockChartModule();

    document.body.innerHTML = '<canvas id="missionMarsChart"></canvas>';
    const canvas = document.getElementById('missionMarsChart');
    canvas.dataset.statistikChart = 'cycle';
    canvas.dataset.labels = JSON.stringify(['1']);
    canvas.dataset.values = JSON.stringify([4.2]);

    const { mountStatistikChart } = await import('../../resources/js/statistik.js');
    mountStatistikChart(canvas);

    expect(mockChart).toHaveBeenCalled();
    const config = mockChart.mock.calls[0][1];
    expect(config.type).toBe('line');
  });

  test('mount draws Mission Mars author chart', async () => {
    vi.resetModules();
    mockChart = mockChartModule();

    document.body.innerHTML = '<canvas id="missionMarsAuthorChart"></canvas>';
    const canvas = document.getElementById('missionMarsAuthorChart');
    canvas.dataset.statistikChart = 'author';
    canvas.dataset.labels = JSON.stringify(['Autor']);
    canvas.dataset.values = JSON.stringify([3]);

    const { mountStatistikChart } = await import('../../resources/js/statistik.js');
    mountStatistikChart(canvas);

    expect(mockChart).toHaveBeenCalled();
    const config = mockChart.mock.calls[0][1];
    expect(config.type).toBe('bar');
  });

  test('mount draws Das Volk der Tiefe chart when data available', async () => {
    vi.resetModules();
    mockChart = mockChartModule();

    document.body.innerHTML = '<canvas id="volkDerTiefeChart"></canvas>';
    const canvas = document.getElementById('volkDerTiefeChart');
    canvas.dataset.statistikChart = 'cycle';
    canvas.dataset.labels = JSON.stringify(['1']);
    canvas.dataset.values = JSON.stringify([4.4]);

    const { mountStatistikChart } = await import('../../resources/js/statistik.js');
    mountStatistikChart(canvas);

    expect(mockChart).toHaveBeenCalled();
    const config = mockChart.mock.calls[0][1];
    expect(config.type).toBe('line');
  });

  test('mount draws Das Volk der Tiefe author chart', async () => {
    vi.resetModules();
    mockChart = mockChartModule();

    document.body.innerHTML = '<canvas id="volkDerTiefeAuthorChart"></canvas>';
    const canvas = document.getElementById('volkDerTiefeAuthorChart');
    canvas.dataset.statistikChart = 'author';
    canvas.dataset.labels = JSON.stringify(['Autor']);
    canvas.dataset.values = JSON.stringify([2]);

    const { mountStatistikChart } = await import('../../resources/js/statistik.js');
    mountStatistikChart(canvas);

    expect(mockChart).toHaveBeenCalled();
    const config = mockChart.mock.calls[0][1];
    expect(config.type).toBe('bar');
  });

  test('mount draws hardcover author chart', async () => {
    vi.resetModules();
    mockChart = mockChartModule();

    document.body.innerHTML = '<canvas id="hardcoverAuthorChart"></canvas>';
    const canvas = document.getElementById('hardcoverAuthorChart');
    canvas.dataset.statistikChart = 'author';
    canvas.dataset.labels = JSON.stringify(['A']);
    canvas.dataset.values = JSON.stringify([2]);

    const { mountStatistikChart } = await import('../../resources/js/statistik.js');
    mountStatistikChart(canvas);

    expect(mockChart).toHaveBeenCalled();
    const config = mockChart.mock.calls[0][1];
    expect(config.type).toBe('bar');
  });
});
