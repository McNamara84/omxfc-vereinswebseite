import { jest } from '@jest/globals';

// Using jest.unstable_mockModule because Jest's stable mock API currently
// lacks full support for mocking ES modules loaded via dynamic import.
// This enables us to intercept the axios dependency in the bootstrap module.

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
