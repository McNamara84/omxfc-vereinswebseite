import { jest } from '@jest/globals';

describe('chronik module', () => {
  beforeEach(() => {
    jest.resetModules();
    document.body.innerHTML = '';
  });

  test('opens modal and populates sources on image click', async () => {
    document.body.innerHTML = `
      <div id="chronik-modal" class="hidden">
        <picture>
          <source id="chronik-modal-avif" />
          <source id="chronik-modal-webp" />
          <img id="chronik-modal-img" />
        </picture>
        <button id="chronik-modal-close"></button>
      </div>
      <a class="chronik-image" data-avif="a.avif" data-webp="b.webp"><img alt="alt text" /></a>
    `;
    await import('../../resources/js/chronik.js');
    document.dispatchEvent(new Event('DOMContentLoaded'));

    const trigger = document.querySelector('.chronik-image');
    trigger.click();

    const modal = document.getElementById('chronik-modal');
    expect(modal.classList.contains('hidden')).toBe(false);
    expect(document.getElementById('chronik-modal-img').src).toContain('b.webp');
    expect(document.getElementById('chronik-modal-avif').srcset).toBe('a.avif');
    expect(document.getElementById('chronik-modal-webp').srcset).toBe('b.webp');
  });

  test('hides modal on overlay click and escape key', async () => {
    document.body.innerHTML = `
      <div id="chronik-modal" class="hidden">
        <picture>
          <source id="chronik-modal-avif" />
          <source id="chronik-modal-webp" />
          <img id="chronik-modal-img" />
        </picture>
        <button id="chronik-modal-close"></button>
      </div>
      <a class="chronik-image" data-avif="a.avif" data-webp="b.webp"><img alt="alt" /></a>
    `;
    await import('../../resources/js/chronik.js');
    document.dispatchEvent(new Event('DOMContentLoaded'));

    const trigger = document.querySelector('.chronik-image');
    const modal = document.getElementById('chronik-modal');
    trigger.click();
    expect(modal.classList.contains('hidden')).toBe(false);

    modal.dispatchEvent(new MouseEvent('click', { bubbles: true }));
    expect(modal.classList.contains('hidden')).toBe(true);

    trigger.click();
    expect(modal.classList.contains('hidden')).toBe(false);
    document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));
    expect(modal.classList.contains('hidden')).toBe(true);
  });
});

