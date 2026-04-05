import { beforeEach, describe, expect, it, vi } from 'vitest';

/**
 * Tests für die Char-Editor Alpine-Komponente.
 *
 * Da char-editor.js `Alpine.data()` auf der aktiven Alpine-Instanz aufruft,
 * mocken wir `window.Alpine.data()` um die Factory-Funktion abzufangen
 * und instanziieren die Komponente dann manuell.
 */

let editorFactory;

beforeEach(async () => {
    // Mock Alpine.data um die Registrierung abzufangen
    window.Alpine = {
        data: vi.fn((name, factory) => {
            if (name === 'charEditor') {
                editorFactory = factory;
            }
        }),
    };

    // Modul-Cache leeren und neu importieren
    vi.resetModules();
    await import('@/alpine/char-editor.js');
});

function createEditor(overrides = {}) {
    const instance = editorFactory();
    Object.assign(instance, overrides);
    // Stub $watch (wird von init() aufgerufen)
    instance.$watch = vi.fn();
    return instance;
}

describe('charEditor – Attribut-Clamping', () => {
    it('begrenzt Attribut auf attributeMax (Nicht-Barbar)', () => {
        const e = createEditor();
        e.attributes.st = 5;
        e.clampAttribute('st');
        expect(e.attributes.st).toBe(1); // max für Nicht-Barbar
    });

    it('begrenzt Attribut auf attributeMax (Barbar)', () => {
        const e = createEditor({ race: 'Barbar' });
        e.attributes.st = 5;
        e.clampAttribute('st');
        expect(e.attributes.st).toBe(2); // max für Barbar
    });

    it('erlaubt Attributwert von -1', () => {
        const e = createEditor();
        e.attributes.ge = -1;
        e.clampAttribute('ge');
        expect(e.attributes.ge).toBe(-1);
    });

    it('begrenzt Attributwert nicht unter -1 auch wenn AP-Budget überschritten', () => {
        const e = createEditor();
        // Alle 2 AP schon von st und ge verbraucht
        e.attributes.st = 1;
        e.attributes.ge = 1;
        e.attributes.ro = -1;
        e.clampAttribute('ro');
        // ro darf nicht unter -1 fallen (auch wenn maxForThis theoretisch < -1)
        expect(e.attributes.ro).toBeGreaterThanOrEqual(-1);
    });

    it('setzt NaN auf 0', () => {
        const e = createEditor();
        e.attributes.wi = NaN;
        e.clampAttribute('wi');
        expect(e.attributes.wi).toBe(0);
    });

    it('respektiert AP-Budget von 2', () => {
        const e = createEditor();
        e.attributes.st = 1;
        e.attributes.ge = 1;
        // AP ist verbraucht, ro darf nicht auf 1 steigen
        e.attributes.ro = 1;
        e.clampAttribute('ro');
        expect(e.attributes.ro).toBe(0);
    });

    it('respektiert AP-Budget von 3 für Barbar (Bonus)', () => {
        const e = createEditor({ race: 'Barbar', raceAPBonus: 1 });
        e.attributes.st = 2;
        e.attributes.ge = 1;
        // 3 AP verbraucht, kein Budget mehr
        e.attributes.ro = 1;
        e.clampAttribute('ro');
        expect(e.attributes.ro).toBe(0);
    });
});

describe('charEditor – Skill-Clamping', () => {
    it('begrenzt Skill auf maxFW', () => {
        const e = createEditor();
        const skill = { name: 'Nahkampf', value: 10 };
        e.skills = [skill];
        e.clampSkillValue(skill);
        expect(skill.value).toBe(4); // maxFW
    });

    it('begrenzt Skill nicht unter Grant-Minimum', () => {
        const e = createEditor();
        e.raceGrants = { Überleben: { type: 'min', value: 1 } };
        const skill = { name: 'Überleben', value: -2 };
        e.skills = [skill];
        e.clampSkillValue(skill);
        expect(skill.value).toBe(1);
    });

    it('begrenzt Skill auf FP-Budget', () => {
        const e = createEditor();
        // Andere Skills verbrauchen 19 FP
        const otherSkill = { name: 'Anderer', value: 19 };
        const testSkill = { name: 'Test', value: 4 };
        e.skills = [otherSkill, testSkill];
        e.clampSkillValue(testSkill);
        expect(testSkill.value).toBe(1); // Nur 1 FP übrig
    });

    it('verhindert negativen maxForThis bei FP-Überschreitung', () => {
        const e = createEditor();
        // Andere Skills verbrauchen bereits 22 FP (> Budget von 20)
        const otherSkill = { name: 'Anderer', value: 22 };
        const testSkill = { name: 'Test', value: 2 };
        e.skills = [otherSkill, testSkill];
        e.clampSkillValue(testSkill);
        // Skill darf nicht unter 0 (Start-Wert) fallen
        expect(testSkill.value).toBe(0);
    });

    it('berücksichtigt exact-Grants beim FP-Budget', () => {
        const e = createEditor();
        e.raceGrants = { FixSkill: { type: 'exact', value: 3 } };
        const fixedSkill = { name: 'FixSkill', value: 3 };
        const freeSkill = { name: 'Frei', value: 4 };
        e.skills = [fixedSkill, freeSkill];
        // Exact-Skills zählen nicht zum FP-Budget
        e.clampSkillValue(freeSkill);
        expect(freeSkill.value).toBe(4);
    });
});

describe('charEditor – Rassen-Logik', () => {
    it('Barbar erhält +1 AP-Bonus', () => {
        const e = createEditor();
        e.init();
        e.applyRaceBarbar();
        expect(e.raceAPBonus).toBe(1);
    });

    it('Barbar erhält Überleben, Intuition und Nahkampf-Skills', () => {
        const e = createEditor();
        e.applyRaceBarbar();
        expect(e.raceGrants).toHaveProperty('Überleben');
        expect(e.raceGrants).toHaveProperty('Intuition');
        expect(e.raceGrants).toHaveProperty('Nahkampf');
        expect(e.skills.find(s => s.name === 'Überleben')).toBeDefined();
    });

    it('Guul setzt AU auf -1', () => {
        const e = createEditor();
        e.applyRaceGuul();
        expect(e.attributes.au).toBe(-1);
    });

    it('Guul erzwingt Nachteile Primitiv und Gejagt', () => {
        const e = createEditor();
        e.applyRaceGuul();
        expect(e.raceLocked.disadvantages).toContain('Primitiv');
        expect(e.raceLocked.disadvantages).toContain('Gejagt');
        expect(e.selectedDisadvantages).toContain('Primitiv');
    });
});

describe('charEditor – Kultur-Logik', () => {
    it('Landbewohner erhält Viehzüchter und Landwirt als Exact-Grants', () => {
        const e = createEditor();
        e.applyCultureLandbewohner();
        expect(e.cultureGrants['Beruf: Viehzüchter']).toEqual({ type: 'exact', value: 2 });
        expect(e.cultureGrants['Beruf: Landwirt']).toEqual({ type: 'exact', value: 2 });
    });

    it('Stadtbewohner erhält Unterhaltungs-Skill und Beruf/Kunde', () => {
        const e = createEditor();
        e.applyCultureStadtbewohner();
        expect(e.cultureGrants).toHaveProperty('Unterhalten');
        expect(e.cultureGrants).toHaveProperty('Beruf');
        expect(e.cultureGrants).toHaveProperty('Kunde');
    });
});

describe('charEditor – Vorteile/Nachteile', () => {
    it('Zäh ist immer aktiv und kann nicht entfernt werden', () => {
        const e = createEditor();
        e.selectedAdvantages = ['Schnell'];
        e.enforceAdvantageLimit();
        expect(e.selectedAdvantages).toContain('Zäh');
    });

    it('begrenzt frei wählbare Vorteile auf 2', () => {
        const e = createEditor();
        e.selectedAdvantages = ['Zäh', 'Schnell', 'Stark', 'Weise'];
        e.enforceAdvantageLimit();
        // Zäh + max 2 frei gewählte
        const chosen = e.selectedAdvantages.filter(a => a !== 'Zäh');
        expect(chosen.length).toBeLessThanOrEqual(2);
    });

    it('isAdvantageDisabled: Zäh ist immer disabled', () => {
        const e = createEditor();
        expect(e.isAdvantageDisabled('Zäh')).toBe(true);
    });
});

describe('charEditor – Computed Properties', () => {
    it('basicsFilled true wenn alle Grunddaten gesetzt', () => {
        const e = createEditor({
            playerName: 'Test',
            characterName: 'Held',
            race: 'Barbar',
            culture: 'Landbewohner',
        });
        expect(e.basicsFilled).toBeTruthy();
    });

    it('basicsFilled false wenn Angabe fehlt', () => {
        const e = createEditor({
            playerName: 'Test',
            characterName: '',
            race: 'Barbar',
            culture: 'Landbewohner',
        });
        expect(e.basicsFilled).toBeFalsy();
    });

    it('apUsed zählt nur positive Attributwerte', () => {
        const e = createEditor();
        e.attributes = { st: 1, ge: -1, ro: 0, wi: 1, wa: 0, in: 0, au: 0 };
        expect(e.apUsed).toBe(2); // 1 + 0 + 0 + 1 + 0 + 0 + 0
    });

    it('fpUsed ignoriert exact-Grants', () => {
        const e = createEditor();
        e.raceGrants = { FixSkill: { type: 'exact', value: 3 } };
        e.skills = [
            { name: 'FixSkill', value: 3 },
            { name: 'Frei', value: 2 },
        ];
        expect(e.fpUsed).toBe(2);
    });
});
