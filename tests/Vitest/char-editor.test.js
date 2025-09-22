import { describe, it, expect, vi } from 'vitest';

const BASE_HTML = `
  <input id="player_name" />
  <input id="character_name" />
  <select id="race"><option value=""></option><option value="Barbar">Barbar</option><option value="Guul">Guul</option></select>
  <select id="culture"><option value=""></option><option value="Landbewohner">Landbewohner</option><option value="Stadtbewohner">Stadtbewohner</option></select>
  <input id="portrait" type="file" />
  <img id="portrait-preview" class="hidden" />
  <select id="advantages" multiple>
    <option value="Zäh">Zäh</option>
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
    </select>
  </div>
  <div id="city-skill-toggle" class="hidden">
    <select id="city-skill-select">
      <option value="Unterhalten">Unterhalten</option>
      <option value="Sprachen">Sprachen</option>
    </select>
  </div>
  <div id="skills-container"></div>
  <datalist id="skills-list">
    <option value="Überleben"></option>
    <option value="Heimlichkeit"></option>
    <option value="Intuition"></option>
    <option value="Natürliche Waffen"></option>
    <option value="Unterhalten"></option>
    <option value="Sprachen"></option>
    <option value="Beruf"></option>
    <option value="Kunde"></option>
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
  vi.resetModules();
  document.body.innerHTML = BASE_HTML;
  for (const [id, value] of Object.entries(values)) {
    const el = document.getElementById(id);
    if (el) el.value = value;
  }
  await import('@/char-editor.js');
  document.dispatchEvent(new Event('DOMContentLoaded'));
}

describe('char-editor pdf button', () => {
  it('is disabled on load', async () => {
    await loadEditor();
    const pdfBtn = document.getElementById('pdf-button');
    expect(pdfBtn.disabled).toBe(true);
  });
});
