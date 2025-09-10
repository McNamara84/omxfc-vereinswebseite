import { jest } from '@jest/globals';

const BASE_HTML = `
  <input id="player_name" />
  <input id="character_name" />
  <select id="race"><option value=""></option><option value="Barbar">Barbar</option></select>
  <select id="culture"><option value=""></option><option value="Landbewohner">Landbewohner</option></select>
  <select id="advantages" multiple>
    <option value="Zäh">Zäh</option>
    <option value="Kind zweier Welten">Kind zweier Welten</option>
  </select>
  <select id="disadvantages" multiple>
    <option value="Arm">Arm</option>
  </select>
  <input id="available_advantage_points" />
  <span id="attribute-points"></span>
  <span id="skill-points"></span>
  <button id="submit-button"></button>
  <button id="pdf-button"></button>
  <button id="add-skill"></button>
  <div id="barbar-combat-toggle" class="hidden">
    <select id="barbar-combat-select">
      <option value="Nahkampf">Nahkampf</option>
      <option value="Fernkampf">Fernkampf</option>
    </select>
  </div>
  <div id="skills-container"></div>
  <datalist id="skills-list">
    <option value="Überleben"></option>
    <option value="Intuition"></option>
    <option value="Nahkampf"></option>
    <option value="Fernkampf"></option>
    <option value="Beruf: Viehzüchter"></option>
    <option value="Beruf: Landwirt"></option>
    <option value="Kunde: Wetter"></option>
  </datalist>
  <button id="continue-button" class="hidden"></button>
  <fieldset id="advanced-fields"></fieldset>
  <input id="st" />
  <input id="ge" />
  <input id="ro" />
  <input id="wi" />
  <input id="wa" />
  <input id="in" />
  <input id="au" />
`;

async function loadEditor(values = {}) {
  jest.resetModules();
  document.body.innerHTML = BASE_HTML;
  for (const [id, value] of Object.entries(values)) {
    const el = document.getElementById(id);
    if (el) el.value = value;
  }
  await import('../../resources/js/char-editor.js');
  document.dispatchEvent(new Event('DOMContentLoaded'));
}

describe('char-editor module', () => {
  test('shows continue button when basics filled', async () => {
    await loadEditor();
    const player = document.getElementById('player_name');
    const character = document.getElementById('character_name');
    const race = document.getElementById('race');
    const culture = document.getElementById('culture');
    const btn = document.getElementById('continue-button');
    player.value = 'Alice';
    character.value = 'Bob';
    race.value = 'Barbar';
    culture.value = 'Landbewohner';
    player.dispatchEvent(new Event('input'));
    expect(btn.classList.contains('hidden')).toBe(false);
  });

  test('selecting Barbar race reveals combat toggle and adds skills', async () => {
    await loadEditor({ player_name: 'Alice', character_name: 'Bob' });
    const race = document.getElementById('race');
    race.value = 'Barbar';
    race.dispatchEvent(new Event('change'));
    const toggle = document.getElementById('barbar-combat-toggle');
    expect(toggle.classList.contains('hidden')).toBe(false);
    const skillNames = Array.from(document.querySelectorAll('.skill-row .skill-name')).map(i => i.value);
    expect(skillNames).toEqual(expect.arrayContaining(['Überleben', 'Intuition', 'Nahkampf']));
    const zaeh = document.querySelector('#advantages option[value="Zäh"]');
    expect(zaeh.selected).toBe(true);
    expect(zaeh.disabled).toBe(true);
    expect(document.getElementById('attribute-points').textContent).toBe('Verfügbare Attributspunkte: 3');
  });

  test('changing barbar combat skill replaces previous skill', async () => {
    await loadEditor({ player_name: 'Alice', character_name: 'Bob' });
    const race = document.getElementById('race');
    race.value = 'Barbar';
    race.dispatchEvent(new Event('change'));
    const combatSelect = document.getElementById('barbar-combat-select');
    combatSelect.value = 'Fernkampf';
    combatSelect.dispatchEvent(new Event('change'));
    const skillNames = Array.from(document.querySelectorAll('.skill-row .skill-name')).map(i => i.value);
    expect(skillNames).toEqual(expect.arrayContaining(['Fernkampf']));
    expect(skillNames).not.toEqual(expect.arrayContaining(['Nahkampf']));
  });

  test('selecting Landbewohner culture adds culture skills', async () => {
    await loadEditor({ player_name: 'Alice', character_name: 'Bob' });
    const race = document.getElementById('race');
    race.value = 'Barbar';
    race.dispatchEvent(new Event('change'));
    const culture = document.getElementById('culture');
    culture.value = 'Landbewohner';
    culture.dispatchEvent(new Event('change'));
    const skillNames = Array.from(document.querySelectorAll('.skill-row .skill-name')).map(i => i.value);
    expect(skillNames).toEqual(
      expect.arrayContaining(['Beruf: Viehzüchter', 'Beruf: Landwirt', 'Kunde: Wetter'])
    );
  });

  test('pdf button disabled by default', async () => {
    await loadEditor();
    const pdfBtn = document.getElementById('pdf-button');
    expect(pdfBtn.disabled).toBe(true);
  });

  test('attribute inputs respect caps and available points', async () => {
    await loadEditor({ player_name: 'A', character_name: 'B' });
    const race = document.getElementById('race');
    race.value = 'Barbar';
    race.dispatchEvent(new Event('change'));
    const st = document.getElementById('st');
    st.value = '3';
    st.dispatchEvent(new Event('input'));
    expect(st.value).toBe('2');
    const ge = document.getElementById('ge');
    ge.value = '2';
    ge.dispatchEvent(new Event('input'));
    expect(ge.value).toBe('1');
    expect(document.getElementById('attribute-points').textContent).toBe('Verfügbare Attributspunkte: 0');
  });

  test('advantage selection is limited by free points', async () => {
    jest.resetModules();
    document.body.innerHTML = BASE_HTML;
    const adv = document.getElementById('advantages');
    ['Extra1', 'Extra2'].forEach(v => {
      const opt = document.createElement('option');
      opt.value = v;
      opt.textContent = v;
      adv.appendChild(opt);
    });
    await import('../../resources/js/char-editor.js');
    document.dispatchEvent(new Event('DOMContentLoaded'));
    adv.options[1].selected = true; // Kind zweier Welten
    adv.dispatchEvent(new Event('change'));
    adv.options[2].selected = true; // Extra1
    adv.dispatchEvent(new Event('change'));
    adv.options[3].selected = true; // Extra2 - should be rejected
    adv.dispatchEvent(new Event('change'));
    expect(adv.options[3].selected).toBe(false);
    expect(adv.options[3].disabled).toBe(true);
    expect(document.getElementById('available_advantage_points').value).toBe('0');
  });

  test('bildung skill disabled when intuition > 0 without special advantage', async () => {
    await loadEditor({ player_name: 'A', character_name: 'B' });
    const addBtn = document.getElementById('add-skill');
    addBtn.click();
    let rows = document.querySelectorAll('.skill-row');
    const intRow = rows[0];
    const intName = intRow.querySelector('.skill-name');
    const intVal = intRow.querySelector('input[type="number"]');
    intName.value = 'Intuition';
    intName.dispatchEvent(new Event('input', { bubbles: true }));
    intVal.value = '1';
    intVal.dispatchEvent(new Event('input', { bubbles: true }));
    addBtn.click();
    rows = document.querySelectorAll('.skill-row');
    const bildRow = rows[1];
    const bildName = bildRow.querySelector('.skill-name');
    const bildVal = bildRow.querySelector('input[type="number"]');
    bildName.value = 'Bildung';
    bildName.dispatchEvent(new Event('input', { bubbles: true }));
    bildVal.value = '1';
    bildVal.dispatchEvent(new Event('input', { bubbles: true }));
    document.getElementById('skills-container').dispatchEvent(new Event('input'));
    rows = document.querySelectorAll('.skill-row');
    const finalBildVal = rows[1].querySelector('input[type="number"]');
    const finalIntVal = rows[0].querySelector('input[type="number"]');
    expect(finalBildVal.value).toBe('0');
    expect(finalIntVal.value).toBe('1');
  });
});
