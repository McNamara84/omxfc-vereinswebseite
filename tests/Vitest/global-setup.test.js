import { beforeEach, afterEach, describe, expect, it, vi } from 'vitest';

const artisanCalls = [];

const fsMock = {
  existsSync: vi.fn(() => false),
  rmSync: vi.fn(),
  mkdirSync: vi.fn(),
  openSync: vi.fn(() => 1),
  closeSync: vi.fn(),
};

vi.mock('../e2e/utils/artisan.js', () => ({
  runArtisan: vi.fn(async (command) => {
    artisanCalls.push(command);
  }),
}));

vi.mock('fs', () => ({
  default: fsMock,
  ...fsMock,
}));

const ORIGINAL_ENV = { ...process.env };

beforeEach(() => {
  vi.resetModules();
  Object.values(fsMock).forEach((mockFn) => {
    if (typeof mockFn.mockReset === 'function') {
      mockFn.mockReset();
    }
  });
  fsMock.existsSync.mockImplementation(() => false);
  fsMock.rmSync.mockImplementation(() => {});
  fsMock.mkdirSync.mockImplementation(() => {});
  fsMock.openSync.mockImplementation(() => 1);
  fsMock.closeSync.mockImplementation(() => {});
  artisanCalls.length = 0;
  process.env = { ...ORIGINAL_ENV };
  delete process.env.APP_KEY;
});

afterEach(() => {
  process.env = { ...ORIGINAL_ENV };
});

describe('Playwright global setup', () => {
  it('throws when APP_KEY is not provided', async () => {
    const { default: globalSetup } = await import('../e2e/global-setup.js');

    await expect(globalSetup()).rejects.toThrow(
      'APP_KEY environment variable must be provided for Playwright tests.'
    );

    expect(fsMock.existsSync).not.toHaveBeenCalled();
    expect(artisanCalls).toHaveLength(0);
  });

  it('prepares the database when APP_KEY is provided', async () => {
    process.env.APP_KEY = 'base64:test-key';

    const { default: globalSetup } = await import('../e2e/global-setup.js');

    await expect(globalSetup()).resolves.not.toThrow();

    expect(fsMock.existsSync).toHaveBeenCalled();
    expect(fsMock.openSync).toHaveBeenCalledWith(expect.stringContaining('playwright.sqlite'), 'w');
    expect(artisanCalls).toEqual([
      'migrate:fresh',
      'db:seed --class="Database\\\\Seeders\\\\TodoCategorySeeder"',
      'db:seed --class="Database\\\\Seeders\\\\TodoPlaywrightSeeder"',
      'db:seed --class="Database\\\\Seeders\\\\DashboardSampleSeeder"',
      'db:seed --class="Database\\\\Seeders\\\\ReviewsPlaywrightSeeder"',
    ]);
  });
});
