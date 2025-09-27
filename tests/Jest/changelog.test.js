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
    const sections = container.querySelectorAll('section');
    expect(sections.length).toBe(1);
    const details = sections[0].querySelector('details');
    expect(details).not.toBeNull();
    expect(details?.open).toBe(true);
  });

  test('does nothing when container missing', async () => {
    document.body.innerHTML = '<div id="other"></div>';
    await import('../../resources/js/changelog.js');
    await domContentLoaded();
    expect(global.fetch).not.toHaveBeenCalled();
  });

  test('shows error message on fetch failure', async () => {
    document.body.innerHTML = '<div id="release-notes"></div>';
    global.fetch.mockRejectedValue(new Error('fail'));
    await import('../../resources/js/changelog.js');
    await domContentLoaded();
    await Promise.resolve();
    expect(global.fetch).toHaveBeenCalledWith('/changelog.json');
    const container = document.getElementById('release-notes');
    const text = container.innerText || container.textContent;
    expect(text).toBe('Fehler beim Laden des Changelogs.');
  });

  test('renders note without type as plain text', async () => {
    document.body.innerHTML = '<div id="release-notes"></div>';
    global.fetch.mockResolvedValue({
      json: () => Promise.resolve([
        { version: '1.0.0', pub_date: '2024-01-02', notes: ['plain note'] },
      ]),
    });
    await import('../../resources/js/changelog.js');
    await domContentLoaded();
    const item = document.querySelector('#release-notes li');
    expect(item.textContent).toBe('plain note');
  });

  test('applies correct badge classes for note types', async () => {
    document.body.innerHTML = '<div id="release-notes"></div>';
    global.fetch.mockResolvedValue({
      json: () => Promise.resolve([
        {
          version: '1.0.1',
          pub_date: '2024-02-03',
          notes: ['[Fixed] bug', '[Changed] tweak', '[Other] misc'],
        },
      ]),
    });
    await import('../../resources/js/changelog.js');
    await domContentLoaded();
    const items = Array.from(document.querySelectorAll('#release-notes li'));
    const classes = items.map((li) => li.querySelector('span')?.className || '');
    expect(classes[0]).toContain('bg-red-600');
    expect(classes[1]).toContain('bg-blue-600');
    expect(classes[2]).toContain('bg-gray-600');
  });

  test('only the most recent release is expanded on load', async () => {
    document.body.innerHTML = '<div id="release-notes"></div>';
    global.fetch.mockResolvedValue({
      json: () => Promise.resolve([
        { version: '1.0.0', pub_date: '2024-01-02', notes: [] },
        { version: '1.1.0', pub_date: '2024-02-15', notes: [] },
        { version: '0.9.5', pub_date: '2023-12-20', notes: [] },
      ]),
    });

    await import('../../resources/js/changelog.js');
    await domContentLoaded();

    const detailElements = Array.from(document.querySelectorAll('#release-notes details'));
    expect(detailElements.length).toBe(3);
    expect(detailElements[0].open).toBe(true);
    expect(detailElements.slice(1).every((el) => el.open === false)).toBe(true);

    const summary = detailElements[0].querySelector('summary');
    expect(summary?.getAttribute('aria-expanded')).toBe('true');
    const collapsedSummary = detailElements[1].querySelector('summary');
    expect(collapsedSummary?.getAttribute('aria-expanded')).toBe('false');
  });
});
