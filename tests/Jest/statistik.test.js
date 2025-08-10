import { jest } from '@jest/globals';

describe('statistik module', () => {
  let drawAuthorChart;
  let drawCycleChart;
  let initRomaneTable;
  let ChartMock;
  let DataTableMock;
  let sortColumnMock;

  beforeEach(async () => {
    jest.resetModules();
    ChartMock = jest.fn();
    sortColumnMock = jest.fn();
    DataTableMock = jest.fn(() => ({ sortColumn: sortColumnMock }));

    await jest.unstable_mockModule('chart.js/auto', () => ({ default: ChartMock }));
    await jest.unstable_mockModule('simple-datatables', () => ({ DataTable: DataTableMock }));
    await jest.unstable_mockModule('simple-datatables/dist/style.css', () => ({}));

    const mod = await import('../../resources/js/statistik.js');
    drawAuthorChart = mod.drawAuthorChart;
    drawCycleChart = mod.drawCycleChart;
    initRomaneTable = mod.initRomaneTable;

    HTMLCanvasElement.prototype.getContext = jest.fn(() => ({}));
  });

  test('drawAuthorChart renders bar chart with given labels and data', () => {
    document.body.innerHTML = '<canvas id="chart"></canvas>';
    drawAuthorChart('chart', ['A', 'B'], [1, 2]);

    expect(ChartMock).toHaveBeenCalledTimes(1);
    const [ctx, config] = ChartMock.mock.calls[0];
    expect(config.type).toBe('bar');
    expect(config.data.labels).toEqual(['A', 'B']);
    expect(config.data.datasets[0].data).toEqual([1, 2]);
  });

  test('drawCycleChart renders line chart with given labels', () => {
    document.body.innerHTML = '<canvas id="cycle"></canvas>';
    drawCycleChart('cycle', ['X'], [5]);

    expect(ChartMock).toHaveBeenCalledTimes(1);
    const config = ChartMock.mock.calls[0][1];
    expect(config.type).toBe('line');
    expect(config.data.labels).toEqual(['X']);
    expect(config.data.datasets[0].data).toEqual([5]);
  });

  test('initRomaneTable initializes DataTable and sorts column', () => {
    document.body.innerHTML = '<table id="romaneTable"></table>';
    initRomaneTable();

    expect(DataTableMock).toHaveBeenCalledTimes(1);
    expect(DataTableMock.mock.calls[0][0].id).toBe('romaneTable');
    expect(sortColumnMock).toHaveBeenCalledWith(3, 'desc');
  });
});
