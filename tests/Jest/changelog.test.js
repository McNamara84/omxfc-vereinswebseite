import { jest } from '@jest/globals';

describe('changelog module', () => {
  let domContentLoaded;
  const originalAdd = document.addEventListener;

  beforeEach(() => {
    jest.resetModules();
    document.body.innerHTML = '';
    global.fetch = jest.fn();
    document.addEventListener = jest.fn((event, cb) => {
      if (event === 'DOMContentLoaded') domContentLoaded = cb;
    });
  });

  afterEach(() => {
    document.addEventListener = originalAdd;
  });

  test('renders releases into container', async () => {
    document.body.innerHTML = '<div id="release-notes"></div>';
    global.fetch.mockResolvedValue({
      json: () => Promise.resolve([
        {
          version: '1.0.0',
          pub_date: '2024-01-02',
          notes: ['[New] Feature added', 'Misc note'],
        },
      ]),
    });
    await import('../../resources/js/changelog.js');
    await domContentLoaded();
    expect(global.fetch).toHaveBeenCalledWith('/changelog.json');
    const container = document.getElementById('release-notes');
    expect(container.querySelectorAll('section').length).toBe(1);
  });

  test('does nothing when container missing', async () => {
    document.body.innerHTML = '<div id="other"></div>';
    await import('../../resources/js/changelog.js');
    await domContentLoaded();
    expect(global.fetch).not.toHaveBeenCalled();
  });
});
