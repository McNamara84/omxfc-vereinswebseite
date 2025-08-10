import { jest } from '@jest/globals';

describe('bootstrap module', () => {
  beforeEach(() => {
    jest.resetModules();
  });

  test('sets global axios instance with default header', async () => {
    const mockAxios = { defaults: { headers: { common: {} } } };
    await jest.unstable_mockModule('axios', () => ({ default: mockAxios }));
    await import('../../resources/js/bootstrap.js');
    expect(window.axios).toBe(mockAxios);
    expect(mockAxios.defaults.headers.common['X-Requested-With']).toBe('XMLHttpRequest');
  });
});
