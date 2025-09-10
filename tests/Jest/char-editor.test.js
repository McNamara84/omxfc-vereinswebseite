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

  test('pdf button disabled by default', async () => {
    await loadEditor();
    const pdfBtn = document.getElementById('pdf-button');
    expect(pdfBtn.disabled).toBe(true);
  });
});
