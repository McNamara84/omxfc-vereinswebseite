import { jest } from '@jest/globals';

describe('hoerbuch role form module', () => {
  let warnSpy;

  beforeEach(async () => {
    jest.resetModules();
    global.fetch = jest.fn(() =>
      Promise.resolve({ ok: true, json: () => Promise.resolve({ speaker: 'Bob' }) })
    );
    warnSpy = jest.spyOn(console, 'warn').mockImplementation(() => {});
    document.body.innerHTML = `
      <meta name="csrf-token" content="TOKEN" />
      <div
        id="roles_list"
        data-members-target="#members"
        data-previous-speaker-url="/prev"
        data-role-index="1"
      >
        <div class="role-row">
          <input type="text" name="roles[0][member_name]" list="members" />
          <input type="hidden" name="roles[0][member_id]" />
          <input type="text" name="roles[0][name]" />
          <input type="hidden" name="roles[0][uploaded]" />
          <label>
            <input type="checkbox" name="roles[0][uploaded]" />
          </label>
          <div class="previous-speaker"></div>
          <button type="button" data-role-remove></button>
        </div>
      </div>
      <datalist id="members">
        <option data-id="1" value="Alice"></option>
      </datalist>
      <button id="add_role"></button>
    `;
    await import('../../resources/js/hoerbuch-role-form.js');
    fetch.mockClear();
  });

  afterEach(() => {
    delete global.fetch;
    warnSpy.mockRestore();
  });

  test('member input sets hidden member id', () => {
    const memberInput = document.querySelector('input[list]');
    const hidden = document.querySelector('input[type="hidden"][name$="[member_id]"]');
    memberInput.value = 'Alice';
    memberInput.dispatchEvent(new Event('input'));
    expect(hidden.value).toBe('1');
  });

  test('role name blur fetches previous speaker and updates hint', async () => {
    const roleNameInput = document.querySelector('input[name$="[name]"]');
    const hint = document.querySelector('.previous-speaker');
    roleNameInput.value = 'Hero';
    roleNameInput.dispatchEvent(new Event('blur'));
    await Promise.resolve();
    await Promise.resolve();
    expect(fetch).toHaveBeenCalled();
    const calledUrl = fetch.mock.calls[0][0];
    expect(calledUrl.toString()).toContain('name=Hero');
    await fetch.mock.results[0].value;
    await Promise.resolve();
    await Promise.resolve();
    expect(hint.textContent).toBe('Bisheriger Sprecher: Bob');
  });

  test('role name input uses debounce before fetching', async () => {
    jest.useFakeTimers();
    const roleNameInput = document.querySelector('input[name$="[name]"]');
    roleNameInput.value = 'Hero';
    roleNameInput.dispatchEvent(new Event('input'));
    expect(fetch).not.toHaveBeenCalled();
    jest.advanceTimersByTime(300);
    await Promise.resolve();
    await fetch.mock.results[0].value;
    await Promise.resolve();
    expect(fetch).toHaveBeenCalled();
    jest.useRealTimers();
  });

  test('add_role appends new role row', () => {
    const addBtn = document.getElementById('add_role');
    addBtn.click();
    const rows = document.querySelectorAll('#roles_list .role-row');
    expect(rows.length).toBe(2);
  });

  test('uploaded hidden input toggles disabled state', () => {
    const checkbox = document.querySelector('input[type="checkbox"][name$="[uploaded]"]');
    const hidden = document.querySelector('input[type="hidden"][name$="[uploaded]"]');
    expect(hidden.disabled).toBe(false);
    checkbox.checked = true;
    checkbox.dispatchEvent(new Event('change'));
    expect(hidden.disabled).toBe(true);
  });

  test('remove button deletes role row', () => {
    const row = document.querySelector('#roles_list .role-row');
    row.querySelector('[data-role-remove]').click();
    expect(document.querySelectorAll('#roles_list .role-row').length).toBe(0);
  });

  test('shows unauthorized error message', async () => {
    fetch.mockResolvedValueOnce({ ok: false, status: 401 });
    const roleNameInput = document.querySelector('input[name$="[name]"]');
    const hint = document.querySelector('.previous-speaker');
    roleNameInput.value = 'Hero';
    roleNameInput.dispatchEvent(new Event('blur'));
    await fetch.mock.results[0].value;
    await Promise.resolve();
    await Promise.resolve();
    expect(hint.textContent).toBe('Nicht berechtigt');
  });

  test('shows generic error message on failure', async () => {
    fetch.mockResolvedValueOnce({ ok: false, status: 500 });
    const roleNameInput = document.querySelector('input[name$="[name]"]');
    const hint = document.querySelector('.previous-speaker');
    roleNameInput.value = 'Hero';
    roleNameInput.dispatchEvent(new Event('blur'));
    await fetch.mock.results[0].value;
    await Promise.resolve();
    await Promise.resolve();
    expect(hint.textContent).toBe('Fehler beim Laden des bisherigen Sprechers');
  });
});

describe('hoerbuch role form without initial data', () => {
  test('skips initialization when container empty', async () => {
    jest.resetModules();
    document.body.innerHTML = '<div id="roles_list"></div><button id="add_role"></button>';
    await expect(import('../../resources/js/hoerbuch-role-form.js')).resolves.not.toThrow();
    expect(document.querySelectorAll('#roles_list .role-row').length).toBe(0);
  });
});

describe('hoerbuch role form without remove button', () => {
  test('logs warning when remove button missing', async () => {
    jest.resetModules();
    global.fetch = jest.fn(() =>
      Promise.resolve({ ok: true, json: () => Promise.resolve({ speaker: null }) })
    );
    const warnSpy = jest.spyOn(console, 'warn').mockImplementation(() => {});
    document.body.innerHTML = `
      <div
        id="roles_list"
        data-members-target="#members"
        data-previous-speaker-url="/prev"
      >
        <div class="role-row">
          <input type="text" name="roles[0][member_name]" list="members" />
          <input type="hidden" name="roles[0][member_id]" />
          <input type="text" name="roles[0][name]" />
          <input type="hidden" name="roles[0][uploaded]" />
          <label>
            <input type="checkbox" name="roles[0][uploaded]" />
          </label>
          <div class="previous-speaker"></div>
        </div>
      </div>
      <datalist id="members">
        <option data-id="1" value="Alice"></option>
      </datalist>
      <button id="add_role"></button>
    `;

    await import('../../resources/js/hoerbuch-role-form.js');

    expect(warnSpy).toHaveBeenCalledWith(
      'hoerbuch-role-form: missing [data-role-remove] button',
      expect.any(HTMLElement)
    );

    warnSpy.mockRestore();
    delete global.fetch;
  });
});

