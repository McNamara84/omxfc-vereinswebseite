const Alpine = window.Alpine;

const RACE_DESCRIPTIONS = {
    Barbar: 'Im 26. Jahrhundert besteht die Zivilisation zum größten Teil aus Barbaren. Sie leben in unterschiedlichen Kulturen, beispielsweise als Seefahrer (die Disuuslachter), Nomaden (die Wandernden Völker) oder Ruinenbewohner (die Loords von Landán). Die zeichnen sich durch Zähigkeit, Wildheit und Kampflust aus, sind zumeist primitiv und leben in Clans. Ehre und Mut werden hoch geschätzt. Technologisch bewegen sich die meisten Barbaren zwischen der späten Steinzeit und dem frühen Mittelalter.',
    Guul: 'Guule sind bedauernswerte Mutationen des Homo Sapiens. Sie sind dürr, fast zwei Meter groß und völlig unbehaart. Ihre langen knochigen Arme enden in Krallen. Die verhornten Füße laufen an den Fersen in einem fingerdicken Stachel aus. Aus dem Maul tropft weißlicher Schleim, was ihr abstoßendes Äußeres zusätzlich verstärkt. Guule sind meist nur mit einem Lendenschurz bekleidet. Sie ernähren sich von Aas und Gebeinen, die sie u.a. aus Gräbern holen.'
};

const CULTURE_DESCRIPTIONS = {
    Landbewohner: 'Landbewohner bewirtschaften den Boden und versuchen als Bauern und Viehzüchter ihren Lebensunterhalt zu verdienen. Die meisten sind einfache Menschen, die Ruhe und Frieden suchen, nicht viel von der Welt wissen und einfache Landgötter anbeten. Aberglauben ist weit verbreitet.',
    Stadtbewohner: 'Stadtbewohner versuchen in der dunklen Zukunft der Erde neues Leben erblühen zu lassen. Dazu haben sie sich in neu erbauten Siedlungen (zuweilen auf Ruinen aus der Zeit vor dem Kometen) angesiedelt und leben als Händler, Handwerker und Bauern. Die Mauern ihrer Siedlungen schützen sie vor den Gefahren der Wildnis. Ihre Siedlungen sind somit Lichter der Hoffnung in der Dunkelheit.'
};

const ATTRIBUTE_IDS = ['st', 'ge', 'ro', 'wi', 'wa', 'in', 'au'];

Alpine.data('charEditor', () => ({
    // Basic info
    playerName: '',
    characterName: '',
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
    raceCache: {},

    // Dynamic data
    attributes: { st: 0, ge: 0, ro: 0, wi: 0, wa: 0, in: 0, au: 0 },
    skills: [],
    selectedAdvantages: ['Zäh'],
    selectedDisadvantages: [],
    raceLocked: { advantages: [], disadvantages: [] },

    // UI state
    advancedUnlocked: false,

    get basicsFilled() {
        return this.playerName.trim() && this.characterName.trim() && this.race && this.culture;
    },

    get attributeMax() {
        return this.race === 'Barbar' ? 2 : 1;
    },

    get apUsed() {
        return ATTRIBUTE_IDS.reduce((sum, id) => sum + Math.max(this.attributes[id], 0), 0);
    },

    get apRemaining() {
        return this.base.AP + this.raceAPBonus - this.apUsed;
    },

    get fpUsed() {
        return this.skills.reduce((sum, skill) => {
            const grant = this.getGrant(skill.name);
            const start = grant ? grant.value : 0;
            if (grant && grant.type === 'exact') return sum;
            const diff = skill.value - start;
            return sum + Math.max(diff, 0);
        }, 0);
    },

    get fpRemaining() {
        return this.base.FP - this.fpUsed;
    },

    get chosenAdvantagesCount() {
        return this.selectedAdvantages.filter(a => a !== 'Zäh').length;
    },

    get freeAdvantagePoints() {
        return this.base.freeAdvantages - this.chosenAdvantagesCount;
    },

    get hasKindZweierWelten() {
        return this.selectedAdvantages.includes('Kind zweier Welten');
    },

    get formValid() {
        return this.apRemaining === 0
            && this.fpRemaining === 0
            && this.selectedDisadvantages.length >= this.chosenAdvantagesCount;
    },

    get allUsedSkillNames() {
        const used = new Set([
            ...Object.keys(this.raceGrants),
            ...Object.keys(this.cultureGrants),
            ...this.skills.map(s => s.name).filter(Boolean),
        ]);
        if (!this.hasKindZweierWelten) {
            if (this.raceGrants['Intuition']) used.add('Bildung');
            if (this.raceGrants['Bildung']) used.add('Intuition');
        }
        return used;
    },

    // --- Methods ---
    init() {
        this.$watch('race', () => this.handleRaceChange());
        this.$watch('culture', () => this.handleCultureChange());
        this.$watch('selectedAdvantages', () => this.enforceAdvantageLimit());
    },

    clampAttribute(id) {
        let val = this.attributes[id];
        if (typeof val !== 'number' || isNaN(val)) val = 0;
        val = Math.max(-1, Math.min(val, this.attributeMax));

        // Check AP budget
        const othersUsed = ATTRIBUTE_IDS.reduce((sum, otherId) => {
            if (otherId === id) return sum;
            return sum + Math.max(this.attributes[otherId], 0);
        }, 0);
        const maxForThis = Math.max(-1, Math.min(this.base.AP + this.raceAPBonus - othersUsed, this.attributeMax));
        val = Math.min(val, maxForThis);

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
        if (this.fpRemaining <= 0) return;
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
        skill.nameDisabled = true;
        skill.locked = true;
        skill.badge = source;
        if (skill.value < value) skill.value = value;
    },

    setFreeExact(name, value, source) {
        const grants = source === 'Rasse' ? this.raceGrants : this.cultureGrants;
        grants[name] = { type: 'exact', value };
        const skill = this.ensureSkill(name);
        skill.nameDisabled = true;
        skill.valueDisabled = true;
        skill.locked = true;
        skill.badge = source;
        skill.value = value;
    },

    getGrant(name) {
        return this.raceGrants[name] || this.cultureGrants[name] || null;
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
        if (!this.hasKindZweierWelten) {
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
        return this.allUsedSkillNames.has(name) && !this.skills.some((s, i) => i === currentIndex && s.name === name);
    },

    isSkillOptionDisabled(optionValue) {
        return this.allUsedSkillNames.has(optionValue);
    },

    // --- Race handling ---
    handleRaceChange() {
        if (this.raceCache[this._prevRace]) {
            // Already cached by cacheRaceState below
        } else if (this._prevRace) {
            this.cacheRaceState(this._prevRace);
        }
        this.clearRace();
        if (this.race === 'Barbar') this.applyRaceBarbar();
        if (this.race === 'Guul') this.applyRaceGuul();
        this.restoreRaceState(this.race);
        this._prevRace = this.race;
        this.updateDescription();
    },

    cacheRaceState(raceName) {
        if (!raceName) return;
        this.raceCache[raceName] = {
            attributes: { ...this.attributes },
            skills: this.skills.filter(s => this.raceGrants[s.name]).map(s => ({ name: s.name, value: s.value })),
            barbarCombatSkill: this.barbarCombatSkill,
        };
    },

    restoreRaceState(raceName) {
        const cache = this.raceCache[raceName];
        if (!cache) return;
        ATTRIBUTE_IDS.forEach(id => {
            if (cache.attributes[id] !== undefined) {
                this.attributes[id] = Math.max(-1, Math.min(cache.attributes[id], this.attributeMax));
            }
        });
        cache.skills.forEach(cached => {
            const skill = this.skills.find(s => s.name === cached.name);
            if (skill) skill.value = cached.value;
        });
        if (raceName === 'Barbar' && cache.barbarCombatSkill) {
            this.setBarbarCombatSkill(cache.barbarCombatSkill);
        }
    },

    clearRace() {
        this.raceAPBonus = 0;
        this.skills = this.skills.filter(s => s.badge !== 'Rasse');
        this.raceGrants = {};
        this.barbarCombatSkill = null;
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

    setBarbarCombatSkill(skillName) {
        const prev = this.barbarCombatSkill;
        if (prev && prev !== skillName) {
            this.skills = this.skills.filter(s => s.name !== prev || s.badge !== 'Rasse');
            delete this.raceGrants[prev];
        }
        this.barbarCombatSkill = skillName;
        this.raceGrants[skillName] = { type: 'min', value: 1 };
        const skill = this.ensureSkill(skillName);
        skill.nameDisabled = true;
        skill.locked = true;
        skill.badge = 'Rasse';
        if (skill.value < 1) skill.value = 1;
    },

    // --- Culture handling ---
    handleCultureChange() {
        this.clearCulture();
        if (this.culture === 'Landbewohner') this.applyCultureLandbewohner();
        if (this.culture === 'Stadtbewohner') this.applyCultureStadtbewohner();
        this.updateDescription();
    },

    clearCulture() {
        this.skills = this.skills.filter(s => s.badge !== 'Kultur');
        this.cultureGrants = {};
        this.citySkill = null;
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

    setCitySkill(skillName) {
        const prev = this.citySkill;
        if (prev && prev !== skillName) {
            this.skills = this.skills.filter(s => s.name !== prev || s.badge !== 'Kultur');
            delete this.cultureGrants[prev];
        }
        this.citySkill = skillName;
        this.cultureGrants[skillName] = { type: 'min', value: 1 };
        const skill = this.ensureSkill(skillName);
        skill.nameDisabled = true;
        skill.locked = true;
        skill.badge = 'Kultur';
        if (skill.value < 1) skill.value = 1;
    },

    // --- Advantages ---
    enforceAdvantageLimit() {
        const max = this.base.freeAdvantages;
        const chosen = this.selectedAdvantages.filter(a => a !== 'Zäh');
        if (chosen.length > max) {
            this.selectedAdvantages = ['Zäh', ...chosen.slice(0, max)];
        }
        if (!this.selectedAdvantages.includes('Zäh')) {
            this.selectedAdvantages = ['Zäh', ...this.selectedAdvantages];
        }
    },

    isAdvantageDisabled(value) {
        if (value === 'Zäh') return true;
        return !this.selectedAdvantages.includes(value) && this.chosenAdvantagesCount >= this.base.freeAdvantages;
    },

    isDisadvantageDisabled(value) {
        return this.raceLocked.disadvantages.includes(value);
    },
}));
