import { jest } from '@jest/globals';

describe('statistik module', () => {
  let drawAuthorChart;
  let drawCycleChart;
  let initRomaneTable;
  let mockChart;
  let mockDataTable;

  beforeEach(async () => {
    jest.resetModules();
    const chartModule = await import('chart.js/auto');
    const datatableModule = await import('simple-datatables');
    mockChart = chartModule.default;
    mockDataTable = datatableModule.DataTable;

    mockChart.mockClear();
    mockDataTable.mockClear();

    const mod = await import('../../resources/js/statistik.js');
    drawAuthorChart = mod.drawAuthorChart;
    drawCycleChart = mod.drawCycleChart;
    initRomaneTable = mod.initRomaneTable;

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
    expect(config.data.datasets[1].data).toEqual([5, 5]);
    expect(config.options.plugins.legend.display).toBe(true);
  });

  test('initRomaneTable initializes DataTable and sorts column', () => {
    document.body.innerHTML = '<table id="romaneTable"></table>';
    initRomaneTable();

    expect(mockDataTable).toHaveBeenCalledTimes(1);
    expect(mockDataTable.mock.calls[0][0].id).toBe('romaneTable');
    const instance = mockDataTable.mock.instances[0];
    expect(instance.sortColumn).toHaveBeenCalledWith(3, 'desc');
  });

  test('DOMContentLoaded draws hardcover chart', async () => {
    jest.resetModules();
    const chartModule = await import('chart.js/auto');
    const datatableModule = await import('simple-datatables');
    mockChart = chartModule.default;
    datatableModule.DataTable.mockClear();
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

  test('DOMContentLoaded draws hardcover author chart', async () => {
    jest.resetModules();
    const chartModule = await import('chart.js/auto');
    const datatableModule = await import('simple-datatables');
    mockChart = chartModule.default;
    datatableModule.DataTable.mockClear();
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
