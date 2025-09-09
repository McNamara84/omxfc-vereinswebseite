import { jest } from '@jest/globals';

describe('hoerbuch role form module', () => {
  beforeEach(async () => {
    jest.resetModules();
    global.fetch = jest.fn(() =>
      Promise.resolve({ ok: true, json: () => Promise.resolve({ speaker: 'Bob' }) })
    );
    window.roleFormData = {
      members: [{ id: 1, name: 'Alice' }],
      previousSpeakerUrl: '/prev',
      roleIndex: 0,
    };
    document.body.innerHTML = `
      <meta name="csrf-token" content="TOKEN" />
      <div id="roles_list">
        <div class="role-row">
          <input type="text" list="members" />
          <input type="hidden" />
          <input type="text" name="roles[0][name]" />
          <div class="previous-speaker"></div>
          <button type="button"></button>
        </div>
      </div>
      <datalist id="members"></datalist>
      <button id="add_role"></button>
    `;
    await import('../../resources/js/hoerbuch-role-form.js');
    fetch.mockClear();
  });

  afterEach(() => {
    delete window.roleFormData;
    delete global.fetch;
  });

  test('member input sets hidden member id', () => {
    const memberInput = document.querySelector('input[list]');
    const hidden = document.querySelector('input[type="hidden"]');
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

  test('add_role appends new role row', () => {
    const addBtn = document.getElementById('add_role');
    addBtn.click();
    const rows = document.querySelectorAll('#roles_list .role-row');
    expect(rows.length).toBe(2);
  });
});

