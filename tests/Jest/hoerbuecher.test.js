import { jest } from '@jest/globals';

describe('hoerbuecher module', () => {
  beforeEach(async () => {
    jest.resetModules();
    jest.useFakeTimers();
    jest.setSystemTime(new Date('2024-01-15T00:00:00Z'));
    document.body.innerHTML = `
      <table>
        <tr data-href="/1" data-status="done" data-type="A" data-year="2024" data-roles-filled="1" data-episode-id="1" data-planned-release-date="2023-12-01" data-role-names='["Held"]'></tr>
        <tr data-href="/2" data-status="open" data-type="B" data-year="2023" data-roles-filled="0" data-episode-id="2" data-planned-release-date="2024-02-01" data-role-names='["Schurke","Nebenfigur"]'></tr>
        <tr data-href="/3" data-status="Rollenbesetzung" data-type="C" data-year="2023" data-roles-filled="0" data-episode-id="3" data-planned-release-date="2024-03-15" data-role-names='[]'></tr>
      </table>
      <select id="status-filter"><option value=""></option><option value="open">open</option><option value="Rollenbesetzung">Rollenbesetzung</option></select>
      <select id="type-filter"><option value=""></option><option value="A">A</option><option value="B">B</option><option value="C">C</option></select>
      <select id="year-filter"><option value=""></option><option value="2023">2023</option><option value="2024">2024</option></select>
      <select id="role-name-filter"><option value=""></option><option value="Held">Held</option><option value="Schurke">Schurke</option><option value="Nebenfigur">Nebenfigur</option></select>
      <input type="checkbox" id="roles-filter" />
      <input type="checkbox" id="roles-unfilled-filter" />
      <input type="checkbox" id="hide-released-filter" checked />
      <div id="card-unfilled-roles"></div>
      <div id="card-open-episodes"></div>
      <div id="card-next-event" data-episode-id="2"></div>
    `;
    await import('../../resources/js/hoerbuecher.js');
    document.dispatchEvent(new Event('DOMContentLoaded'));
  });

  afterEach(() => {
    jest.useRealTimers();
  });

  test('hides released episodes by default and shows them when filter is disabled', () => {
    const rows = document.querySelectorAll('tr[data-href]');
    expect(rows[0].style.display).toBe('none');
    expect(rows[1].style.display).toBe('');

    const hideReleased = document.getElementById('hide-released-filter');
    hideReleased.checked = false;
    hideReleased.dispatchEvent(new Event('change'));

    expect(rows[0].style.display).toBe('');
    expect(rows[1].style.display).toBe('');
  });

  test('filters rows by status and type', () => {
    const statusFilter = document.getElementById('status-filter');
    statusFilter.value = 'open';
    statusFilter.dispatchEvent(new Event('change'));

    const rows = document.querySelectorAll('tr[data-href]');
    expect(rows[0].style.display).toBe('none');
    expect(rows[1].style.display).toBe('');

    const typeFilter = document.getElementById('type-filter');
    typeFilter.value = 'B';
    typeFilter.dispatchEvent(new Event('change'));
    expect(rows[1].style.display).toBe('');

    typeFilter.value = 'A';
    typeFilter.dispatchEvent(new Event('change'));
    expect(rows[1].style.display).toBe('none');
  });

  test('filters rows by selected role name', () => {
    const roleFilter = document.getElementById('role-name-filter');
    const rows = document.querySelectorAll('tr[data-href]');

    roleFilter.value = 'Schurke';
    roleFilter.dispatchEvent(new Event('change'));

    expect(rows[0].style.display).toBe('none');
    expect(rows[1].style.display).toBe('');
    expect(rows[2].style.display).toBe('none');

    roleFilter.value = '';
    roleFilter.dispatchEvent(new Event('change'));

    expect(rows[0].style.display).toBe('none');
    expect(rows[1].style.display).toBe('');
    expect(rows[2].style.display).toBe('');
  });

  test('row click and Enter key navigate to dataset href', () => {
    const rows = document.querySelectorAll('tr[data-href]');
    rows[0].dataset.href = '#/1';
    rows[1].dataset.href = '#/2';
    rows[0].click();
    expect(window.location.hash).toBe('#/1');
    window.location.hash = '';
    rows[1].dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter' }));
    expect(window.location.hash).toBe('#/2');
  });

  test('roles and rolesUnfilled filters are mutually exclusive', () => {
    const roles = document.getElementById('roles-filter');
    const rolesUnfilled = document.getElementById('roles-unfilled-filter');

    roles.checked = true;
    roles.dispatchEvent(new Event('change'));
    expect(rolesUnfilled.checked).toBe(false);
    expect(rolesUnfilled.disabled).toBe(true);

    roles.checked = false;
    roles.dispatchEvent(new Event('change'));
    expect(rolesUnfilled.disabled).toBe(false);

    rolesUnfilled.checked = true;
    rolesUnfilled.dispatchEvent(new Event('change'));
    expect(roles.checked).toBe(false);
    expect(roles.disabled).toBe(true);

    rolesUnfilled.checked = false;
    rolesUnfilled.dispatchEvent(new Event('change'));
    expect(roles.disabled).toBe(false);
  });

  test('clicking cardNextEvent filters only matching episode', () => {
    const cardNextEvent = document.getElementById('card-next-event');
    cardNextEvent.click();
    const rows = document.querySelectorAll('tr[data-href]');
    expect(rows[0].style.display).toBe('none');
    expect(rows[1].style.display).toBe('');
  });

  test('cardNextEvent resets other filters', () => {
    const roles = document.getElementById('roles-filter');
    const rolesUnfilled = document.getElementById('roles-unfilled-filter');
    const statusFilter = document.getElementById('status-filter');
    roles.checked = true;
    roles.dispatchEvent(new Event('change'));
    statusFilter.value = 'open';
    document.getElementById('card-next-event').click();
    expect(roles.checked).toBe(false);
    expect(roles.disabled).toBe(false);
    expect(rolesUnfilled.checked).toBe(false);
    expect(rolesUnfilled.disabled).toBe(false);
    expect(statusFilter.value).toBe('');
  });

  test('card-unfilled-roles shows only rows without roles filled', () => {
    const card = document.getElementById('card-unfilled-roles');
    card.click();
    const rows = document.querySelectorAll('tr[data-href]');
    expect(rows[0].style.display).toBe('none');
    expect(rows[1].style.display).toBe('');
    expect(rows[2].style.display).toBe('');
    const roles = document.getElementById('roles-filter');
    const rolesUnfilled = document.getElementById('roles-unfilled-filter');
    expect(rolesUnfilled.checked).toBe(true);
    expect(roles.disabled).toBe(true);
  });

  test('card-open-episodes filters by status and unfilled roles', () => {
    const card = document.getElementById('card-open-episodes');
    card.click();
    const rows = document.querySelectorAll('tr[data-href]');
    expect(rows[0].style.display).toBe('none');
    expect(rows[1].style.display).toBe('none');
    expect(rows[2].style.display).toBe('');
    const statusFilter = document.getElementById('status-filter');
    expect(statusFilter.value).toBe('Rollenbesetzung');
  });

  test('card-unfilled-roles handles missing roles-unfilled filter', async () => {
    jest.resetModules();
    document.body.innerHTML = `
      <table>
        <tr data-href="/1" data-status="done" data-type="A" data-year="2024" data-roles-filled="1" data-episode-id="1"></tr>
      </table>
      <select id="status-filter"><option value=""></option></select>
      <div id="card-unfilled-roles"></div>
    `;
    await import('../../resources/js/hoerbuecher.js');
    document.dispatchEvent(new Event('DOMContentLoaded'));
    const card = document.getElementById('card-unfilled-roles');
    expect(() => card.click()).not.toThrow();
  });
});

