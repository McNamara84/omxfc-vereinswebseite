import { jest } from '@jest/globals';

describe('hoerbuch role upload toggle module', () => {
  beforeEach(() => {
    jest.resetModules();
    document.body.innerHTML = `
      <form data-auto-submit="change">
        <input type="hidden" name="uploaded" value="0" />
        <input type="checkbox" />
      </form>
    `;
  });

  test('disables hidden input when checkbox is checked and submits form', async () => {
    const form = document.querySelector('form');
    const hidden = form.querySelector('input[type="hidden"]');
    const checkbox = form.querySelector('input[type="checkbox"]');
    form.requestSubmit = undefined;
    form.submit = jest.fn();

    await import('../../resources/js/hoerbuch-role-upload-toggle.js');

    expect(hidden.disabled).toBe(false);
    checkbox.checked = true;
    checkbox.dispatchEvent(new Event('change'));

    expect(hidden.disabled).toBe(true);
    expect(form.submit).toHaveBeenCalledTimes(1);
  });

  test('uses requestSubmit when available', async () => {
    const form = document.querySelector('form');
    const hidden = form.querySelector('input[type="hidden"]');
    const checkbox = form.querySelector('input[type="checkbox"]');
    form.requestSubmit = jest.fn();
    form.submit = jest.fn();

    await import('../../resources/js/hoerbuch-role-upload-toggle.js');

    checkbox.checked = true;
    checkbox.dispatchEvent(new Event('change'));

    expect(hidden.disabled).toBe(true);
    expect(form.requestSubmit).toHaveBeenCalledTimes(1);
    expect(form.submit).not.toHaveBeenCalled();
  });
});
