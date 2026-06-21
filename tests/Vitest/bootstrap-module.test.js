import { vi } from 'vitest';

describe('bootstrap module', () => {
  beforeEach(() => {
    vi.resetModules();
    delete window.omxfcHttp;
  });

  test('sets global http instance with default header', async () => {
    const { default: http } = await import('../../resources/js/http/client.js');

    await import('../../resources/js/bootstrap.js');

    expect(window.omxfcHttp).toBe(http);
    expect(http.defaults.headers.common['X-Requested-With']).toBe('XMLHttpRequest');
  });
});
