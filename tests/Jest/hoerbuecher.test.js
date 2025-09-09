import { jest } from '@jest/globals';

describe('hoerbuecher module', () => {
  beforeEach(async () => {
    jest.resetModules();
    document.body.innerHTML = `
      <table>
        <tr data-href="/1" data-status="done" data-type="A" data-year="2024" data-roles-filled="1" data-episode-id="1"></tr>
        <tr data-href="/2" data-status="open" data-type="B" data-year="2023" data-roles-filled="0" data-episode-id="2"></tr>
      </table>
      <select id="status-filter"><option value=""></option><option value="open">open</option></select>
      <select id="type-filter"><option value=""></option><option value="A">A</option><option value="B">B</option></select>
      <select id="year-filter"><option value=""></option><option value="2023">2023</option></select>
      <input type="checkbox" id="roles-filter" />
      <input type="checkbox" id="roles-unfilled-filter" />
      <div id="card-next-event" data-episode-id="2"></div>
    `;
    await import('../../resources/js/hoerbuecher.js');
    document.dispatchEvent(new Event('DOMContentLoaded'));
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
  });

  test('clicking cardNextEvent filters only matching episode', () => {
    const cardNextEvent = document.getElementById('card-next-event');
    cardNextEvent.click();
    const rows = document.querySelectorAll('tr[data-href]');
    expect(rows[0].style.display).toBe('none');
    expect(rows[1].style.display).toBe('');
  });
});

