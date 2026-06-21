const RACE_DESCRIPTIONS = {
    Barbar: 'Im 26. Jahrhundert besteht die Zivilisation zum größten Teil aus Barbaren. Sie leben in unterschiedlichen Kulturen, beispielsweise als Seefahrer (die Disuuslachter), Nomaden (die Wandernden Völker) oder Ruinenbewohner (die Loords von Landán). Die zeichnen sich durch Zähigkeit, Wildheit und Kampflust aus, sind zumeist primitiv und leben in Clans. Ehre und Mut werden hoch geschätzt. Technologisch bewegen sich die meisten Barbaren zwischen der späten Steinzeit und dem frühen Mittelalter.',
    Guul: 'Guule sind bedauernswerte Mutationen des Homo Sapiens. Sie sind dürr, fast zwei Meter groß und völlig unbehaart. Ihre langen knochigen Arme enden in Krallen. Die verhornten Füße laufen an den Fersen in einem fingerdicken Stachel aus. Aus dem Maul tropft weißlicher Schleim, was ihr abstoßendes Äußeres zusätzlich verstärkt. Guule sind meist nur mit einem Lendenschurz bekleidet. Sie ernähren sich von Aas und Gebeinen, die sie u.a. aus Gräbern holen.',
    Hydrit: 'Hydriten, von den Menschen oft Fischmenschen genannt, sind ein friedliebendes und altes Volk. Sie leben in geheimen Unterseestädten, sind amphibisch, kultiviert und verfügen über fortgeschrittene biogenetische Technologien. Der Genuss von Fleisch verwandelt Hydriten in gefährliche Bestien, weshalb sie sich meist vegetarisch ernähren.',
    Techno: 'Technos sind Nachfahren der Menschen, die den Kometeneinschlag in Bunkern überlebt und eine neue Zivilisation aufgebaut haben. Aufgrund ihres technologischen Fortschritts gelten sie vielen Barbaren als Götter oder Dämonen. Sie leiden jedoch an einer tödlichen Immunschwäche und können die Außenwelt nur mit Schutzanzug betreten.',
    Präkristofluu: 'Präkristofluu sind Menschen aus dem 21. Jahrhundert, die durch Gefrierkammern oder mysteriöse Zeitphänomene in das 26. Jahrhundert gelangt sind. Ihr technisches Wissen und ihre Kenntnisse der Vergangenheit gleichen ihre geringe Anpassungsfähigkeit aus.'
};

const CULTURE_DESCRIPTIONS = {
    Landbewohner: 'Landbewohner bewirtschaften den Boden und versuchen als Bauern und Viehzüchter ihren Lebensunterhalt zu verdienen. Die meisten sind einfache Menschen, die Ruhe und Frieden suchen, nicht viel von der Welt wissen und einfache Landgötter anbeten. Aberglauben ist weit verbreitet.',
    Stadtbewohner: 'Stadtbewohner versuchen in der dunklen Zukunft der Erde neues Leben erblühen zu lassen. Dazu haben sie sich in neu erbauten Siedlungen (zuweilen auf Ruinen aus der Zeit vor dem Kometen) angesiedelt und leben als Händler, Handwerker und Bauern. Die Mauern ihrer Siedlungen schützen sie vor den Gefahren der Wildnis. Ihre Siedlungen sind somit Lichter der Hoffnung in der Dunkelheit.',
    Meeresbewohner: 'Meeresbewohner sind Hydriten aus großen, seit langem verborgenen Unterseestädten. Ihre Gesellschaft ist streng hierarchisch organisiert, technisch und biotechnologisch weit fortgeschritten und meidet den Kontakt zu Oberflächenbewohnern.',
    Bunkermensch: 'Bunkermenschen sind Nachfahren jener Menschen, die die Katastrophe in Bunkern überlebten. Sie verfügen über Technik der alten Welt, leiden aber durch Isolation an einer fatalen Immunschwäche und begegnen der Oberfläche meist nur in Schutzanzügen.',
    'Mensch des 21. Jahrhunderts': 'Diese Kultur muss von allen Präkristofluu ausgewählt werden. Menschen des 21. Jahrhunderts sind auf verworrenen Pfaden in das 26. Jahrhundert gelangt; ihre Bandbreite ist nahezu unerschöpflich.',
    Nomade: 'Nomaden folgen den Routen ihrer Nutztiere durch die Jahreszeiten. Sie sind wehrhaft, überleben in unwirtlicher Natur und werden von sesshaften Völkern oft misstrauisch betrachtet.',
    Ruinenbewohner: 'Ruinenbewohner leben in den Resten der Städte aus der Zeit vor Kristoflus als Banditen, Jäger und Sammler. Ihre Gesellschaften sind primitiv und von Brutalität und Not geprägt.',
    Untergrundbewohner: 'Untergrundbewohner leben in Höhlen und alten Stollen, um sich vor den Gefahren der Oberfläche zu schützen. Sie arbeiten überwiegend als Bergleute, Jäger und Sammler. Häufig haben sie seltsame Rituale und clanartige Strukturen von großer Starrheit entwickelt.',
    'Volk der 13 Inseln': 'Das Volk der 13 Inseln lebt in Schweden, Dänemark und Finnland. Es besteht vor allem aus Jägern, Bauern und Fischern. Frauen dieser Kultur besitzen die Gabe des Lauschens.'
};

const CULTURE_NAMES = Object.keys(CULTURE_DESCRIPTIONS);
const ATTRIBUTE_IDS = ['st', 'ge', 'ro', 'wi', 'wa', 'in', 'au'];
const PRAEKRISTOFLUU_RACE = 'Präkristofluu';
const MENSCH_21_CULTURE = 'Mensch des 21. Jahrhunderts';
const TECHNO_SKILLS = ['Fahren', 'Feuerwaffen', 'Heiler', 'Pilot', 'Techniker', 'Wissenschaftler'];
const TECHNO_SKILL_POOL_POINTS = 12;
const BUNKERMENSCH_BONUS_SKILLS = ['Feuerwaffen', 'Pilot', 'Wissenschaftler'];
const PRAEKRISTOFLUU_SKILLS = ['Bildung', 'Fahren', 'Feuerwaffen', 'Pilot', 'Techniker', 'Wissenschaftler'];
const PRAEKRISTOFLUU_SKILL_POOL_POINTS = 12;
const MENSCH_21_BONUS_SKILLS = ['Bildung', 'Pilot', 'Techniker', 'Wissenschaftler'];
const NOMADE_COMBAT_SKILLS = ['Nahkampf', 'Fernkampf'];
const NOMADE_MOVEMENT_SKILLS = ['Reiten', 'Athletik'];
const RUINENBEWOHNER_BONUS_SKILLS = ['Nahkampf', 'Fernkampf', 'Athletik', 'Kunde'];
const VOLK_DER_13_INSELN_CULTURE = 'Volk der 13 Inseln';
const VOLK_DER_13_INSELN_PROFESSION_SKILLS = ['Beruf: Bauer', 'Beruf: Fischer'];
const VOLK_DER_13_INSELN_REQUIRED_ADVANTAGE = 'Psychische Kraft';
const FEMALE_GENDER = 'weiblich';

function hydrateExistingCharEditors() {
    if (!window.Alpine
        || typeof window.Alpine.initTree !== 'function'
        || typeof window.Alpine.destroyTree !== 'function'
        || typeof window.Alpine.$data !== 'function') {
        return;
    }

    document.querySelectorAll('[x-data="charEditor"], [x-data^="charEditor("]').forEach((element) => {
        const scope = element._x_dataStack ? window.Alpine.$data(element) : null;
        const hasRegisteredState = scope
            && typeof scope.basicsFilled === 'function'
            && typeof scope.formValid === 'function'
            && Object.hasOwn(scope, 'advancedUnlocked');

        if (hasRegisteredState) {
            return;
        }

        if (element._x_dataStack) {
            window.Alpine.destroyTree(element);
        }

        window.Alpine.initTree(element);
    });
}

function registerCharEditor({ hydrateExisting = false } = {}) {
    if (!window.Alpine || typeof window.Alpine.data !== 'function') {
        return;
    }

    window.Alpine.data('charEditor', () => ({
    // Basic info
    playerName: '',
    characterName: '',
    gender: '',
    race: '',
    culture: '',
    description: '',
    descriptionUserEdited: false,
    portraitPreview: null,
    equipment: '',

    // Game constants
    base: { AP: 2, FP: 20, maxFW: 4, freeAdvantages: 2 },

    // Race/culture state
    raceAPBonus: 0,
    raceGrants: {},
    cultureGrants: {},
    barbarCombatSkill: null,
    citySkill: null,
    seaProfessionSkill: null,
    seaKnowledgeOrCombatSkill: null,
    bunkermenschBonusSkill: null,
    mensch21FirstBonusSkill: null,
    mensch21SecondBonusSkill: null,
    nomadeCombatSkill: null,
    nomadeMovementSkill: null,
    ruinenbewohnerBonusSkill: null,
    volkDer13InselnProfessionSkill: null,
    technoSkillNames: TECHNO_SKILLS,
    technoSkillPoolPoints: TECHNO_SKILL_POOL_POINTS,
    technoSkillPoints: Object.fromEntries(TECHNO_SKILLS.map(name => [name, 2])),
    praekristofluuSkillNames: PRAEKRISTOFLUU_SKILLS,
    praekristofluuSkillPoolPoints: PRAEKRISTOFLUU_SKILL_POOL_POINTS,
    praekristofluuSkillPoints: Object.fromEntries(PRAEKRISTOFLUU_SKILLS.map(name => [name, 2])),
    raceCache: {},
    raceAttributeModifiers: {},
    raceLockedByBunkermenschCulture: false,

    // Dynamic data
    attributes: { st: 0, ge: 0, ro: 0, wi: 0, wa: 0, in: 0, au: 0 },
    skills: [],
    selectedAdvantages: ['Zäh'],
    selectedDisadvantages: [],
    raceLocked: { advantages: [], disadvantages: [] },
    cultureLocked: { advantages: [], disadvantages: [] },
    cultureAutoSelectedAdvantages: [],

    // UI state
    advancedUnlocked: false,

    basicsFilled() {
        return this.playerName.trim() && this.characterName.trim() && this.gender && this.race && this.culture;
    },

    attributeMax() {
        return this.race === 'Barbar' ? 2 : 1;
    },

    attributeModifier(id) {
        return this.raceAttributeModifiers[id] || 0;
    },

    attributeCost(id, value = this.attributes[id]) {
        return Math.max(value - this.attributeModifier(id), 0);
    },

    getAttributeMin(id) {
        return Math.max(-1, -1 + this.attributeModifier(id));
    },

    getAttributeMax(id) {
        return Math.max(this.getAttributeMin(id), this.attributeMax() + this.attributeModifier(id));
    },

    apUsed() {
        return ATTRIBUTE_IDS.reduce((sum, id) => sum + this.attributeCost(id), 0);
    },

    apRemaining() {
        return this.base.AP + this.raceAPBonus - this.apUsed();
    },

    fpUsed() {
        return this.skills.reduce((sum, skill) => {
            const grant = this.getGrant(skill.name);
            const start = grant ? grant.value : 0;
            if (grant && grant.type === 'exact') return sum;
            const diff = skill.value - start;
            return sum + Math.max(diff, 0);
        }, 0);
    },

    fpRemaining() {
        return this.base.FP - this.fpUsed();
    },

    lockedAdvantages() {
        return [...new Set([...this.raceLocked.advantages, ...this.cultureLocked.advantages])];
    },

    chosenAdvantagesCount() {
        const lockedAdvantages = this.lockedAdvantages();

        return this.selectedAdvantages.filter(a => a !== 'Zäh' && !lockedAdvantages.includes(a)).length;
    },

    freeAdvantagePoints() {
        return this.base.freeAdvantages - this.chosenAdvantagesCount();
    },

    hasKindZweierWelten() {
        return this.selectedAdvantages.includes('Kind zweier Welten');
    },

    formValid() {
        return this.apRemaining() === 0
            && this.fpRemaining() === 0
            && this.technoSkillPoolComplete()
            && this.praekristofluuSkillPoolComplete()
            && this.selectedDisadvantages.length >= this.chosenAdvantagesCount();
    },

    shouldMirrorBaseFields() {
        return this.advancedUnlocked;
    },

    shouldSubmitPortraitPreview() {
        return this.advancedUnlocked && Boolean(this.portraitPreview);
    },

    shouldMirrorSkillName(skill) {
        return Boolean(skill?.nameDisabled);
    },

    shouldMirrorSkillValue(skill) {
        if (!skill) return false;
        if (skill.valueDisabled) return true;

        return this.getGrant(skill.name)?.type === 'exact';
    },

    allUsedSkillNames() {
        const used = new Set([
            ...Object.keys(this.raceGrants),
            ...Object.keys(this.cultureGrants),
            ...this.skills.map(s => s.name).filter(Boolean),
        ]);
        if (!this.hasKindZweierWelten()) {
            if (this.raceGrants['Intuition']) used.add('Bildung');
            if (this.raceGrants['Bildung']) used.add('Intuition');
        }
        return used;
    },

    allowedCulturesForRace(race = this.race) {
        if (race === 'Hydrit') return ['Meeresbewohner'];
        if (race === 'Techno') return ['Bunkermensch'];
        if (race === PRAEKRISTOFLUU_RACE) return [MENSCH_21_CULTURE];

        return CULTURE_NAMES.filter(culture => culture !== 'Bunkermensch'
            && culture !== MENSCH_21_CULTURE
            && (race === 'Barbar' || culture !== VOLK_DER_13_INSELN_CULTURE));
    },

    isCultureSelectable(culture) {
        if (culture === 'Bunkermensch') {
            return this.race !== 'Hydrit' && this.race !== PRAEKRISTOFLUU_RACE;
        }

        if (this.race === 'Techno' && this.raceLockedByBunkermenschCulture) {
            return this.allowedCulturesForRace('').includes(culture);
        }

        return this.allowedCulturesForRace().includes(culture);
    },

    isRaceSelectable(race) {
        return this.culture !== 'Bunkermensch' || !this.raceLockedByBunkermenschCulture || race === 'Techno';
    },

    enforceCultureForRace() {
        const allowedCultures = this.allowedCulturesForRace();
        if (allowedCultures.includes(this.culture)) return false;
        if (!this.culture && allowedCultures.length !== 1) return false;

        const [defaultCulture = ''] = allowedCultures.length === 1 ? allowedCultures : [''];
        this.clearCulture();
        this.culture = defaultCulture;

        return true;
    },

    // --- Methods ---
    init() {
        this.$watch('race', () => this.handleRaceChange());
        this.$watch('culture', () => this.handleCultureChange());
        this.$watch('gender', () => this.handleGenderChange());
        this.$watch('selectedAdvantages', () => this.enforceAdvantageLimit());
    },

    clampAttribute(id) {
        let val = this.attributes[id];
        if (typeof val !== 'number' || isNaN(val)) val = this.attributeModifier(id) || 0;
        val = Math.max(this.getAttributeMin(id), Math.min(val, this.getAttributeMax(id)));

        // Check AP budget against paid values after race modifiers.
        const othersUsed = ATTRIBUTE_IDS.reduce((sum, otherId) => {
            if (otherId === id) return sum;
            return sum + this.attributeCost(otherId);
        }, 0);
        const availableForThis = Math.max(0, this.base.AP + this.raceAPBonus - othersUsed);
        const maxForThis = Math.min(this.getAttributeMax(id), this.attributeModifier(id) + availableForThis);
        val = Math.min(val, Math.max(this.getAttributeMin(id), maxForThis));

        this.attributes[id] = val;
    },

    handlePortraitUpload(event) {
        const file = event.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = (e) => { this.portraitPreview = e.target.result; };
            reader.readAsDataURL(file);
        } else {
            this.portraitPreview = null;
        }
    },

    unlockAdvanced() {
        this.advancedUnlocked = true;
    },

    handleGenderChange() {
        this.refreshCultureLockedAdvantages();
    },

    updateDescription() {
        if (this.descriptionUserEdited) return;
        let text = RACE_DESCRIPTIONS[this.race] || '';
        if (CULTURE_DESCRIPTIONS[this.culture]) {
            text += (text ? '\n\n' : '') + CULTURE_DESCRIPTIONS[this.culture];
        }
        this.description = text;
    },

    // --- Skill management ---
    addSkill() {
        if (this.fpRemaining() <= 0) return;
        this.skills.push({ name: '', value: 0, source: null, locked: false, nameDisabled: false, valueDisabled: false, badge: null });
    },

    removeSkill(index) {
        this.skills.splice(index, 1);
    },

    ensureSkill(name) {
        const existing = this.skills.find(s => s.name === name);
        if (existing) return existing;
        const skill = { name, value: 0, source: null, locked: false, nameDisabled: false, valueDisabled: false, badge: null };
        this.skills.push(skill);
        return skill;
    },

    setFreeMin(name, value, source) {
        const grants = source === 'Rasse' ? this.raceGrants : this.cultureGrants;
        grants[name] = { type: 'min', value };
        const skill = this.ensureSkill(name);
        this.applyGrantToSkill(skill);
    },

    setFreeExact(name, value, source) {
        const grants = source === 'Rasse' ? this.raceGrants : this.cultureGrants;
        grants[name] = { type: 'exact', value };
        const skill = this.ensureSkill(name);
        this.applyGrantToSkill(skill);
    },

    applyGrantToSkill(skill) {
        const grant = this.getGrant(skill.name);
        if (!grant) return;

        const hasRaceGrant = Boolean(this.raceGrants[skill.name]);
        const hasCultureGrant = Boolean(this.cultureGrants[skill.name]);

        skill.nameDisabled = true;
        skill.locked = true;
        skill.badge = hasRaceGrant && hasCultureGrant ? 'Rasse/Kultur' : (hasRaceGrant ? 'Rasse' : 'Kultur');
        skill.valueDisabled = grant.type === 'exact';

        if (grant.type === 'exact') {
            skill.value = grant.value;
        } else if (skill.value < grant.value) {
            skill.value = grant.value;
        }
    },

    refreshGrantedSkill(name) {
        const skill = this.skills.find(s => s.name === name);
        if (!skill) return;

        const grant = this.getGrant(name);
        if (!grant) {
            this.skills = this.skills.filter(s => s !== skill);
            return;
        }

        this.applyGrantToSkill(skill);
    },

    getGrant(name) {
        const grants = [this.raceGrants[name], this.cultureGrants[name]].filter(Boolean);
        if (!grants.length) return null;

        return {
            type: grants.some(grant => grant.type === 'exact') ? 'exact' : 'min',
            value: Math.max(...grants.map(grant => grant.value)),
        };
    },

    getSkillMin(name) {
        const grant = this.getGrant(name);
        return grant ? grant.value : 0;
    },

    getSkillMax(name) {
        const grant = this.getGrant(name);
        if (grant && grant.type === 'exact') return grant.value;
        return this.base.maxFW;
    },

    isSkillDisabled(skill) {
        if (skill.valueDisabled) return true;
        const grant = this.getGrant(skill.name);
        if (grant && grant.type === 'exact') return true;
        // Education/Intuition exclusivity
        if (!this.hasKindZweierWelten()) {
            const intuitionGrant = !!this.raceGrants['Intuition'];
            const bildungGrant = !!this.raceGrants['Bildung'];
            if (intuitionGrant && skill.name === 'Bildung') return true;
            if (bildungGrant && skill.name === 'Intuition') return true;
            if (skill.name === 'Bildung') {
                const intuitionSkill = this.skills.find(s => s.name === 'Intuition');
                if (intuitionSkill && intuitionSkill.value >= 1) return true;
            }
            if (skill.name === 'Intuition' && !this.raceGrants['Intuition']) {
                const bildungSkill = this.skills.find(s => s.name === 'Bildung');
                if (bildungSkill && bildungSkill.value >= 1) return true;
            }
        }
        return false;
    },

    clampSkillValue(skill) {
        const min = this.getSkillMin(skill.name);
        const max = this.getSkillMax(skill.name);
        if (skill.value < min) skill.value = min;
        if (skill.value > max) skill.value = max;

        // Check FP budget
        const grant = this.getGrant(skill.name);
        if (!grant || grant.type === 'min') {
            const start = grant ? grant.value : 0;
            const diff = skill.value - start;
            const othersUsed = this.skills.reduce((sum, s) => {
                if (s === skill) return sum;
                const g = this.getGrant(s.name);
                if (g && g.type === 'exact') return sum;
                const st = g ? g.value : 0;
                return sum + Math.max(s.value - st, 0);
            }, 0);
            const maxForThis = Math.max(0, this.base.FP - othersUsed);
            if (diff > maxForThis) {
                skill.value = start + maxForThis;
            }
        }
    },

    isSkillNameUsed(name, currentIndex) {
        if (!name) return false;
        return this.allUsedSkillNames().has(name) && !this.skills.some((s, i) => i === currentIndex && s.name === name);
    },

    isSkillOptionDisabled(optionValue) {
        return this.allUsedSkillNames().has(optionValue);
    },

    resetTechnoSkillPoints(defaultValue = 0) {
        this.technoSkillPoints = Object.fromEntries(TECHNO_SKILLS.map(name => [name, defaultValue]));
    },

    technoPoolUsed() {
        return TECHNO_SKILLS.reduce((sum, name) => sum + (Number(this.technoSkillPoints[name]) || 0), 0);
    },

    technoSkillPoolComplete() {
        return this.race !== 'Techno' || this.technoPoolUsed() === this.technoSkillPoolPoints;
    },

    setTechnoSkillPoints(skillName, value) {
        if (!TECHNO_SKILLS.includes(skillName)) return;

        const parsedValue = Number.parseInt(value, 10);
        const normalizedValue = Number.isFinite(parsedValue)
            ? Math.max(0, Math.min(parsedValue, this.base.maxFW))
            : 0;

        this.technoSkillPoints[skillName] = normalizedValue;
        this.applyTechnoSkillGrant(skillName);
        this.refreshBunkermenschBonusGrant();
    },

    applyTechnoSkillGrant(skillName) {
        const value = this.race === 'Techno' ? (Number(this.technoSkillPoints[skillName]) || 0) : 0;

        if (value > 0) {
            this.raceGrants[skillName] = { type: 'min', value };
            const skill = this.ensureSkill(skillName);
            this.applyGrantToSkill(skill);
            return;
        }

        delete this.raceGrants[skillName];
        this.refreshGrantedSkill(skillName);
    },

    refreshAllTechnoSkillGrants() {
        TECHNO_SKILLS.forEach(name => this.applyTechnoSkillGrant(name));
        this.refreshBunkermenschBonusGrant();
    },

    resetPraekristofluuSkillPoints(defaultValue = 0) {
        this.praekristofluuSkillPoints = Object.fromEntries(PRAEKRISTOFLUU_SKILLS.map(name => [name, defaultValue]));
    },

    praekristofluuPoolUsed() {
        return PRAEKRISTOFLUU_SKILLS.reduce((sum, name) => sum + (Number(this.praekristofluuSkillPoints[name]) || 0), 0);
    },

    praekristofluuSkillPoolComplete() {
        return this.race !== PRAEKRISTOFLUU_RACE || this.praekristofluuPoolUsed() === this.praekristofluuSkillPoolPoints;
    },

    setPraekristofluuSkillPoints(skillName, value) {
        if (!PRAEKRISTOFLUU_SKILLS.includes(skillName)) return;

        const parsedValue = Number.parseInt(value, 10);
        const normalizedValue = Number.isFinite(parsedValue)
            ? Math.max(0, Math.min(parsedValue, this.base.maxFW))
            : 0;

        this.praekristofluuSkillPoints[skillName] = normalizedValue;
        this.applyPraekristofluuSkillGrant(skillName);
        this.refreshAllMensch21BonusGrants();
    },

    applyPraekristofluuSkillGrant(skillName) {
        const value = this.race === PRAEKRISTOFLUU_RACE ? (Number(this.praekristofluuSkillPoints[skillName]) || 0) : 0;

        if (value > 0) {
            this.raceGrants[skillName] = { type: 'min', value };
            const skill = this.ensureSkill(skillName);
            this.applyGrantToSkill(skill);
            return;
        }

        delete this.raceGrants[skillName];
        this.refreshGrantedSkill(skillName);
    },

    refreshAllPraekristofluuSkillGrants() {
        PRAEKRISTOFLUU_SKILLS.forEach(name => this.applyPraekristofluuSkillGrant(name));
        this.refreshAllMensch21BonusGrants();
    },

    setRaceAttributeModifiers(modifiers) {
        this.raceAttributeModifiers = { ...modifiers };

        Object.entries(modifiers).forEach(([id, modifier]) => {
            if (!ATTRIBUTE_IDS.includes(id)) return;
            const paidValue = Number.isFinite(Number(this.attributes[id])) ? Number(this.attributes[id]) : 0;
            const modifiedValue = paidValue + modifier;
            this.attributes[id] = Math.max(this.getAttributeMin(id), Math.min(modifiedValue, this.getAttributeMax(id)));
        });
    },

    clearRaceAttributeModifiers() {
        const previousModifiers = { ...this.raceAttributeModifiers };
        this.raceAttributeModifiers = {};

        Object.entries(previousModifiers).forEach(([id, modifier]) => {
            if (!ATTRIBUTE_IDS.includes(id)) return;
            const modifiedValue = Number.isFinite(Number(this.attributes[id])) ? Number(this.attributes[id]) : modifier;
            const paidValue = modifiedValue - modifier;
            this.attributes[id] = Math.max(-1, Math.min(paidValue, this.attributeMax()));
        });
    },

    // --- Race handling ---
    handleRaceChange() {
        const previousRace = this._prevRace || '';
        if (this.race === previousRace) return;

        if (!this.isRaceSelectable(this.race)) {
            this.race = 'Techno';
        } else if (this.culture !== 'Bunkermensch' || this.race !== 'Techno') {
            this.raceLockedByBunkermenschCulture = false;
        }

        if (this.race === previousRace) return;

        if (this.raceCache[this._prevRace]) {
            // Already cached by cacheRaceState below
        } else if (this._prevRace) {
            this.cacheRaceState(this._prevRace);
        }
        this.clearRace();
        if (this.race === 'Barbar') this.applyRaceBarbar();
        if (this.race === 'Guul') this.applyRaceGuul();
        if (this.race === 'Hydrit') this.applyRaceHydrit();
        if (this.race === 'Techno') this.applyRaceTechno();
        if (this.race === PRAEKRISTOFLUU_RACE) this.applyRacePraekristofluu();
        this.restoreRaceState(this.race);
        this.enforceCultureForRace();
        this._prevRace = this.race;
        this.updateDescription();
    },

    cacheRaceState(raceName) {
        if (!raceName) return;
        this.raceCache[raceName] = {
            attributes: { ...this.attributes },
            skills: this.skills.filter(s => this.raceGrants[s.name]).map(s => ({ name: s.name, value: s.value })),
            barbarCombatSkill: this.barbarCombatSkill,
            technoSkillPoints: { ...this.technoSkillPoints },
            praekristofluuSkillPoints: { ...this.praekristofluuSkillPoints },
        };
    },

    restoreRaceState(raceName) {
        const cache = this.raceCache[raceName];
        if (!cache) return;
        ATTRIBUTE_IDS.forEach(id => {
            if (cache.attributes[id] !== undefined) {
                this.attributes[id] = Math.max(this.getAttributeMin(id), Math.min(cache.attributes[id], this.getAttributeMax(id)));
            }
        });
        cache.skills.forEach(cached => {
            const skill = this.skills.find(s => s.name === cached.name);
            if (skill) skill.value = cached.value;
        });
        if (raceName === 'Barbar' && cache.barbarCombatSkill) {
            this.setBarbarCombatSkill(cache.barbarCombatSkill);
        }
        if (raceName === 'Techno' && cache.technoSkillPoints) {
            this.technoSkillPoints = { ...this.technoSkillPoints, ...cache.technoSkillPoints };
            this.refreshAllTechnoSkillGrants();
        }
        if (raceName === PRAEKRISTOFLUU_RACE && cache.praekristofluuSkillPoints) {
            this.praekristofluuSkillPoints = { ...this.praekristofluuSkillPoints, ...cache.praekristofluuSkillPoints };
            this.refreshAllPraekristofluuSkillGrants();
        }
    },

    clearRace() {
        const previousRaceSkills = Object.keys(this.raceGrants);
        const previousLockedAdvantages = [...this.raceLocked.advantages];
        const previousLockedDisadvantages = [...this.raceLocked.disadvantages];

        this.raceAPBonus = 0;
        this.raceGrants = {};
        this.barbarCombatSkill = null;
        this.resetTechnoSkillPoints(0);
        this.resetPraekristofluuSkillPoints(0);
        this.clearRaceAttributeModifiers();
        previousRaceSkills.forEach(name => this.refreshGrantedSkill(name));
        this.selectedAdvantages = this.selectedAdvantages.filter(value => value === 'Zäh' || !previousLockedAdvantages.includes(value));
        this.selectedDisadvantages = this.selectedDisadvantages.filter(value => !previousLockedDisadvantages.includes(value));
        this.raceLocked.advantages = [];
        this.raceLocked.disadvantages = [];
    },

    applyRaceBarbar() {
        this.raceAPBonus = 1;
        this.setFreeMin('Überleben', 1, 'Rasse');
        this.setFreeMin('Intuition', 1, 'Rasse');
        this.setBarbarCombatSkill('Nahkampf');
    },

    applyRaceGuul() {
        this.attributes.au = -1;
        this.setFreeMin('Heimlichkeit', 2, 'Rasse');
        this.setFreeMin('Intuition', 1, 'Rasse');
        this.setFreeMin('Natürliche Waffen', 1, 'Rasse');
        this.raceLocked.disadvantages = ['Primitiv', 'Gejagt'];
        this.selectedDisadvantages = [...new Set([...this.selectedDisadvantages, 'Primitiv', 'Gejagt'])];
    },

    applyRaceHydrit() {
        this.setFreeMin('Athletik', 2, 'Rasse');
        this.setFreeMin('Bildung', 1, 'Rasse');
        this.setFreeMin('Natürliche Waffen', 1, 'Rasse');
        this.raceLocked.advantages = ['Kiemen', 'Natürliche Waffen'];
        this.raceLocked.disadvantages = ['Anfälligkeit gegen Wahnsinn'];
        this.selectedAdvantages = [...new Set([...this.selectedAdvantages, 'Kiemen', 'Natürliche Waffen'])];
        this.selectedDisadvantages = [...new Set([...this.selectedDisadvantages, 'Anfälligkeit gegen Wahnsinn'])];
    },

    applyRaceTechno() {
        this.setRaceAttributeModifiers({ st: -1, ro: -1, in: 1 });
        this.resetTechnoSkillPoints(2);
        this.refreshAllTechnoSkillGrants();
        this.raceLocked.advantages = ['High-Tech-Ausrüstung'];
        this.raceLocked.disadvantages = ['Tödliche Immunschwäche'];
        this.selectedAdvantages = [...new Set([...this.selectedAdvantages, 'High-Tech-Ausrüstung'])];
        this.selectedDisadvantages = [...new Set([...this.selectedDisadvantages, 'Tödliche Immunschwäche'])];
    },

    applyRacePraekristofluu() {
        this.setFreeMin('Beruf', 3, 'Rasse');
        this.resetPraekristofluuSkillPoints(2);
        this.refreshAllPraekristofluuSkillGrants();
        this.raceLocked.advantages = ['High-Tech-Ausrüstung'];
        this.selectedAdvantages = [...new Set([...this.selectedAdvantages, 'High-Tech-Ausrüstung'])];
    },

    setBarbarCombatSkill(skillName) {
        ['Nahkampf', 'Fernkampf']
            .filter(name => name !== skillName && this.raceGrants[name])
            .forEach(name => {
                delete this.raceGrants[name];
                this.refreshGrantedSkill(name);
            });

        this.barbarCombatSkill = skillName;
        this.raceGrants[skillName] = { type: 'min', value: 1 };
        const skill = this.ensureSkill(skillName);
        this.applyGrantToSkill(skill);
    },

    // --- Culture handling ---
    handleCultureChange() {
        if (!this.isCultureSelectable(this.culture)) {
            this.enforceCultureForRace();
            return;
        }

        if (this.culture === 'Bunkermensch') {
            this.ensureTechnoRaceForBunkermenschCulture();
        } else {
            this.releaseBunkermenschRaceLock();
        }

        this.clearCulture();
        if (this.culture === 'Landbewohner') this.applyCultureLandbewohner();
        if (this.culture === 'Stadtbewohner') this.applyCultureStadtbewohner();
        if (this.culture === 'Meeresbewohner') this.applyCultureMeeresbewohner();
        if (this.culture === 'Bunkermensch') this.applyCultureBunkermensch();
        if (this.culture === MENSCH_21_CULTURE) this.applyCultureMensch21();
        if (this.culture === 'Nomade') this.applyCultureNomade();
        if (this.culture === 'Ruinenbewohner') this.applyCultureRuinenbewohner();
        if (this.culture === 'Untergrundbewohner') this.applyCultureUntergrundbewohner();
        if (this.culture === VOLK_DER_13_INSELN_CULTURE) this.applyCultureVolkDer13Inseln();
        this.updateDescription();
    },

    ensureTechnoRaceForBunkermenschCulture() {
        if (this.race === 'Techno') return;

        this.raceLockedByBunkermenschCulture = true;
        this.race = 'Techno';
        this.handleRaceChange();
    },

    releaseBunkermenschRaceLock() {
        if (!this.raceLockedByBunkermenschCulture) return;

        this.raceLockedByBunkermenschCulture = false;
        if (this.race !== 'Techno') return;

        this.race = '';
        this.handleRaceChange();
    },

    clearCulture() {
        const previousCultureSkills = Object.keys(this.cultureGrants);

        this.cultureGrants = {};
        this.citySkill = null;
        this.seaProfessionSkill = null;
        this.seaKnowledgeOrCombatSkill = null;
        this.bunkermenschBonusSkill = null;
        this.mensch21FirstBonusSkill = null;
        this.mensch21SecondBonusSkill = null;
        this.nomadeCombatSkill = null;
        this.nomadeMovementSkill = null;
        this.ruinenbewohnerBonusSkill = null;
        this.volkDer13InselnProfessionSkill = null;
        this.setCultureLockedAdvantages([]);
        previousCultureSkills.forEach(name => this.refreshGrantedSkill(name));
    },

    applyCultureLandbewohner() {
        this.setFreeExact('Beruf: Viehzüchter', 2, 'Kultur');
        this.setFreeExact('Beruf: Landwirt', 2, 'Kultur');
        this.setFreeMin('Kunde: Wetter', 1, 'Kultur');
    },

    applyCultureStadtbewohner() {
        this.setCitySkill('Unterhalten');
        this.setFreeMin('Beruf', 1, 'Kultur');
        this.setFreeMin('Kunde', 1, 'Kultur');
    },

    applyCultureMeeresbewohner() {
        this.setFreeMin('Athletik', 1, 'Kultur');
        this.setSeaProfessionSkill('Beruf: Farmer');
        this.setSeaKnowledgeOrCombatSkill('Wissenschaftler');
    },

    applyCultureBunkermensch() {
        this.setFreeMin('Bildung', 1, 'Kultur');
        this.setFreeMin('Nahkampf', 1, 'Kultur');
        this.setBunkermenschBonusSkill('Feuerwaffen');
    },

    applyCultureMensch21() {
        this.setFreeMin('Beruf', 1, 'Kultur');
        this.setMensch21BonusSkills('Bildung', 'Pilot');
    },

    applyCultureNomade() {
        this.setFreeMin('\u00dcberleben', 1, 'Kultur');
        this.setNomadeCombatSkill('Nahkampf');
        this.setNomadeMovementSkill('Reiten');
    },

    applyCultureRuinenbewohner() {
        this.setFreeMin('Diebeskunst', 1, 'Kultur');
        this.setFreeMin('Heimlichkeit', 1, 'Kultur');
        this.setRuinenbewohnerBonusSkill('Nahkampf');
    },

    applyCultureUntergrundbewohner() {
        this.setFreeMin('Athletik', 1, 'Kultur');
        this.setFreeMin('Beruf: Bergmann', 1, 'Kultur');
        this.setFreeMin('\u00dcberleben', 1, 'Kultur');
    },

    applyCultureVolkDer13Inseln() {
        this.setFreeMin('Athletik', 1, 'Kultur');
        this.setFreeMin('\u00dcberleben', 1, 'Kultur');
        this.setVolkDer13InselnProfessionSkill('Beruf: Bauer');
        this.refreshCultureLockedAdvantages();
    },

    setCitySkill(skillName) {
        ['Unterhalten', 'Sprachen']
            .filter(name => name !== skillName && this.cultureGrants[name])
            .forEach(name => {
                delete this.cultureGrants[name];
                this.refreshGrantedSkill(name);
            });

        this.citySkill = skillName;
        this.cultureGrants[skillName] = { type: 'min', value: 1 };
        const skill = this.ensureSkill(skillName);
        this.applyGrantToSkill(skill);
    },

    setSeaProfessionSkill(skillName) {
        ['Beruf: Farmer', 'Beruf: Künstler']
            .filter(name => name !== skillName && this.cultureGrants[name])
            .forEach(name => {
                delete this.cultureGrants[name];
                this.refreshGrantedSkill(name);
            });

        this.seaProfessionSkill = skillName;
        this.cultureGrants[skillName] = { type: 'min', value: 1 };
        const skill = this.ensureSkill(skillName);
        this.applyGrantToSkill(skill);
    },

    setSeaKnowledgeOrCombatSkill(skillName) {
        ['Wissenschaftler', 'Techniker', 'Nahkampf']
            .filter(name => name !== skillName && this.cultureGrants[name])
            .forEach(name => {
                delete this.cultureGrants[name];
                this.refreshGrantedSkill(name);
            });

        this.seaKnowledgeOrCombatSkill = skillName;
        this.cultureGrants[skillName] = { type: 'min', value: 1 };
        const skill = this.ensureSkill(skillName);
        this.applyGrantToSkill(skill);
    },

    setCultureChoiceSkill(skillName, optionNames, stateProperty, value = 1) {
        if (!optionNames.includes(skillName)) return;

        optionNames
            .filter(name => name !== skillName && this.cultureGrants[name])
            .forEach(name => {
                delete this.cultureGrants[name];
                this.refreshGrantedSkill(name);
            });

        this[stateProperty] = skillName;
        this.cultureGrants[skillName] = { type: 'min', value };
        const skill = this.ensureSkill(skillName);
        this.applyGrantToSkill(skill);
    },

    setNomadeCombatSkill(skillName) {
        this.setCultureChoiceSkill(skillName, NOMADE_COMBAT_SKILLS, 'nomadeCombatSkill');
    },

    setNomadeMovementSkill(skillName) {
        this.setCultureChoiceSkill(skillName, NOMADE_MOVEMENT_SKILLS, 'nomadeMovementSkill');
    },

    setRuinenbewohnerBonusSkill(skillName) {
        this.setCultureChoiceSkill(skillName, RUINENBEWOHNER_BONUS_SKILLS, 'ruinenbewohnerBonusSkill');
    },

    setVolkDer13InselnProfessionSkill(skillName) {
        this.setCultureChoiceSkill(skillName, VOLK_DER_13_INSELN_PROFESSION_SKILLS, 'volkDer13InselnProfessionSkill');
    },

    setBunkermenschBonusSkill(skillName) {
        if (!BUNKERMENSCH_BONUS_SKILLS.includes(skillName)) return;

        BUNKERMENSCH_BONUS_SKILLS
            .filter(name => name !== skillName && this.cultureGrants[name])
            .forEach(name => {
                delete this.cultureGrants[name];
                const grant = this.getGrant(name);
                const skill = this.skills.find(s => s.name === name);
                if (skill && grant && skill.value > grant.value) {
                    skill.value = grant.value;
                }
                this.refreshGrantedSkill(name);
            });

        this.bunkermenschBonusSkill = skillName;
        this.refreshBunkermenschBonusGrant();
    },

    refreshBunkermenschBonusGrant() {
        if (this.culture !== 'Bunkermensch' || !this.bunkermenschBonusSkill) return;

        const raceValue = Number(this.technoSkillPoints[this.bunkermenschBonusSkill]) || 0;
        const value = Math.min(this.base.maxFW, raceValue + 1);
        this.cultureGrants[this.bunkermenschBonusSkill] = { type: 'min', value };
        const skill = this.ensureSkill(this.bunkermenschBonusSkill);
        this.applyGrantToSkill(skill);
    },

    setMensch21FirstBonusSkill(skillName) {
        this.setMensch21BonusSkills(skillName, this.mensch21SecondBonusSkill);
    },

    setMensch21SecondBonusSkill(skillName) {
        this.setMensch21BonusSkills(this.mensch21FirstBonusSkill, skillName);
    },

    setMensch21BonusSkills(firstSkill, secondSkill) {
        const first = MENSCH_21_BONUS_SKILLS.includes(firstSkill)
            ? firstSkill
            : MENSCH_21_BONUS_SKILLS[0];
        let second = MENSCH_21_BONUS_SKILLS.includes(secondSkill)
            ? secondSkill
            : MENSCH_21_BONUS_SKILLS.find(name => name !== first);

        if (second === first) {
            second = MENSCH_21_BONUS_SKILLS.find(name => name !== first);
        }

        const nextSkills = new Set([first, second].filter(Boolean));
        MENSCH_21_BONUS_SKILLS
            .filter(name => !nextSkills.has(name) && this.cultureGrants[name])
            .forEach(name => {
                delete this.cultureGrants[name];
                const grant = this.getGrant(name);
                const skill = this.skills.find(s => s.name === name);
                if (skill && grant && skill.value > grant.value) {
                    skill.value = grant.value;
                }
                this.refreshGrantedSkill(name);
            });

        this.mensch21FirstBonusSkill = first;
        this.mensch21SecondBonusSkill = second;
        this.refreshAllMensch21BonusGrants();
    },

    refreshAllMensch21BonusGrants() {
        if (this.culture !== MENSCH_21_CULTURE) return;

        [...new Set([this.mensch21FirstBonusSkill, this.mensch21SecondBonusSkill].filter(Boolean))]
            .forEach(name => this.refreshMensch21BonusGrant(name));
    },

    refreshMensch21BonusGrant(skillName) {
        if (this.culture !== MENSCH_21_CULTURE || !MENSCH_21_BONUS_SKILLS.includes(skillName)) return;

        const raceValue = this.race === PRAEKRISTOFLUU_RACE
            ? (Number(this.praekristofluuSkillPoints[skillName]) || 0)
            : 0;
        const value = Math.min(this.base.maxFW, raceValue + 1);
        this.cultureGrants[skillName] = { type: 'min', value };
        const skill = this.ensureSkill(skillName);
        this.applyGrantToSkill(skill);
    },

    setCultureLockedAdvantages(advantages) {
        const nextAdvantages = [...new Set(advantages)];
        const previousAutoSelected = [...this.cultureAutoSelectedAdvantages];
        const autoSelectedToRemove = previousAutoSelected.filter(value => !nextAdvantages.includes(value));

        this.selectedAdvantages = this.selectedAdvantages.filter(value => !autoSelectedToRemove.includes(value));

        const newlyAutoSelected = nextAdvantages.filter(value => !this.selectedAdvantages.includes(value));
        this.selectedAdvantages = [...new Set([...this.selectedAdvantages, ...newlyAutoSelected])];
        this.cultureLocked.advantages = nextAdvantages;
        this.cultureAutoSelectedAdvantages = [
            ...previousAutoSelected.filter(value => nextAdvantages.includes(value) && this.selectedAdvantages.includes(value)),
            ...newlyAutoSelected,
        ];
        this.enforceAdvantageLimit();
    },

    refreshCultureLockedAdvantages() {
        const advantages = this.culture === VOLK_DER_13_INSELN_CULTURE && this.gender === FEMALE_GENDER
            ? [VOLK_DER_13_INSELN_REQUIRED_ADVANTAGE]
            : [];

        this.setCultureLockedAdvantages(advantages);
    },

    // --- Advantages ---
    enforceAdvantageLimit() {
        const max = this.base.freeAdvantages;
        const lockedAdvantages = this.lockedAdvantages();
        const locked = this.selectedAdvantages.filter(a => lockedAdvantages.includes(a));
        const chosen = this.selectedAdvantages.filter(a => a !== 'Zäh' && !lockedAdvantages.includes(a));

        if (chosen.length > max) {
            this.selectedAdvantages = [...new Set(['Zäh', ...locked, ...chosen.slice(0, max)])];
        }
        if (!this.selectedAdvantages.includes('Zäh')) {
            this.selectedAdvantages = ['Zäh', ...this.selectedAdvantages];
        }
    },

    selectedDisabledAdvantages() {
        return this.selectedAdvantages.filter(value => this.isAdvantageDisabled(value));
    },

    selectedLockedDisadvantages() {
        return this.selectedDisadvantages.filter(value => this.isDisadvantageDisabled(value));
    },

    isAdvantageDisabled(value) {
        if (value === 'Zäh') return true;
        if (this.lockedAdvantages().includes(value)) return true;
        return !this.selectedAdvantages.includes(value) && this.chosenAdvantagesCount() >= this.base.freeAdvantages;
    },

    isDisadvantageDisabled(value) {
        return this.raceLocked.disadvantages.includes(value);
    },
}));

    if (hydrateExisting) {
        hydrateExistingCharEditors();
    }
}

if (window.Alpine && typeof window.Alpine.data === 'function') {
    registerCharEditor({ hydrateExisting: true });
} else {
    document.addEventListener('alpine:init', () => registerCharEditor(), { once: true });
}
