/**
 * Tests für die Char-Editor Alpine-Komponente.
 *
 * char-editor.js registriert Alpine.data() sofort, wenn window.Alpine bereits
 * verfügbar ist. Andernfalls wartet das Modul auf 'alpine:init'.
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

    it('Techno erhält kostenlose Attributsmodifikatoren, Pflichtmerkmale und 12 Rassen-Fertigkeitspunkte', () => {
        const e = createEditor({ race: 'Techno' });
        e.applyRaceTechno();

        expect(e.attributes).toMatchObject({ st: -1, ro: -1, in: 1 });
        expect(e.apUsed()).toBe(0);
        expect(e.apRemaining()).toBe(2);
        expect(e.getAttributeMin('in')).toBe(0);
        expect(e.getAttributeMax('in')).toBe(2);
        expect(e.technoPoolUsed()).toBe(12);
        expect(e.technoSkillPoolComplete()).toBe(true);
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
        expect(e.isCultureSelectable('Meeresbewohner')).toBe(true);
        expect(e.isCultureSelectable('Nomade')).toBe(true);
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
        expect(e.skills.find(s => s.name === 'Beruf: Landwirt')).toBeUndefined();
    });

    it('Rassenwechsel zu Hydrit setzt Kultur auf Meeresbewohner und ersetzt alte Kultur-Grants', () => {
        const e = createEditor({ race: 'Hydrit', culture: 'Landbewohner' });
        e.applyCultureLandbewohner();

        expect(e.cultureGrants['Beruf: Landwirt']).toEqual({ type: 'exact', value: 2 });

        e.handleRaceChange();

        expect(e.culture).toBe('Meeresbewohner');
        expect(e.cultureGrants['Beruf: Landwirt']).toBeUndefined();
        expect(e.cultureGrants['Beruf: Farmer']).toBeUndefined();
        expect(e.skills.find(s => s.name === 'Beruf: Landwirt')).toBeUndefined();

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

    it('Techno erzwingt Bunkermensch und Bunkermensch sperrt Rassen auf Techno', () => {
        const techno = createEditor({ race: 'Techno' });

        expect(techno.allowedCulturesForRace()).toEqual(['Bunkermensch']);
        expect(techno.isCultureSelectable('Bunkermensch')).toBe(true);
        expect(techno.isCultureSelectable('Landbewohner')).toBe(false);
        expect(techno.isCultureSelectable('Meeresbewohner')).toBe(false);

        const barbar = createEditor({ race: 'Barbar' });

        expect(barbar.isCultureSelectable('Landbewohner')).toBe(true);
        expect(barbar.isCultureSelectable('Stadtbewohner')).toBe(true);
        expect(barbar.isCultureSelectable('Meeresbewohner')).toBe(true);
        expect(barbar.isCultureSelectable('Nomade')).toBe(true);
        expect(barbar.isCultureSelectable('Volk der 13 Inseln')).toBe(true);
        expect(barbar.isCultureSelectable('Bunkermensch')).toBe(true);

        const bunker = createEditor({ race: 'Techno', culture: 'Bunkermensch', raceLockedByBunkermenschCulture: true });

        expect(bunker.isRaceSelectable('Barbar')).toBe(false);
        expect(bunker.isRaceSelectable('Guul')).toBe(false);
        expect(bunker.isRaceSelectable('Hydrit')).toBe(false);
        expect(bunker.isRaceSelectable('Techno')).toBe(true);
        expect(bunker.isCultureSelectable('Landbewohner')).toBe(true);
        expect(bunker.isCultureSelectable('Volk der 13 Inseln')).toBe(false);
    });

    it('Rassenwechsel zu Techno setzt Kultur auf Bunkermensch und ersetzt alte Kultur-Grants', () => {
        const e = createEditor({ race: 'Techno', culture: 'Landbewohner' });
        e.applyCultureLandbewohner();

        expect(e.cultureGrants['Beruf: Landwirt']).toEqual({ type: 'exact', value: 2 });

        e.handleRaceChange();

        expect(e.culture).toBe('Bunkermensch');
        expect(e.raceLockedByBunkermenschCulture).toBe(false);
        expect(e.cultureGrants['Beruf: Landwirt']).toBeUndefined();
        expect(e.skills.find(s => s.name === 'Beruf: Landwirt')).toBeUndefined();

        e.handleCultureChange();

        expect(e.cultureGrants.Bildung).toEqual({ type: 'min', value: 1 });
        expect(e.cultureGrants.Nahkampf).toEqual({ type: 'min', value: 1 });
        expect(e.cultureGrants.Feuerwaffen).toEqual({ type: 'min', value: 3 });
    });

    it('Kulturwechsel auf Bunkermensch setzt Techno automatisch und ersetzt alte Rassen-Grants', () => {
        const e = createEditor({ race: 'Barbar', culture: 'Bunkermensch' });
        e.applyRaceBarbar();

        expect(e.raceGrants['\u00dcberleben']).toEqual({ type: 'min', value: 1 });

        e.handleCultureChange();

        expect(e.race).toBe('Techno');
        expect(e.raceLockedByBunkermenschCulture).toBe(true);
        expect(e.raceGrants['\u00dcberleben']).toBeUndefined();
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
        expect(e.cultureGrants['Beruf: Landwirt']).toEqual({ type: 'exact', value: 2 });
        expect(e.cultureGrants.Feuerwaffen).toBeUndefined();
        expect(e.skills.find(s => s.name === 'Feuerwaffen')).toBeUndefined();
        expect(e.isRaceSelectable('Barbar')).toBe(true);
        expect(e.isRaceSelectable('Guul')).toBe(true);
        expect(e.isRaceSelectable('Hydrit')).toBe(true);
        expect(e.isRaceSelectable('Techno')).toBe(true);
    });

    it('direkter Rassenwechsel bleibt bei Bunkermensch auf Techno beschraenkt', () => {
        const e = createEditor({ race: 'Techno', culture: 'Bunkermensch' });
        e.applyRaceTechno();
        e.applyCultureBunkermensch();
        e._prevRace = 'Techno';
        const clearRace = vi.spyOn(e, 'clearRace');

        e.race = 'Barbar';
        e.handleRaceChange();

        expect(e.race).toBe('Techno');
        expect(clearRace).not.toHaveBeenCalled();
        expect(e.raceGrants.Fahren).toEqual({ type: 'min', value: 2 });
        expect(e.cultureGrants.Bildung).toEqual({ type: 'min', value: 1 });
        expect(e.isRaceSelectable('Barbar')).toBe(false);
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

    it('Volk der 13 Inseln ist nur fuer Barbaren auswaehlbar', () => {
        const barbar = createEditor({ race: 'Barbar' });
        const guul = createEditor({ race: 'Guul' });

        expect(barbar.isCultureSelectable('Volk der 13 Inseln')).toBe(true);
        expect(guul.isCultureSelectable('Volk der 13 Inseln')).toBe(false);
        expect(guul.isCultureSelectable('Nomade')).toBe(true);

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
