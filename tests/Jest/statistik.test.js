import { jest } from '@jest/globals';

describe('statistik module', () => {
  let drawAuthorChart;
  let drawCycleChart;
  let initRomaneTable;
  let mockChart;
  let mockDataTable;
  let mockSortColumn;

  beforeEach(async () => {
    jest.resetModules();
    const chartModule = await import('chart.js/auto');
    const { DataTable, mockSortColumn: sortColumn } = await import('simple-datatables');
    mockChart = chartModule.default;
    mockDataTable = DataTable;
    mockSortColumn = sortColumn;

    mockChart.mockClear();
    mockDataTable.mockClear();
    mockSortColumn.mockClear();

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

  test('drawCycleChart renders line chart with given labels', () => {
    document.body.innerHTML = '<canvas id="cycle"></canvas>';
    drawCycleChart('cycle', ['X'], [5]);

    expect(mockChart).toHaveBeenCalledTimes(1);
    const config = mockChart.mock.calls[0][1];
    expect(config.type).toBe('line');
    expect(config.data.labels).toEqual(['X']);
    expect(config.data.datasets[0].data).toEqual([5]);
  });

  test('initRomaneTable initializes DataTable and sorts column', () => {
    document.body.innerHTML = '<table id="romaneTable"></table>';
    initRomaneTable();

    expect(mockDataTable).toHaveBeenCalledTimes(1);
    expect(mockDataTable.mock.calls[0][0].id).toBe('romaneTable');
    const tableInstance = mockDataTable.mock.results[0].value;
    expect(tableInstance.sortColumn).toHaveBeenCalledWith(3, 'desc');
  });
});
