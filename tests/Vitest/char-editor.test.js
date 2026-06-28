/**
 * Tests für die Char-Editor Alpine-Komponente.
 *
 * char-editor.js registriert Alpine.data() sofort, wenn window.Alpine bereits
 * verfügbar ist. Andernfalls wartet das Modul auf 'alpine:init'.
 */

let editorFactory;

const specialRuleConfig = {
    advantages: [
        'Anführer',
        'Gestaltwandler',
        'Gesteigertes Attribut',
        'Gesteigerter Sinn',
        'High-Tech-Ausrüstung',
        'Kampfreflexe',
        'Kaltblütig',
        'Kiemen',
        'Kind zweier Welten',
        'Nachtsicht',
        'Natürliche Waffen',
        'Panzerung',
        'Psychische Kraft',
        'Psychisches Reservoir',
        'Regeneration',
        'Scharfschütze',
        'Schnell',
        'Sprachbegabt',
        'Tiergefährte',
        'Zäh',
    ],
    disadvantages: [
        'Abergläubisch',
        'Abhängige',
        'Anfälligkeit gegen Wahnsinn',
        'Auffällig',
        'Blutdurst',
        'Ehrenkodex',
        'Feind',
        'Gejagt',
        'Lichtscheu',
        'Primitiv',
        'Taratzenfutter',
        'Tödliche Immunschwäche',
        'Verpflichtung',
        'Verwundbarkeit',
    ],
    advantageCosts: {
        Gestaltwandler: 3,
        Zäh: 0,
    },
    repeatableAdvantages: ['Panzerung'],
    advantageDetailRequired: ['Gesteigertes Attribut', 'Gesteigerter Sinn', 'Tiergefährte'],
    disadvantageDetailRequired: [
        'Abergläubisch',
        'Abhängige',
        'Ehrenkodex',
        'Feind',
        'Gejagt',
        'Verpflichtung',
        'Verwundbarkeit',
    ],
};

beforeEach(async () => {
    window.rpgCharEditorRules = JSON.parse(JSON.stringify(specialRuleConfig));

    // Mock Alpine.data um die Registrierung abzufangen
    window.Alpine = {
        data: vi.fn((name, factory) => {
            if (name === 'charEditor') {
                editorFactory = factory;
            }
        }),
        initTree: vi.fn(),
        destroyTree: vi.fn(),
        $data: vi.fn(() => ({
            basicsFilled: vi.fn(),
            formValid: vi.fn(),
            advancedUnlocked: false,
        })),
    };

    document.body.innerHTML = '<form x-data="charEditor"></form>';

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

describe('charEditor – Registrierung', () => {
    it('registriert die Komponente sofort wenn Alpine bereits verfügbar ist', () => {
        expect(window.Alpine.data).toHaveBeenCalledWith('charEditor', expect.any(Function));
        expect(editorFactory).toBeTypeOf('function');
        expect(window.Alpine.initTree).toHaveBeenCalledWith(document.querySelector('[x-data="charEditor"]'));
        expect(window.Alpine.destroyTree).not.toHaveBeenCalled();
    });

    it('registriert die Komponente über alpine:init wenn Alpine erst später verfügbar ist', async () => {
        vi.resetModules();
        editorFactory = undefined;
        delete window.Alpine;

        const lateAlpine = {
            data: vi.fn((name, factory) => {
                if (name === 'charEditor') {
                    editorFactory = factory;
                }
            }),
            initTree: vi.fn(),
        };

        await import('@/alpine/char-editor.js');

        expect(editorFactory).toBeUndefined();

        window.Alpine = lateAlpine;
        document.dispatchEvent(new CustomEvent('alpine:init'));
        document.dispatchEvent(new CustomEvent('alpine:init'));

        expect(lateAlpine.data).toHaveBeenCalledWith('charEditor', expect.any(Function));
        expect(lateAlpine.data).toHaveBeenCalledTimes(1);
        expect(editorFactory).toBeTypeOf('function');
        expect(lateAlpine.initTree).not.toHaveBeenCalled();
    });

    it('initialisiert bereits hydratisierte charEditor-Wurzeln nicht erneut', async () => {
        vi.resetModules();

        const existingRoot = document.querySelector('[x-data="charEditor"]');
        existingRoot._x_dataStack = [{}];

        window.Alpine.data.mockClear();
        window.Alpine.initTree.mockClear();

        await import('@/alpine/char-editor.js');

        expect(window.Alpine.data).toHaveBeenCalledWith('charEditor', expect.any(Function));
        expect(window.Alpine.initTree).not.toHaveBeenCalled();
        expect(window.Alpine.destroyTree).not.toHaveBeenCalled();
    });

    it('reinitialisiert bereits gestartete Wurzeln mit unvollständigem Scope', async () => {
        vi.resetModules();

        const existingRoot = document.querySelector('[x-data="charEditor"]');
        existingRoot._x_dataStack = [{}];

        window.Alpine.data.mockClear();
        window.Alpine.initTree.mockClear();
        window.Alpine.destroyTree.mockClear();
        window.Alpine.$data.mockReturnValueOnce({ playerName: 'alt' });

        await import('@/alpine/char-editor.js');

        expect(window.Alpine.data).toHaveBeenCalledWith('charEditor', expect.any(Function));
        expect(window.Alpine.destroyTree).toHaveBeenCalledWith(existingRoot);
        expect(window.Alpine.initTree).toHaveBeenCalledWith(existingRoot);
    });
});

describe('charEditor – Attribut-Clamping', () => {
    it('liest AP aus der Attribut-Regelkonfiguration', async () => {
        window.rpgCharEditorRules.attributeRules = {
            creationPoints: 3,
            attributes: [],
        };

        vi.resetModules();
        await import('@/alpine/char-editor.js');

        const e = createEditor();

        expect(e.base.AP).toBe(3);
        expect(e.apRemaining()).toBe(3);
    });

    it('begrenzt Attribut auf attributeBaseMax (Nicht-Barbar)', () => {
        const e = createEditor();
        e.attributes.st = 5;
        e.clampAttribute('st');
        expect(e.attributes.st).toBe(1); // max für Nicht-Barbar
    });

    it('begrenzt Attribut auf attributeBaseMax (Barbar)', () => {
        const e = createEditor({ race: 'Barbar' });
        e.applyRaceBarbar();
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

    it('erlaubt Attributwert von -2 bei negativen Rassenmodifikatoren', () => {
        const e = createEditor({ race: 'Techno' });
        e.applyRaceTechno();

        e.attributes.st = -2;
        e.clampAttribute('st');

        expect(e.attributes.st).toBe(-2);
        expect(e.getAttributeMin('st')).toBe(-2);
        expect(e.getAttributeMax('st')).toBe(0);
    });

    it('liefert Regelhinweise für Attribut-Hilfetexte', () => {
        const e = createEditor();

        expect(e.attributeTooltip('st')).toContain('Muskelkraft');
        expect(e.attributeTooltip('st')).toContain('2W6 + Attributswert x 3');
        expect(e.attributeTooltip('st')).toContain('Regelbereich aktuell: -1 bis 1');
    });

    it('liefert Regelhinweise für Fertigkeiten und Spezialisierungen', () => {
        const e = createEditor();

        expect(e.skillTooltip('Athletik')).toContain('Attribute: ST, GE, RO');
        expect(e.skillTooltip('Athletik')).toContain('Klettern');
        expect(e.skillTooltip('Beruf: Bauer')).toContain('Spezialisierung');
        expect(e.skillTooltip('  Beruf:Künstler  ')).toContain('Spezialisierung');
        expect(e.skillTooltip('Natuerliche_Waffen')).toContain('Rassenbedingte Sonderregel');
        expect(e.skillTooltip('Bildung')).toContain('Kind zweier Welten');
        expect(e.skillTooltip('Natürliche Waffen')).toContain('Rassenbedingte Sonderregel');
    });

    it('schlägt nur frei wählbare Fertigkeiten in der Datalist vor', () => {
        const e = createEditor();

        expect(e.skillSuggestions()).toContain('Athletik');
        expect(e.skillSuggestions()).toContain('Beruf: Viehzüchter');
        expect(e.skillSuggestions()).not.toContain('Natürliche Waffen');
    });

    it('vergibt stabile eindeutige Keys für Fertigkeitszeilen', () => {
        const e = createEditor();

        e.addSkill();
        const firstUid = e.skills[0].uid;
        e.ensureSkill('Nahkampf');
        e.removeSkill(0);
        e.addSkill();

        expect(firstUid).toBe('skill-1');
        expect(e.skills.map(skill => skill.uid)).toEqual(['skill-2', 'skill-3']);
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

    it('respektiert AP-Budget von 2 neben einem kostenlosen Barbar-Attributbonus', () => {
        const e = createEditor({ race: 'Barbar' });
        e.applyRaceBarbar();
        e.attributes.st = 2;
        e.attributes.ge = 1;
        // 2 bezahlte AP sind verbraucht: ST kostet wegen Rassenbonus nur 1, GE kostet 1.
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
    it('Barbar erhält einen kostenlosen Attributbonus statt zusätzlicher AP', () => {
        const e = createEditor({ race: 'Barbar' });
        e.applyRaceBarbar();

        expect(e.raceAPBonus).toBe(0);
        expect(e.barbarAttributeBonus).toBe('st');
        expect(e.attributes.st).toBe(1);
        expect(e.apUsed()).toBe(0);
        expect(e.apRemaining()).toBe(2);
        expect(e.getAttributeMax('st')).toBe(2);
    });

    it('Barbar-Attributbonus kann ohne AP-Kosten gewechselt werden', () => {
        const e = createEditor({ race: 'Barbar' });
        e.applyRaceBarbar();

        e.barbarAttributeBonus = 'ge';
        e.setBarbarAttributeBonus(e.barbarAttributeBonus);

        expect(e.barbarAttributeBonus).toBe('ge');
        expect(e.attributes.st).toBe(0);
        expect(e.attributes.ge).toBe(1);
        expect(e.apUsed()).toBe(0);
        expect(e.getAttributeMax('ge')).toBe(2);
    });

    it('Barbar erhält Überleben, Intuition und Nahkampf-Skills', () => {
        const e = createEditor();
        e.applyRaceBarbar();
        expect(e.raceGrants).toHaveProperty('Überleben');
        expect(e.raceGrants).toHaveProperty('Intuition');
        expect(e.raceGrants).toHaveProperty('Nahkampf');
        expect(e.skills.find(s => s.name === 'Überleben')).toBeDefined();
    });

    it('Guul setzt AU als kostenlosen Rassenmodifikator auf -1', () => {
        const e = createEditor({ race: 'Guul' });
        e.applyRaceGuul();

        expect(e.attributes.au).toBe(-1);
        expect(e.apUsed()).toBe(0);
        expect(e.getAttributeMin('au')).toBe(-2);
        expect(e.getAttributeMax('au')).toBe(0);

        e.clearRace();

        expect(e.attributes.au).toBe(0);
    });

    it('Guul erzwingt Natürliche Waffen sowie Nachteile Primitiv und Gejagt', () => {
        const e = createEditor();
        e.applyRaceGuul();
        expect(e.raceLocked.advantages).toEqual(['Natürliche Waffen']);
        expect(e.selectedAdvantages).toEqual(expect.arrayContaining(['Zäh', 'Natürliche Waffen']));
        expect(e.selectedDisabledAdvantages()).toEqual(['Zäh', 'Natürliche Waffen']);
        expect(e.raceLocked.disadvantages).toContain('Primitiv');
        expect(e.raceLocked.disadvantages).toContain('Gejagt');
        expect(e.selectedDisadvantages).toContain('Primitiv');
    });

    it('Hydrit erhält Athletik, Bildung, natürliche Waffen und Pflichtmerkmale', () => {
        const e = createEditor();
        e.applyRaceHydrit();

        expect(e.raceGrants.Athletik).toEqual({ type: 'min', value: 2 });
        expect(e.raceGrants.Bildung).toEqual({ type: 'min', value: 1 });
        expect(e.raceGrants['Natürliche Waffen']).toEqual({ type: 'min', value: 1 });
        expect(e.skills.find(s => s.name === 'Athletik')).toMatchObject({ value: 2, badge: 'Rasse' });
        expect(e.selectedAdvantages).toEqual(expect.arrayContaining(['Kiemen', 'Natürliche Waffen']));
        expect(e.raceLocked.advantages).toEqual(['Kiemen', 'Natürliche Waffen']);
        expect(e.selectedDisadvantages).toContain('Anfälligkeit gegen Wahnsinn');
        expect(e.raceLocked.disadvantages).toEqual(['Anfälligkeit gegen Wahnsinn']);
    });

    it('Hydrit-Pflichtvorteile verbrauchen keine freien Vorteilspunkte', () => {
        const e = createEditor();
        e.applyRaceHydrit();

        expect(e.chosenAdvantagesCount()).toBe(0);
        expect(e.freeAdvantagePoints()).toBe(2);
        expect(e.selectedDisabledAdvantages()).toEqual(['Zäh', 'Kiemen', 'Natürliche Waffen']);

        e.selectedAdvantages.push('Schnell', 'Kampfreflexe', 'Nachtsicht');
        e.enforceAdvantageLimit();

        expect(e.selectedAdvantages).toEqual(['Zäh', 'Kiemen', 'Natürliche Waffen', 'Schnell', 'Kampfreflexe']);
    });

    it('Rassenwechsel entfernt Hydrit-Pflichtmerkmale und behält nur übrige Grants', () => {
        const e = createEditor();
        e.applyRaceHydrit();
        e.clearRace();

        expect(e.raceLocked.advantages).toEqual([]);
        expect(e.raceLocked.disadvantages).toEqual([]);
        expect(e.selectedAdvantages).toEqual(['Zäh']);
        expect(e.selectedDisadvantages).not.toContain('Anfälligkeit gegen Wahnsinn');
        expect(e.skills.find(s => s.name === 'Athletik')).toBeUndefined();
    });

    it('Nosfera erhält kostenlose Attributsmodifikatoren, Fertigkeiten und Pflichtmerkmale', () => {
        const e = createEditor({ race: 'Nosfera' });
        e.applyRaceNosfera();

        expect(e.attributes).toMatchObject({ ge: 1, au: -1 });
        expect(e.apUsed()).toBe(0);
        expect(e.apRemaining()).toBe(2);
        expect(e.getAttributeMin('ge')).toBe(0);
        expect(e.getAttributeMax('ge')).toBe(2);
        expect(e.getAttributeMin('au')).toBe(-2);
        expect(e.getAttributeMax('au')).toBe(0);
        expect(e.raceGrants.Intuition).toEqual({ type: 'min', value: 2 });
        expect(e.raceGrants.Heimlichkeit).toEqual({ type: 'min', value: 2 });
        expect(e.skills.find(s => s.name === 'Intuition')).toMatchObject({ value: 2, badge: 'Rasse' });
        expect(e.skills.find(s => s.name === 'Heimlichkeit')).toMatchObject({ value: 2, badge: 'Rasse' });
        expect(e.raceLocked.advantages).toEqual(['Nachtsicht']);
        expect(e.raceLocked.disadvantages).toEqual(['Blutdurst', 'Lichtscheu', 'Gejagt']);
        expect(e.selectedAdvantages).toEqual(expect.arrayContaining(['Zäh', 'Nachtsicht']));
        expect(e.selectedAdvantages).not.toContain('Psychisches Reservoir');
        expect(e.selectedDisadvantages).toEqual(expect.arrayContaining(['Blutdurst', 'Lichtscheu', 'Gejagt']));
        expect(e.chosenAdvantagesCount()).toBe(0);
        expect(e.freeAdvantagePoints()).toBe(2);
        expect(e.selectedDisabledAdvantages()).toEqual(['Zäh', 'Nachtsicht']);
        expect(e.selectedLockedDisadvantages()).toEqual(['Blutdurst', 'Lichtscheu', 'Gejagt']);
    });

    it('Rassenwechsel entfernt Nosfera-Pflichtmerkmale, Fertigkeiten und Attributsmodifikatoren', () => {
        const e = createEditor({ race: 'Nosfera' });
        e.applyRaceNosfera();
        e.clearRace();

        expect(e.attributes.ge).toBe(0);
        expect(e.attributes.au).toBe(0);
        expect(e.raceLocked.advantages).toEqual([]);
        expect(e.raceLocked.disadvantages).toEqual([]);
        expect(e.selectedAdvantages).toEqual(['Zäh']);
        expect(e.selectedDisadvantages).toEqual([]);
        expect(e.skills.find(s => s.name === 'Intuition')).toBeUndefined();
        expect(e.skills.find(s => s.name === 'Heimlichkeit')).toBeUndefined();
    });

    it('Taratze erhält kostenlose Attributsmodifikatoren, Fertigkeiten und Pflichtnachteile', () => {
        const e = createEditor({ race: 'Taratze' });
        e.applyRaceTaratze();

        expect(e.attributes).toMatchObject({ st: 1, wa: 1, in: -1, au: -1 });
        expect(e.apUsed()).toBe(0);
        expect(e.apRemaining()).toBe(2);
        expect(e.getAttributeMax('st')).toBe(2);
        expect(e.getAttributeMax('wa')).toBe(2);
        expect(e.getAttributeMin('in')).toBe(-2);
        expect(e.getAttributeMax('in')).toBe(0);
        expect(e.getAttributeMin('au')).toBe(-2);
        expect(e.getAttributeMax('au')).toBe(0);
        expect(e.raceGrants.Intuition).toEqual({ type: 'min', value: 2 });
        expect(e.raceGrants.Heimlichkeit).toEqual({ type: 'min', value: 1 });
        expect(e.raceGrants['Überleben']).toEqual({ type: 'min', value: 1 });
        expect(e.skills.find(s => s.name === 'Intuition')).toMatchObject({ value: 2, badge: 'Rasse' });
        expect(e.skills.find(s => s.name === 'Heimlichkeit')).toMatchObject({ value: 1, badge: 'Rasse' });
        expect(e.skills.find(s => s.name === 'Überleben')).toMatchObject({ value: 1, badge: 'Rasse' });
        expect(e.raceLocked.advantages).toEqual([]);
        expect(e.raceLocked.disadvantages).toEqual(['Auffällig', 'Primitiv', 'Gejagt']);
        expect(e.selectedDisadvantages).toEqual(expect.arrayContaining(['Auffällig', 'Primitiv', 'Gejagt']));
        expect(e.chosenAdvantagesCount()).toBe(0);
        expect(e.freeAdvantagePoints()).toBe(2);
        expect(e.selectedLockedDisadvantages()).toEqual(['Auffällig', 'Primitiv', 'Gejagt']);
    });

    it('Rassenwechsel entfernt Taratze-Pflichtnachteile, Fertigkeiten und Attributsmodifikatoren', () => {
        const e = createEditor({ race: 'Taratze' });
        e.applyRaceTaratze();
        e.clearRace();

        expect(e.attributes).toMatchObject({ st: 0, wa: 0, in: 0, au: 0 });
        expect(e.raceLocked.disadvantages).toEqual([]);
        expect(e.selectedDisadvantages).toEqual([]);
        expect(e.skills.find(s => s.name === 'Intuition')).toBeUndefined();
        expect(e.skills.find(s => s.name === 'Heimlichkeit')).toBeUndefined();
        expect(e.skills.find(s => s.name === 'Überleben')).toBeUndefined();
    });

    it('Wulfane erhält kostenlose Attributsmodifikatoren, Fertigkeiten und Ehrenkodex', () => {
        const e = createEditor({ race: 'Wulfane' });
        e.applyRaceWulfane();

        expect(e.attributes).toMatchObject({ ro: 1, au: -1 });
        expect(e.apUsed()).toBe(0);
        expect(e.apRemaining()).toBe(2);
        expect(e.getAttributeMax('ro')).toBe(2);
        expect(e.getAttributeMin('au')).toBe(-2);
        expect(e.getAttributeMax('au')).toBe(0);
        expect(e.raceGrants.Intuition).toEqual({ type: 'min', value: 1 });
        expect(e.raceGrants.Nahkampf).toEqual({ type: 'min', value: 1 });
        expect(e.skills.find(s => s.name === 'Intuition')).toMatchObject({ value: 1, badge: 'Rasse' });
        expect(e.skills.find(s => s.name === 'Nahkampf')).toMatchObject({ value: 1, badge: 'Rasse' });
        expect(e.raceLocked.advantages).toEqual([]);
        expect(e.raceLocked.disadvantages).toEqual(['Ehrenkodex']);
        expect(e.selectedDisadvantages).toContain('Ehrenkodex');
        expect(e.selectedLockedDisadvantages()).toEqual(['Ehrenkodex']);
    });

    it('Rassenwechsel entfernt Wulfane-Pflichtnachteil, Fertigkeiten und Attributsmodifikatoren', () => {
        const e = createEditor({ race: 'Wulfane' });
        e.applyRaceWulfane();
        e.clearRace();

        expect(e.attributes.ro).toBe(0);
        expect(e.attributes.au).toBe(0);
        expect(e.raceLocked.disadvantages).toEqual([]);
        expect(e.selectedDisadvantages).toEqual([]);
        expect(e.skills.find(s => s.name === 'Intuition')).toBeUndefined();
        expect(e.skills.find(s => s.name === 'Nahkampf')).toBeUndefined();
    });

    it('Rassenwechsel zu Taratze und Wulfane wendet die neuen Rassenregeln im Editorfluss an', () => {
        const e = createEditor({ race: 'Taratze', culture: 'Stadtbewohner' });
        e.handleRaceChange();

        expect(e.raceGrants.Intuition).toEqual({ type: 'min', value: 2 });
        expect(e.raceLocked.disadvantages).toEqual(['Auffällig', 'Primitiv', 'Gejagt']);
        expect(e.culture).toBe('Stadtbewohner');

        e.race = 'Wulfane';
        e.handleRaceChange();

        expect(e.raceGrants.Intuition).toEqual({ type: 'min', value: 1 });
        expect(e.raceGrants.Nahkampf).toEqual({ type: 'min', value: 1 });
        expect(e.raceGrants.Heimlichkeit).toBeUndefined();
        expect(e.raceLocked.disadvantages).toEqual(['Ehrenkodex']);
        expect(e.selectedDisadvantages).toEqual(['Ehrenkodex']);
        expect(e.culture).toBe('Stadtbewohner');
    });

    it('Techno erhält kostenlose Attributsmodifikatoren, Pflichtmerkmale und 12 Rassen-Fertigkeitspunkte', () => {
        const e = createEditor({ race: 'Techno' });
        e.applyRaceTechno();

        expect(e.attributes).toMatchObject({ st: -1, ro: -1, in: 1 });
        expect(e.apUsed()).toBe(0);
        expect(e.apRemaining()).toBe(2);
        expect(e.getAttributeMin('st')).toBe(-2);
        expect(e.getAttributeMax('st')).toBe(0);
        expect(e.getAttributeMin('ro')).toBe(-2);
        expect(e.getAttributeMax('ro')).toBe(0);
        expect(e.getAttributeMin('in')).toBe(0);
        expect(e.getAttributeMax('in')).toBe(2);
        expect(e.technoPoolUsed()).toBe(12);
        expect(e.technoSkillPoolComplete()).toBe(true);
        expect(e.raceGrants.Bildung).toEqual({ type: 'min', value: 3 });
        expect(e.skills.find(s => s.name === 'Bildung')).toMatchObject({ value: 3, badge: 'Rasse' });
        expect(e.raceGrants.Fahren).toEqual({ type: 'min', value: 2 });
        expect(e.raceGrants.Feuerwaffen).toEqual({ type: 'min', value: 2 });
        expect(e.raceGrants.Heiler).toEqual({ type: 'min', value: 2 });
        expect(e.raceGrants.Pilot).toEqual({ type: 'min', value: 2 });
        expect(e.raceGrants.Techniker).toEqual({ type: 'min', value: 2 });
        expect(e.raceGrants.Wissenschaftler).toEqual({ type: 'min', value: 2 });
        expect(e.raceLocked.advantages).toEqual(['High-Tech-Ausrüstung']);
        expect(e.raceLocked.disadvantages).toEqual(['Tödliche Immunschwäche']);
        expect(e.selectedAdvantages).toEqual(expect.arrayContaining(['Zäh', 'High-Tech-Ausrüstung']));
        expect(e.selectedDisadvantages).toContain('Tödliche Immunschwäche');
    });

    it('Techno rechnet bereits bezahlte Attribute beim Rassenwechsel in modifizierte Endwerte um', () => {
        const e = createEditor({ race: 'Techno' });
        e.attributes.st = 1;
        e.attributes.ro = 0;
        e.attributes.in = 1;

        e.applyRaceTechno();

        expect(e.attributes.st).toBe(0);
        expect(e.attributes.ro).toBe(-1);
        expect(e.attributes.in).toBe(2);
        expect(e.apUsed()).toBe(2);
        expect(e.apRemaining()).toBe(0);
        expect(e.getAttributeMax('st')).toBe(0);
    });

    it('Rassenwechsel weg von Techno rechnet modifizierte Attribute auf bezahlte Basiswerte zurück', () => {
        const e = createEditor({ race: 'Techno' });
        e.applyRaceTechno();
        e.attributes.st = 0;
        e.attributes.ro = -1;
        e.attributes.in = 2;

        e.clearRace();

        expect(e.attributes.st).toBe(1);
        expect(e.attributes.ro).toBe(0);
        expect(e.attributes.in).toBe(1);
        expect(e.apUsed()).toBe(2);
    });

    it('Techno-Pool begrenzt Einzelwerte und verlangt exakt 12 verteilte Punkte', () => {
        const e = createEditor({ race: 'Techno' });
        e.applyRaceTechno();

        e.setTechnoSkillPoints('Fahren', 9);

        expect(e.technoSkillPoints.Fahren).toBe(4);
        expect(e.raceGrants.Fahren).toEqual({ type: 'min', value: 4 });
        expect(e.technoPoolUsed()).toBe(14);
        expect(e.technoSkillPoolComplete()).toBe(false);

        e.setTechnoSkillPoints('Heiler', 0);

        expect(e.technoPoolUsed()).toBe(12);
        expect(e.technoSkillPoolComplete()).toBe(true);
        expect(e.raceGrants.Heiler).toBeUndefined();
        expect(e.skills.find(s => s.name === 'Heiler')).toBeUndefined();
    });

    it('stellt Rassen-Info-Zusammenfassungen für alle auswählbaren Rassen bereit', () => {
        const e = createEditor();

        ['Barbar', 'Guul', 'Hydrit', 'Nosfera', 'Taratze', 'Wulfane', 'Techno', 'Präkristofluu'].forEach((raceName) => {
            e.setRaceInfoPreview(raceName);

            expect(e.raceInfo()).toMatchObject({ name: raceName });
            expect(e.raceInfoRows().length).toBeGreaterThanOrEqual(4);
        });

        e.setRaceInfoPreview('Techno');

        expect(e.raceInfo().skills).toContain('Bildung +3');

        e.setRaceInfoPreview('');

        expect(e.raceInfoPreview).toBe('');
        expect(e.raceInfo()).toBeNull();
    });

    it('Präkristofluu erhält Beruf, High-Tech-Ausrüstung und 12 Rassen-Fertigkeitspunkte', () => {
        const e = createEditor({ race: 'Präkristofluu' });
        e.applyRacePraekristofluu();

        expect(e.apRemaining()).toBe(2);
        expect(e.praekristofluuPoolUsed()).toBe(12);
        expect(e.praekristofluuSkillPoolComplete()).toBe(true);
        expect(e.raceGrants.Beruf).toEqual({ type: 'min', value: 3 });
        expect(e.raceGrants.Bildung).toEqual({ type: 'min', value: 2 });
        expect(e.raceGrants.Fahren).toEqual({ type: 'min', value: 2 });
        expect(e.raceGrants.Feuerwaffen).toEqual({ type: 'min', value: 2 });
        expect(e.raceGrants.Pilot).toEqual({ type: 'min', value: 2 });
        expect(e.raceGrants.Techniker).toEqual({ type: 'min', value: 2 });
        expect(e.raceGrants.Wissenschaftler).toEqual({ type: 'min', value: 2 });
        expect(e.raceLocked.advantages).toEqual(['High-Tech-Ausrüstung']);
        expect(e.raceLocked.disadvantages).toEqual([]);
        expect(e.selectedAdvantages).toEqual(expect.arrayContaining(['Zäh', 'High-Tech-Ausrüstung']));
    });

    it('Präkristofluu-Pool begrenzt Einzelwerte und verlangt exakt 12 verteilte Punkte', () => {
        const e = createEditor({ race: 'Präkristofluu' });
        e.applyRacePraekristofluu();

        e.setPraekristofluuSkillPoints('Bildung', 9);

        expect(e.praekristofluuSkillPoints.Bildung).toBe(4);
        expect(e.raceGrants.Bildung).toEqual({ type: 'min', value: 4 });
        expect(e.praekristofluuPoolUsed()).toBe(14);
        expect(e.praekristofluuSkillPoolComplete()).toBe(false);
        expect(e.formValid()).toBe(false);

        e.setPraekristofluuSkillPoints('Feuerwaffen', 0);

        expect(e.praekristofluuPoolUsed()).toBe(12);
        expect(e.praekristofluuSkillPoolComplete()).toBe(true);
        expect(e.raceGrants.Feuerwaffen).toBeUndefined();
        expect(e.skills.find(s => s.name === 'Feuerwaffen')).toBeUndefined();
    });

    it('Rassenwechsel entfernt Präkristofluu-Pflichtvorteil und Pool-Grants', () => {
        const e = createEditor({ race: 'Präkristofluu' });
        e.applyRacePraekristofluu();

        e.clearRace();

        expect(e.raceLocked.advantages).toEqual([]);
        expect(e.selectedAdvantages).toEqual(['Zäh']);
        expect(e.raceGrants).toEqual({});
        expect(e.praekristofluuPoolUsed()).toBe(0);
        expect(e.skills.find(s => s.name === 'Beruf')).toBeUndefined();
        expect(e.skills.find(s => s.name === 'Bildung')).toBeUndefined();
    });
});

describe('charEditor – Kultur-Logik', () => {
    it('Hydrit erlaubt nur Meeresbewohner als Kultur', () => {
        const e = createEditor({ race: 'Hydrit' });

        expect(e.allowedCulturesForRace()).toEqual(['Meeresbewohner']);
        expect(e.isCultureSelectable('Meeresbewohner')).toBe(true);
        expect(e.isCultureSelectable('Landbewohner')).toBe(false);
        expect(e.isCultureSelectable('Stadtbewohner')).toBe(false);

        e.race = 'Barbar';

        expect(e.isCultureSelectable('Landbewohner')).toBe(true);
        expect(e.isCultureSelectable('Stadtbewohner')).toBe(true);
        expect(e.isCultureSelectable('Meeresbewohner')).toBe(false);
        expect(e.isCultureSelectable('Nomade')).toBe(true);
        expect(e.isCultureSelectable('Ruinenbewohner')).toBe(true);
        expect(e.isCultureSelectable('Untergrundbewohner')).toBe(true);
        expect(e.isCultureSelectable('Volk der 13 Inseln')).toBe(true);

        const barbar = createEditor({ race: 'Barbar', culture: '' });
        barbar.handleRaceChange();

        expect(barbar.culture).toBe('');
    });

    it('erzwingt Hydrit-Kultur ohne direkten Kultur-Handler-Durchlauf', () => {
        const e = createEditor({ race: 'Hydrit', culture: 'Landbewohner' });
        e.applyCultureLandbewohner();
        const handleCultureChange = vi.spyOn(e, 'handleCultureChange');

        expect(e.enforceCultureForRace()).toBe(true);

        expect(e.culture).toBe('Meeresbewohner');
        expect(handleCultureChange).not.toHaveBeenCalled();
        expect(e.cultureGrants).toEqual({});
        expect(e.skills.find(s => s.name === 'Beruf: Viehzüchter')).toBeUndefined();
    });

    it('Rassenwechsel zu Hydrit setzt Kultur auf Meeresbewohner und ersetzt alte Kultur-Grants', () => {
        const e = createEditor({ race: 'Hydrit', culture: 'Landbewohner' });
        e.applyCultureLandbewohner();

        expect(e.cultureGrants['Beruf: Viehzüchter']).toEqual({ type: 'min', value: 2 });

        e.handleRaceChange();

        expect(e.culture).toBe('Meeresbewohner');
        expect(e.cultureGrants['Beruf: Viehzüchter']).toBeUndefined();
        expect(e.cultureGrants['Beruf: Farmer']).toBeUndefined();
        expect(e.skills.find(s => s.name === 'Beruf: Viehzüchter')).toBeUndefined();

        e.handleCultureChange();

        expect(e.cultureGrants['Beruf: Farmer']).toEqual({ type: 'min', value: 1 });
        expect(e.cultureGrants.Wissenschaftler).toEqual({ type: 'min', value: 1 });
        expect(e.skills.find(s => s.name === 'Athletik')).toMatchObject({ value: 2, badge: 'Rasse/Kultur' });
    });

    it('direkter Kulturwechsel verhindert ungueltige Hydrit-Kultur', () => {
        const e = createEditor({ race: 'Hydrit', culture: 'Landbewohner' });
        e.applyRaceHydrit();

        e.handleCultureChange();

        expect(e.culture).toBe('Meeresbewohner');
        expect(e.cultureGrants).toEqual({});

        e.handleCultureChange();

        expect(e.cultureGrants.Athletik).toEqual({ type: 'min', value: 1 });
        expect(e.skills.find(s => s.name === 'Athletik')).toMatchObject({ value: 2, badge: 'Rasse/Kultur' });
    });

    it('Techno erzwingt Bunkermensch und nur der Auto-Lock sperrt Rassen auf Techno', () => {
        const techno = createEditor({ race: 'Techno' });

        expect(techno.allowedCulturesForRace()).toEqual(['Bunkermensch']);
        expect(techno.isCultureSelectable('Bunkermensch')).toBe(true);
        expect(techno.isCultureSelectable('Landbewohner')).toBe(false);
        expect(techno.isCultureSelectable('Meeresbewohner')).toBe(false);

        const barbar = createEditor({ race: 'Barbar' });

        expect(barbar.isCultureSelectable('Landbewohner')).toBe(true);
        expect(barbar.isCultureSelectable('Stadtbewohner')).toBe(true);
        expect(barbar.isCultureSelectable('Meeresbewohner')).toBe(false);
        expect(barbar.isCultureSelectable('Nomade')).toBe(true);
        expect(barbar.isCultureSelectable('Ruinenbewohner')).toBe(true);
        expect(barbar.isCultureSelectable('Untergrundbewohner')).toBe(true);
        expect(barbar.isCultureSelectable('Volk der 13 Inseln')).toBe(true);
        expect(barbar.isCultureSelectable('Bunkermensch')).toBe(true);

        const bunker = createEditor({ race: 'Techno', culture: 'Bunkermensch', raceLockedByBunkermenschCulture: true });

        expect(bunker.isRaceSelectable('Barbar')).toBe(false);
        expect(bunker.isRaceSelectable('Guul')).toBe(false);
        expect(bunker.isRaceSelectable('Hydrit')).toBe(false);
        expect(bunker.isRaceSelectable('Techno')).toBe(true);
        expect(bunker.isCultureSelectable('Landbewohner')).toBe(true);
        expect(bunker.isCultureSelectable('Ruinenbewohner')).toBe(true);
        expect(bunker.isCultureSelectable('Untergrundbewohner')).toBe(true);
        expect(bunker.isCultureSelectable('Volk der 13 Inseln')).toBe(false);

        const manualTechno = createEditor({ race: 'Techno', culture: 'Bunkermensch' });

        expect(manualTechno.isRaceSelectable('Barbar')).toBe(true);
        expect(manualTechno.isRaceSelectable('Guul')).toBe(true);
        expect(manualTechno.isRaceSelectable('Hydrit')).toBe(true);
        expect(manualTechno.isRaceSelectable('Techno')).toBe(true);
        expect(manualTechno.isCultureSelectable('Landbewohner')).toBe(false);
    });

    it('Rassenwechsel zu Techno setzt Kultur auf Bunkermensch und ersetzt alte Kultur-Grants', () => {
        const e = createEditor({ race: 'Techno', culture: 'Landbewohner' });
        e.applyCultureLandbewohner();

        expect(e.cultureGrants['Beruf: Viehzüchter']).toEqual({ type: 'min', value: 2 });

        e.handleRaceChange();

        expect(e.culture).toBe('Bunkermensch');
        expect(e.raceLockedByBunkermenschCulture).toBe(false);
        expect(e.cultureGrants['Beruf: Viehzüchter']).toBeUndefined();
        expect(e.skills.find(s => s.name === 'Beruf: Viehzüchter')).toBeUndefined();

        e.handleCultureChange();

        expect(e.cultureGrants.Bildung).toEqual({ type: 'min', value: 1 });
        expect(e.cultureGrants.Nahkampf).toEqual({ type: 'min', value: 1 });
        expect(e.cultureGrants.Feuerwaffen).toEqual({ type: 'min', value: 3 });
    });

    it('Präkristofluu erlaubt nur Mensch des 21. Jahrhunderts als Kultur', () => {
        const praekristofluu = createEditor({ race: 'Präkristofluu' });

        expect(praekristofluu.allowedCulturesForRace()).toEqual(['Mensch des 21. Jahrhunderts']);
        expect(praekristofluu.isCultureSelectable('Mensch des 21. Jahrhunderts')).toBe(true);
        expect(praekristofluu.isCultureSelectable('Landbewohner')).toBe(false);
        expect(praekristofluu.isCultureSelectable('Bunkermensch')).toBe(false);

        const barbar = createEditor({ race: 'Barbar' });

        expect(barbar.isCultureSelectable('Mensch des 21. Jahrhunderts')).toBe(false);
        expect(barbar.allowedCulturesForRace()).not.toContain('Mensch des 21. Jahrhunderts');
    });

    it('Rassenwechsel zu Präkristofluu setzt Mensch des 21. Jahrhunderts und ersetzt alte Kultur-Grants', () => {
        const e = createEditor({ race: 'Präkristofluu', culture: 'Landbewohner' });
        e.applyCultureLandbewohner();

        expect(e.cultureGrants['Beruf: Viehzüchter']).toEqual({ type: 'min', value: 2 });

        e.handleRaceChange();

        expect(e.culture).toBe('Mensch des 21. Jahrhunderts');
        expect(e.cultureGrants['Beruf: Viehzüchter']).toBeUndefined();
        expect(e.skills.find(s => s.name === 'Beruf: Viehzüchter')).toBeUndefined();

        e.handleCultureChange();

        expect(e.cultureGrants.Beruf).toEqual({ type: 'min', value: 1 });
        expect(e.cultureGrants.Bildung).toEqual({ type: 'min', value: 3 });
        expect(e.cultureGrants.Pilot).toEqual({ type: 'min', value: 3 });
        expect(e.skills.find(s => s.name === 'Beruf')).toMatchObject({ value: 3, badge: 'Rasse/Kultur' });
    });

    it('Kulturwechsel auf Bunkermensch setzt Techno automatisch und ersetzt alte Rassen-Grants', () => {
        const e = createEditor({ race: 'Barbar', culture: 'Bunkermensch' });
        e.applyRaceBarbar();

        expect(e.raceGrants['\u00dcberleben']).toEqual({ type: 'min', value: 1 });

        e.handleCultureChange();

        expect(e.race).toBe('Techno');
        expect(e.raceLockedByBunkermenschCulture).toBe(true);
        expect(e.raceGrants['\u00dcberleben']).toBeUndefined();
        expect(e.raceGrants.Bildung).toEqual({ type: 'min', value: 3 });
        expect(e.skills.find(s => s.name === 'Bildung')).toMatchObject({ value: 3, badge: 'Rasse/Kultur' });
        expect(e.raceGrants.Fahren).toEqual({ type: 'min', value: 2 });
        expect(e.raceGrants.Feuerwaffen).toEqual({ type: 'min', value: 2 });
        expect(e.cultureGrants.Bildung).toEqual({ type: 'min', value: 1 });
        expect(e.cultureGrants.Nahkampf).toEqual({ type: 'min', value: 1 });
        expect(e.cultureGrants.Feuerwaffen).toEqual({ type: 'min', value: 3 });
        expect(e.isRaceSelectable('Barbar')).toBe(false);
        expect(e.isRaceSelectable('Techno')).toBe(true);
    });

    it('Kulturwechsel weg von automatisch gesetztem Bunkermensch leert Techno und gibt Rassen frei', () => {
        const e = createEditor({ race: 'Barbar', culture: 'Bunkermensch' });
        e.applyRaceBarbar();
        e.handleCultureChange();

        expect(e.race).toBe('Techno');
        expect(e.raceLockedByBunkermenschCulture).toBe(true);

        e.culture = 'Landbewohner';
        e.handleCultureChange();

        expect(e.race).toBe('');
        expect(e.raceLockedByBunkermenschCulture).toBe(false);
        expect(e.raceGrants).toEqual({});
        expect(e.cultureGrants['Beruf: Viehzüchter']).toEqual({ type: 'min', value: 2 });
        expect(e.cultureGrants.Feuerwaffen).toBeUndefined();
        expect(e.skills.find(s => s.name === 'Feuerwaffen')).toBeUndefined();
        expect(e.isRaceSelectable('Barbar')).toBe(true);
        expect(e.isRaceSelectable('Guul')).toBe(true);
        expect(e.isRaceSelectable('Hydrit')).toBe(true);
        expect(e.isRaceSelectable('Techno')).toBe(true);
    });

    it('direkter Rassenwechsel bleibt bei aktivem Bunkermensch-Auto-Lock auf Techno beschraenkt', () => {
        const e = createEditor({ race: 'Techno', culture: 'Bunkermensch', raceLockedByBunkermenschCulture: true });
        e.applyRaceTechno();
        e.applyCultureBunkermensch();
        e._prevRace = 'Techno';
        const clearRace = vi.spyOn(e, 'clearRace');

        e.race = 'Barbar';
        e.handleRaceChange();

        expect(e.race).toBe('Techno');
        expect(clearRace).not.toHaveBeenCalled();
        expect(e.raceGrants.Bildung).toEqual({ type: 'min', value: 3 });
        expect(e.skills.find(s => s.name === 'Bildung')).toMatchObject({ value: 3, badge: 'Rasse/Kultur' });
        expect(e.raceGrants.Fahren).toEqual({ type: 'min', value: 2 });
        expect(e.cultureGrants.Bildung).toEqual({ type: 'min', value: 1 });
        expect(e.isRaceSelectable('Barbar')).toBe(false);
    });

    it('manuell gewaehlter Techno kann per Rassenwechsel aus Bunkermensch herauswechseln', () => {
        const e = createEditor({ race: 'Techno', culture: 'Bunkermensch' });
        e.applyRaceTechno();
        e.applyCultureBunkermensch();
        e._prevRace = 'Techno';

        e.race = 'Barbar';
        e.handleRaceChange();

        expect(e.race).toBe('Barbar');
        expect(e.culture).toBe('');
        expect(e.raceLockedByBunkermenschCulture).toBe(false);
        expect(e.raceGrants['\u00dcberleben']).toEqual({ type: 'min', value: 1 });
        expect(e.raceGrants.Fahren).toBeUndefined();
        expect(e.cultureGrants).toEqual({});
        expect(e.isRaceSelectable('Barbar')).toBe(true);
    });

    it('ignoriert doppelten Rassen-Handler-Lauf bei unveraenderter Rasse', () => {
        const e = createEditor({ race: 'Techno', culture: 'Bunkermensch' });
        e.applyRaceTechno();
        e.applyCultureBunkermensch();
        e._prevRace = 'Techno';
        const clearRace = vi.spyOn(e, 'clearRace');

        e.handleRaceChange();

        expect(clearRace).not.toHaveBeenCalled();
        expect(e.race).toBe('Techno');
        expect(e.raceGrants.Bildung).toEqual({ type: 'min', value: 3 });
        expect(e.skills.find(s => s.name === 'Bildung')).toMatchObject({ value: 3, badge: 'Rasse/Kultur' });
        expect(e.raceGrants.Fahren).toEqual({ type: 'min', value: 2 });
        expect(e.cultureGrants.Bildung).toEqual({ type: 'min', value: 1 });
    });

    it('Bunkermensch erhält Bildung, Nahkampf und den wählbaren Zusatzbonus', () => {
        const e = createEditor({ race: 'Techno', culture: 'Bunkermensch' });
        e.applyRaceTechno();
        e.applyCultureBunkermensch();

        expect(e.cultureGrants.Bildung).toEqual({ type: 'min', value: 1 });
        expect(e.cultureGrants.Nahkampf).toEqual({ type: 'min', value: 1 });
        expect(e.cultureGrants.Feuerwaffen).toEqual({ type: 'min', value: 3 });
        expect(e.bunkermenschBonusSkill).toBe('Feuerwaffen');
        expect(e.skills.find(s => s.name === 'Feuerwaffen')).toMatchObject({ value: 3, badge: 'Rasse/Kultur' });
    });

    it('Bunkermensch-Zusatzbonus senkt den alten Bonus wieder auf den Techno-Rassenwert', () => {
        const e = createEditor({ race: 'Techno', culture: 'Bunkermensch' });
        e.applyRaceTechno();
        e.applyCultureBunkermensch();

        expect(e.skills.find(s => s.name === 'Feuerwaffen')).toMatchObject({ value: 3, badge: 'Rasse/Kultur' });

        e.setBunkermenschBonusSkill('Pilot');

        expect(e.cultureGrants.Feuerwaffen).toBeUndefined();
        expect(e.skills.find(s => s.name === 'Feuerwaffen')).toMatchObject({ value: 2, badge: 'Rasse' });
    });

    it('Bunkermensch-Zusatzbonus ersetzt alte Optionen und addiert auf Techno-Poolpunkte bis zum Maximum', () => {
        const e = createEditor({ race: 'Techno', culture: 'Bunkermensch' });
        e.applyRaceTechno();
        e.applyCultureBunkermensch();

        e.setTechnoSkillPoints('Feuerwaffen', 4);

        expect(e.cultureGrants.Feuerwaffen).toEqual({ type: 'min', value: 4 });
        expect(e.getGrant('Feuerwaffen')).toEqual({ type: 'min', value: 4 });

        e.setBunkermenschBonusSkill('Pilot');

        expect(e.cultureGrants.Feuerwaffen).toBeUndefined();
        expect(e.raceGrants.Feuerwaffen).toEqual({ type: 'min', value: 4 });
        expect(e.cultureGrants.Pilot).toEqual({ type: 'min', value: 3 });
        expect(e.skills.find(s => s.name === 'Pilot')).toMatchObject({ value: 3, badge: 'Rasse/Kultur' });
    });

    it('Mensch des 21. Jahrhunderts setzt Beruf und zwei unterschiedliche Zusatzboni', () => {
        const e = createEditor({ race: 'Präkristofluu', culture: 'Mensch des 21. Jahrhunderts' });
        e.applyRacePraekristofluu();
        e.applyCultureMensch21();

        expect(e.cultureGrants.Beruf).toEqual({ type: 'min', value: 1 });
        expect(e.cultureGrants.Bildung).toEqual({ type: 'min', value: 3 });
        expect(e.cultureGrants.Pilot).toEqual({ type: 'min', value: 3 });
        expect(e.mensch21FirstBonusSkill).toBe('Bildung');
        expect(e.mensch21SecondBonusSkill).toBe('Pilot');
        expect(e.skills.find(s => s.name === 'Bildung')).toMatchObject({ value: 3, badge: 'Rasse/Kultur' });

        e.setMensch21FirstBonusSkill('Techniker');

        expect(e.cultureGrants.Bildung).toBeUndefined();
        expect(e.raceGrants.Bildung).toEqual({ type: 'min', value: 2 });
        expect(e.skills.find(s => s.name === 'Bildung')).toMatchObject({ value: 2, badge: 'Rasse' });
        expect(e.cultureGrants.Techniker).toEqual({ type: 'min', value: 3 });
        expect(e.cultureGrants.Pilot).toEqual({ type: 'min', value: 3 });

        e.setMensch21SecondBonusSkill('Techniker');

        expect(e.mensch21FirstBonusSkill).toBe('Techniker');
        expect(e.mensch21SecondBonusSkill).toBe('Bildung');
        expect(e.cultureGrants.Pilot).toBeUndefined();
        expect(e.cultureGrants.Bildung).toEqual({ type: 'min', value: 3 });
    });

    it('Mensch-des-21-Jahrhunderts-Boni addieren auf Präkristofluu-Poolpunkte bis zum Maximum', () => {
        const e = createEditor({ race: 'Präkristofluu', culture: 'Mensch des 21. Jahrhunderts' });
        e.applyRacePraekristofluu();
        e.applyCultureMensch21();

        e.setPraekristofluuSkillPoints('Bildung', 4);

        expect(e.cultureGrants.Bildung).toEqual({ type: 'min', value: 4 });
        expect(e.getGrant('Bildung')).toEqual({ type: 'min', value: 4 });
    });

    it('Landbewohner erhält einen steigerbaren Beruf-Wahlbonus und Kunde Wetter', () => {
        const e = createEditor();
        e.applyCultureLandbewohner();

        expect(e.cultureGrants['Beruf: Viehzüchter']).toEqual({ type: 'min', value: 2 });
        expect(e.cultureGrants['Beruf: Landwirt']).toBeUndefined();
        expect(e.cultureGrants['Kunde: Wetter']).toEqual({ type: 'min', value: 1 });
        expect(e.landbewohnerProfessionSkill).toBe('Beruf: Viehzüchter');

        const profession = e.skills.find(s => s.name === 'Beruf: Viehzüchter');
        expect(profession).toMatchObject({ value: 2, badge: 'Kultur', valueDisabled: false });
    });

    it('Landbewohner-Wahlbonus ersetzt die vorherige Berufsoption', () => {
        const e = createEditor();
        e.applyCultureLandbewohner();

        e.setLandbewohnerProfessionSkill('Beruf: Landwirt');

        expect(e.cultureGrants['Beruf: Viehzüchter']).toBeUndefined();
        expect(e.skills.find(s => s.name === 'Beruf: Viehzüchter')).toBeUndefined();
        expect(e.cultureGrants['Beruf: Landwirt']).toEqual({ type: 'min', value: 2 });
        expect(e.cultureGrants['Kunde: Wetter']).toEqual({ type: 'min', value: 1 });
        expect(e.landbewohnerProfessionSkill).toBe('Beruf: Landwirt');
    });

    it('Stadtbewohner erhält Unterhaltungs-Skill und Beruf/Kunde', () => {
        const e = createEditor();
        e.applyCultureStadtbewohner();
        expect(e.cultureGrants).toHaveProperty('Unterhalten');
        expect(e.cultureGrants).toHaveProperty('Beruf');
        expect(e.cultureGrants).toHaveProperty('Kunde');
    });

    it('Meeresbewohner erhält Athletik und die Standard-Wahlboni', () => {
        const e = createEditor();
        e.applyCultureMeeresbewohner();

        expect(e.cultureGrants.Athletik).toEqual({ type: 'min', value: 1 });
        expect(e.cultureGrants['Beruf: Farmer']).toEqual({ type: 'min', value: 1 });
        expect(e.cultureGrants.Wissenschaftler).toEqual({ type: 'min', value: 1 });
        expect(e.seaProfessionSkill).toBe('Beruf: Farmer');
        expect(e.seaKnowledgeOrCombatSkill).toBe('Wissenschaftler');
    });

    it('Meeresbewohner-Wahlboni ersetzen den vorherigen Kulturbonus', () => {
        const e = createEditor();
        e.applyCultureMeeresbewohner();

        e.setSeaProfessionSkill('Beruf: Künstler');
        e.setSeaKnowledgeOrCombatSkill('Nahkampf');

        expect(e.cultureGrants['Beruf: Farmer']).toBeUndefined();
        expect(e.cultureGrants.Wissenschaftler).toBeUndefined();
        expect(e.cultureGrants['Beruf: Künstler']).toEqual({ type: 'min', value: 1 });
        expect(e.cultureGrants.Nahkampf).toEqual({ type: 'min', value: 1 });
        expect(e.skills.find(s => s.name === 'Beruf: Farmer')).toBeUndefined();
        expect(e.skills.find(s => s.name === 'Wissenschaftler')).toBeUndefined();
    });

    it('Wahlboni ersetzen alte Optionen auch wenn x-model bereits den neuen Wert gesetzt hat', () => {
        const e = createEditor();
        e.applyRaceBarbar();
        e.applyCultureStadtbewohner();
        e.applyCultureMeeresbewohner();

        e.barbarCombatSkill = 'Fernkampf';
        e.setBarbarCombatSkill(e.barbarCombatSkill);
        e.citySkill = 'Sprachen';
        e.setCitySkill(e.citySkill);
        e.seaProfessionSkill = 'Beruf: Künstler';
        e.setSeaProfessionSkill(e.seaProfessionSkill);
        e.seaKnowledgeOrCombatSkill = 'Nahkampf';
        e.setSeaKnowledgeOrCombatSkill(e.seaKnowledgeOrCombatSkill);

        expect(e.raceGrants.Nahkampf).toBeUndefined();
        expect(e.raceGrants.Fernkampf).toEqual({ type: 'min', value: 1 });
        expect(e.cultureGrants.Unterhalten).toBeUndefined();
        expect(e.cultureGrants.Sprachen).toEqual({ type: 'min', value: 1 });
        expect(e.cultureGrants['Beruf: Farmer']).toBeUndefined();
        expect(e.cultureGrants['Beruf: Künstler']).toEqual({ type: 'min', value: 1 });
        expect(e.cultureGrants.Wissenschaftler).toBeUndefined();
        expect(e.cultureGrants.Nahkampf).toEqual({ type: 'min', value: 1 });
    });

    it('kombiniert überlappende Rassen- und Kultur-Grants über den höchsten Mindestwert', () => {
        const e = createEditor();
        e.applyRaceHydrit();
        e.applyCultureMeeresbewohner();

        const athletik = e.skills.find(s => s.name === 'Athletik');

        expect(e.getGrant('Athletik')).toEqual({ type: 'min', value: 2 });
        expect(athletik).toMatchObject({ value: 2, badge: 'Rasse/Kultur' });
        expect(e.fpUsed()).toBe(0);
    });

    it('Kulturwechsel entfernt Meeresbewohner-Boni ohne überlappende Rassen-Grants zu löschen', () => {
        const e = createEditor();
        e.applyRaceHydrit();
        e.applyCultureMeeresbewohner();
        e.clearCulture();

        const athletik = e.skills.find(s => s.name === 'Athletik');

        expect(athletik).toMatchObject({ value: 2, badge: 'Rasse' });
        expect(e.cultureGrants).toEqual({});
        expect(e.skills.find(s => s.name === 'Beruf: Farmer')).toBeUndefined();
        expect(e.skills.find(s => s.name === 'Wissenschaftler')).toBeUndefined();
    });

    it('Nomade setzt Ueberleben und die Standard-Wahlboni', () => {
        const e = createEditor({ race: 'Barbar', culture: 'Nomade' });
        e.applyRaceBarbar();
        e.applyCultureNomade();

        expect(e.cultureGrants['\u00dcberleben']).toEqual({ type: 'min', value: 1 });
        expect(e.cultureGrants.Nahkampf).toEqual({ type: 'min', value: 1 });
        expect(e.cultureGrants.Reiten).toEqual({ type: 'min', value: 1 });
        expect(e.nomadeCombatSkill).toBe('Nahkampf');
        expect(e.nomadeMovementSkill).toBe('Reiten');
        expect(e.skills.find(s => s.name === '\u00dcberleben')).toMatchObject({ value: 1, badge: 'Rasse/Kultur' });
    });

    it('Nomade-Wahlboni ersetzen alte Optionen ohne Rassen-Grants zu entfernen', () => {
        const e = createEditor({ race: 'Barbar', culture: 'Nomade' });
        e.applyRaceBarbar();
        e.applyCultureNomade();

        e.setNomadeCombatSkill('Fernkampf');
        e.setNomadeMovementSkill('Athletik');

        expect(e.cultureGrants.Nahkampf).toBeUndefined();
        expect(e.raceGrants.Nahkampf).toEqual({ type: 'min', value: 1 });
        expect(e.cultureGrants.Fernkampf).toEqual({ type: 'min', value: 1 });
        expect(e.cultureGrants.Reiten).toBeUndefined();
        expect(e.cultureGrants.Athletik).toEqual({ type: 'min', value: 1 });
        expect(e.skills.find(s => s.name === 'Reiten')).toBeUndefined();
        expect(e.skills.find(s => s.name === 'Nahkampf')).toMatchObject({ value: 1, badge: 'Rasse' });
    });

    it('Ruinenbewohner setzt Diebeskunst, Heimlichkeit und den Standard-Wahlbonus', () => {
        const e = createEditor({ race: 'Barbar', culture: 'Ruinenbewohner' });
        e.applyRaceBarbar();
        e.applyCultureRuinenbewohner();

        expect(e.cultureGrants.Diebeskunst).toEqual({ type: 'min', value: 1 });
        expect(e.cultureGrants.Heimlichkeit).toEqual({ type: 'min', value: 1 });
        expect(e.cultureGrants.Nahkampf).toEqual({ type: 'min', value: 1 });
        expect(e.ruinenbewohnerBonusSkill).toBe('Nahkampf');
        expect(e.skills.find(s => s.name === 'Nahkampf')).toMatchObject({ value: 1, badge: 'Rasse/Kultur' });
        expect(e.skills.find(s => s.name === 'Fernwaffen')).toBeUndefined();
    });

    it('Ruinenbewohner-Wahlbonus ersetzt alte Optionen ohne Rassen-Grants zu entfernen', () => {
        const e = createEditor({ race: 'Barbar', culture: 'Ruinenbewohner' });
        e.applyRaceBarbar();
        e.applyCultureRuinenbewohner();

        e.setRuinenbewohnerBonusSkill('Fernkampf');

        expect(e.cultureGrants.Nahkampf).toBeUndefined();
        expect(e.raceGrants.Nahkampf).toEqual({ type: 'min', value: 1 });
        expect(e.skills.find(s => s.name === 'Nahkampf')).toMatchObject({ value: 1, badge: 'Rasse' });
        expect(e.cultureGrants.Fernkampf).toEqual({ type: 'min', value: 1 });

        e.setRuinenbewohnerBonusSkill('Athletik');

        expect(e.cultureGrants.Fernkampf).toBeUndefined();
        expect(e.skills.find(s => s.name === 'Fernkampf')).toBeUndefined();
        expect(e.cultureGrants.Athletik).toEqual({ type: 'min', value: 1 });

        e.setRuinenbewohnerBonusSkill('Kunde');

        expect(e.cultureGrants.Athletik).toBeUndefined();
        expect(e.skills.find(s => s.name === 'Athletik')).toBeUndefined();
        expect(e.cultureGrants.Kunde).toEqual({ type: 'min', value: 1 });
        expect(e.ruinenbewohnerBonusSkill).toBe('Kunde');
    });

    it('Untergrundbewohner setzt Athletik, Bergmann und Ueberleben', () => {
        const e = createEditor({ race: 'Barbar', culture: 'Untergrundbewohner' });
        e.applyRaceBarbar();
        e.applyCultureUntergrundbewohner();

        expect(e.cultureGrants.Athletik).toEqual({ type: 'min', value: 1 });
        expect(e.cultureGrants['Beruf: Bergmann']).toEqual({ type: 'min', value: 1 });
        expect(e.cultureGrants['\u00dcberleben']).toEqual({ type: 'min', value: 1 });
        expect(e.skills.find(s => s.name === '\u00dcberleben')).toMatchObject({ value: 1, badge: 'Rasse/Kultur' });
        expect(e.skills.find(s => s.name === 'Beruf: Bergmann')).toMatchObject({ value: 1, badge: 'Kultur' });
    });

    it('Disuuslachter (Nordmann) ist nur fuer Barbaren auswaehlbar', () => {
        const barbar = createEditor({ race: 'Barbar' });
        const guul = createEditor({ race: 'Guul' });
        const nosfera = createEditor({ race: 'Nosfera' });

        expect(barbar.isCultureSelectable('Disuuslachter (Nordmann)')).toBe(true);
        expect(guul.isCultureSelectable('Disuuslachter (Nordmann)')).toBe(false);
        expect(nosfera.isCultureSelectable('Disuuslachter (Nordmann)')).toBe(false);
        expect(nosfera.isCultureSelectable('Nomade')).toBe(true);

        const invalid = createEditor({ race: 'Nosfera', culture: 'Disuuslachter (Nordmann)' });
        invalid.applyCultureDisuuslachter();
        invalid.handleRaceChange();

        expect(invalid.culture).toBe('');
        expect(invalid.cultureGrants).toEqual({});
        expect(invalid.skills.find(s => s.name === 'Beruf: Seemann')).toBeUndefined();
    });

    it('Disuuslachter setzt Nahkampf, Ueberleben und Seemann ohne Rassenboni zu verdoppeln', () => {
        const e = createEditor({ race: 'Barbar', culture: 'Disuuslachter (Nordmann)' });
        e.applyRaceBarbar();
        e.applyCultureDisuuslachter();

        expect(e.cultureGrants.Nahkampf).toEqual({ type: 'min', value: 1 });
        expect(e.cultureGrants['\u00dcberleben']).toEqual({ type: 'min', value: 1 });
        expect(e.cultureGrants['Beruf: Seemann']).toEqual({ type: 'min', value: 1 });
        expect(e.getGrant('Nahkampf')).toEqual({ type: 'min', value: 1 });
        expect(e.getGrant('\u00dcberleben')).toEqual({ type: 'min', value: 1 });
        expect(e.skills.find(s => s.name === 'Nahkampf')).toMatchObject({ value: 1, badge: 'Rasse/Kultur' });
        expect(e.skills.find(s => s.name === '\u00dcberleben')).toMatchObject({ value: 1, badge: 'Rasse/Kultur' });
        expect(e.skills.find(s => s.name === 'Beruf: Seemann')).toMatchObject({ value: 1, badge: 'Kultur' });
        expect(e.fpUsed()).toBe(0);

        e.clearCulture();

        expect(e.cultureGrants).toEqual({});
        expect(e.raceGrants.Nahkampf).toEqual({ type: 'min', value: 1 });
        expect(e.raceGrants['\u00dcberleben']).toEqual({ type: 'min', value: 1 });
        expect(e.skills.find(s => s.name === 'Beruf: Seemann')).toBeUndefined();
        expect(e.skills.find(s => s.name === 'Nahkampf')).toMatchObject({ value: 1, badge: 'Rasse' });
    });

    it('Volk der 13 Inseln ist nur fuer Barbaren auswaehlbar', () => {
        const barbar = createEditor({ race: 'Barbar' });
        const guul = createEditor({ race: 'Guul' });

        expect(barbar.isCultureSelectable('Volk der 13 Inseln')).toBe(true);
        expect(guul.isCultureSelectable('Volk der 13 Inseln')).toBe(false);
        expect(guul.isCultureSelectable('Nomade')).toBe(true);
        expect(guul.isCultureSelectable('Ruinenbewohner')).toBe(true);
        expect(guul.isCultureSelectable('Untergrundbewohner')).toBe(true);

        const invalid = createEditor({ race: 'Guul', culture: 'Volk der 13 Inseln', gender: 'weiblich' });
        invalid.applyCultureVolkDer13Inseln();
        invalid.handleRaceChange();

        expect(invalid.culture).toBe('');
        expect(invalid.cultureGrants).toEqual({});
        expect(invalid.selectedAdvantages).not.toContain('Psychische Kraft');
    });

    it('Volk der 13 Inseln setzt Athletik, Ueberleben und Beruf-Wahlbonus', () => {
        const e = createEditor({ race: 'Barbar', culture: 'Volk der 13 Inseln', gender: 'maennlich' });
        e.applyRaceBarbar();
        e.applyCultureVolkDer13Inseln();

        expect(e.cultureGrants.Athletik).toEqual({ type: 'min', value: 1 });
        expect(e.cultureGrants['\u00dcberleben']).toEqual({ type: 'min', value: 1 });
        expect(e.cultureGrants['Beruf: Bauer']).toEqual({ type: 'min', value: 1 });
        expect(e.cultureLocked.advantages).toEqual([]);
        expect(e.skills.find(s => s.name === '\u00dcberleben')).toMatchObject({ value: 1, badge: 'Rasse/Kultur' });

        e.setVolkDer13InselnProfessionSkill('Beruf: Fischer');

        expect(e.cultureGrants['Beruf: Bauer']).toBeUndefined();
        expect(e.skills.find(s => s.name === 'Beruf: Bauer')).toBeUndefined();
        expect(e.cultureGrants['Beruf: Fischer']).toEqual({ type: 'min', value: 1 });
    });

    it('weibliches Volk der 13 Inseln erzwingt Psychische Kraft ohne freie Vorteile zu verbrauchen', () => {
        const e = createEditor({ race: 'Barbar', culture: 'Volk der 13 Inseln', gender: 'weiblich' });
        e.applyCultureVolkDer13Inseln();

        expect(e.cultureLocked.advantages).toEqual(['Psychische Kraft']);
        expect(e.selectedAdvantages).toContain('Psychische Kraft');
        expect(e.chosenAdvantagesCount()).toBe(0);
        expect(e.freeAdvantagePoints()).toBe(2);
        expect(e.selectedDisabledAdvantages()).toEqual(['Z\u00e4h', 'Psychische Kraft']);

        e.gender = 'maennlich';
        e.handleGenderChange();

        expect(e.cultureLocked.advantages).toEqual([]);
        expect(e.selectedAdvantages).not.toContain('Psychische Kraft');
    });

    it('Kultur-Pflichtvorteil erhaelt bereits frei gewaehlte Psychische Kraft', () => {
        const e = createEditor({ race: 'Barbar', culture: 'Volk der 13 Inseln', gender: 'weiblich' });
        e.selectedAdvantages = ['Z\u00e4h', 'Psychische Kraft'];
        e.applyCultureVolkDer13Inseln();

        e.gender = 'maennlich';
        e.handleGenderChange();

        expect(e.selectedAdvantages).toContain('Psychische Kraft');
        expect(e.chosenAdvantagesCount()).toBe(1);
        expect(e.freeAdvantagePoints()).toBe(1);
    });
});

describe('charEditor – Vorteile/Nachteile', () => {
    it('Zäh ist immer aktiv und kann nicht entfernt werden', () => {
        const e = createEditor();
        e.selectedAdvantages = ['Schnell'];
        e.enforceAdvantageLimit();
        expect(e.selectedAdvantages).toContain('Zäh');
    });

    it('weist Vorteile bei identischem Ergebnis nicht erneut zu', () => {
        const e = createEditor();
        const selectedAdvantages = e.selectedAdvantages;

        e.enforceAdvantageLimit();

        expect(e.selectedAdvantages).toBe(selectedAdvantages);
    });

    it('weist Kulturvorteile bei leerer Änderung nicht erneut zu', () => {
        const e = createEditor();
        const selectedAdvantages = e.selectedAdvantages;

        e.handleGenderChange();

        expect(e.selectedAdvantages).toBe(selectedAdvantages);
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

    it('sperrt weitere Vorteile, sobald zwei frei gewählt sind', () => {
        const e = createEditor();
        e.selectedAdvantages = ['Zäh', 'Schnell', 'Kampfreflexe'];

        expect(e.isAdvantageDisabled('Nachtsicht')).toBe(true);
        expect(e.isAdvantageDisabled('Schnell')).toBe(false);
    });

    it('meldet deaktivierte ausgewählte Vorteile für Hidden Inputs', () => {
        const e = createEditor();
        e.selectedAdvantages = ['Zäh', 'Schnell'];

        expect(e.selectedDisabledAdvantages()).toEqual(['Zäh']);
    });

    it('meldet ausgewählte Pflichtnachteile für Hidden Inputs', () => {
        const e = createEditor();
        e.applyRaceGuul();

        expect(e.selectedLockedDisadvantages()).toEqual(['Primitiv', 'Gejagt']);
    });
});

describe('charEditor – W66-Regeln', () => {
    it('kennt W66-Bereiche und neue Nachteile', () => {
        const e = createEditor();

        expect(e.advantageRollLabel('Gestaltwandler')).toBe('13');
        expect(e.advantageRollLabel('Panzerung')).toBe('46');
        expect(e.disadvantageRollLabel('Taratzenfutter')).toBe('54-63');
        expect(e.disadvantageRollLabel('Verpflichtung')).toBe('65');
        expect(e.disadvantageRollLabel('Verwundbarkeit')).toBe('66');
    });

    it('berechnet Gestaltwandler als drei Vorteile und sperrt ihn bei nur zwei freien Punkten', () => {
        const e = createEditor();

        expect(e.advantageCost('Gestaltwandler')).toBe(3);
        expect(e.isAdvantageDisabled('Gestaltwandler')).toBe(true);

        e.selectedAdvantages = ['Zäh', 'Gestaltwandler'];
        e.enforceAdvantageLimit();

        expect(e.selectedAdvantages).toEqual(['Zäh']);
    });

    it('zaehlt Panzerung mehrfach und begrenzt die Kosten auf das Budget', () => {
        const e = createEditor();
        e.selectedAdvantages = ['Zäh', 'Panzerung'];

        e.setAdvantageCount('Panzerung', 2);

        expect(e.advantageCounts.Panzerung).toBe(2);
        expect(e.chosenAdvantagesCount()).toBe(2);
        expect(e.freeAdvantagePoints()).toBe(0);

        e.setAdvantageCount('Panzerung', 3);

        expect(e.advantageCounts.Panzerung).toBe(2);
        expect(e.chosenAdvantagesCount()).toBe(2);
    });

    it('wuerfelt Vorteile nach W66 und uebernimmt eintragbare Ergebnisse', () => {
        const e = createEditor();
        e.rollD6 = vi.fn()
            .mockReturnValueOnce(4)
            .mockReturnValueOnce(6)
            .mockReturnValueOnce(4)
            .mockReturnValueOnce(6);

        const first = e.rollSpecial('advantage');
        const second = e.rollSpecial('advantage');

        expect(first).toMatchObject({ value: 46, name: 'Panzerung', applied: true });
        expect(second).toMatchObject({ value: 46, name: 'Panzerung', applied: true });
        expect(e.selectedAdvantages).toContain('Panzerung');
        expect(e.advantageCounts.Panzerung).toBe(2);
        expect(e.lastRoll.name).toBe('Panzerung');
    });

    it('wuerfelt Nachteile nach W66 und uebernimmt neue Tabelleneintraege', () => {
        const e = createEditor();
        e.rollD6 = vi.fn().mockReturnValueOnce(6).mockReturnValueOnce(5);

        const result = e.rollSpecial('disadvantage');

        expect(result).toMatchObject({ value: 65, name: 'Verpflichtung', applied: true });
        expect(e.selectedDisadvantages).toContain('Verpflichtung');
    });

    it('verlangt Detailangaben nur fuer frei gewaehlte detailpflichtige Eintraege', () => {
        const e = createEditor();
        e.selectedAdvantages = ['Zäh', 'Tiergefährte'];
        e.selectedDisadvantages = ['Aberglaeubisch'];

        expect(e.requiredSpecialDetailsFilled()).toBe(false);

        e.advantageDetails.Tiergefährte = 'Schwarzer Januskater';
        e.selectedDisadvantages = ['Abergläubisch'];
        e.disadvantageDetails.Abergläubisch = 'Salz, Omen, dreimal klopfen';

        expect(e.requiredSpecialDetailsFilled()).toBe(true);

        e.applyRaceGuul();

        expect(e.disadvantageRequiresDetail('Gejagt')).toBe(false);
    });
});

describe('charEditor – Submit-Mirroring', () => {
    it('spiegelt Basisfelder erst nach Freischaltung', () => {
        const e = createEditor();

        expect(e.shouldMirrorBaseFields()).toBe(false);

        e.advancedUnlocked = true;

        expect(e.shouldMirrorBaseFields()).toBe(true);
    });

    it('sendet den Portrait-Preview nur wenn einer vorhanden ist', () => {
        const e = createEditor();

        expect(e.shouldSubmitPortraitPreview()).toBe(false);

        e.portraitPreview = 'data:image/png;base64,abc=';

        expect(e.shouldSubmitPortraitPreview()).toBe(false);

        e.advancedUnlocked = true;

        expect(e.shouldSubmitPortraitPreview()).toBe(true);
    });

    it('spiegelt nur wirklich gesperrte Skill-Werte', () => {
        const e = createEditor();
        const freeSkill = { name: 'Athletik', value: 2, nameDisabled: false, valueDisabled: false };
        const lockedNameSkill = { name: 'Nahkampf', value: 1, nameDisabled: true, valueDisabled: false };
        const exactGrantSkill = { name: 'Beruf: Landwirt', value: 2, nameDisabled: true, valueDisabled: true };

        e.cultureGrants = { 'Beruf: Viehzüchter': { type: 'exact', value: 2 } };
        const exactGrantWithoutFlag = { name: 'Beruf: Viehzüchter', value: 2, nameDisabled: true, valueDisabled: false };

        e.raceGrants = { Intuition: { type: 'min', value: 1 } };
        const uiDisabledByExclusivity = { name: 'Bildung', value: 1, nameDisabled: false, valueDisabled: false };

        expect(e.isSkillDisabled(uiDisabledByExclusivity)).toBe(true);
        expect(e.shouldMirrorSkillValue(uiDisabledByExclusivity)).toBe(false);
        expect(e.shouldMirrorSkillName(freeSkill)).toBe(false);
        expect(e.shouldMirrorSkillValue(freeSkill)).toBe(false);
        expect(e.shouldMirrorSkillName(lockedNameSkill)).toBe(true);
        expect(e.shouldMirrorSkillValue(lockedNameSkill)).toBe(false);
        expect(e.shouldMirrorSkillName(exactGrantSkill)).toBe(true);
        expect(e.shouldMirrorSkillValue(exactGrantSkill)).toBe(true);
        expect(e.shouldMirrorSkillValue(exactGrantWithoutFlag)).toBe(true);
    });
});

describe('charEditor – Computed Properties', () => {
    it('basicsFilled true wenn alle Grunddaten gesetzt', () => {
        const e = createEditor({
            playerName: 'Test',
            characterName: 'Held',
            gender: 'weiblich',
            race: 'Barbar',
            culture: 'Landbewohner',
        });
        expect(e.basicsFilled()).toBeTruthy();
    });

    it('basicsFilled false wenn Angabe fehlt', () => {
        const e = createEditor({
            playerName: 'Test',
            characterName: '',
            gender: 'weiblich',
            race: 'Barbar',
            culture: 'Landbewohner',
        });
        expect(e.basicsFilled()).toBeFalsy();
    });

    it('basicsFilled false ohne Geschlecht', () => {
        const e = createEditor({
            playerName: 'Test',
            characterName: 'Held',
            race: 'Barbar',
            culture: 'Landbewohner',
        });
        expect(e.basicsFilled()).toBeFalsy();
    });

    it('apUsed zählt nur positive Attributwerte', () => {
        const e = createEditor();
        e.attributes = { st: 1, ge: -1, ro: 0, wi: 1, wa: 0, in: 0, au: 0 };
        expect(e.apUsed()).toBe(2); // 1 + 0 + 0 + 1 + 0 + 0 + 0
    });

    it('fpUsed ignoriert exact-Grants', () => {
        const e = createEditor();
        e.raceGrants = { FixSkill: { type: 'exact', value: 3 } };
        e.skills = [
            { name: 'FixSkill', value: 3 },
            { name: 'Frei', value: 2 },
        ];
        expect(e.fpUsed()).toBe(2);
    });
});
