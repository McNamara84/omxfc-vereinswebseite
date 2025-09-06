// Charaktereditor logic for Barbar race and Landbewohner culture

document.addEventListener('DOMContentLoaded', () => {
    const state = {
        niveau: 3,
        base: { AP: 2, FP: 20, maxFW: 4, freeAdvantages: 2, autoAdvantages: ['Zäh'] },
        race: null,
        culture: null,
        raceAPBonus: 0,
        raceGrants: { skills: {} },
        cultureGrants: { skills: {} },
        hasKindZweierWelten: false,
        barbarCombatSkill: null
    };

    // element references
    const attributeIds = ['st','ge','ro','wi','wa','in','au'];
    const attributeInputs = {};
    attributeIds.forEach(id => {
        const el = document.getElementById(id);
        if (el) {
            attributeInputs[id] = el;
            el.dataset.base = 0;
            el.min = -1;
            el.max = 1;
            el.addEventListener('input', onAttributeInput);
        }
    });

    const attributePointsEl = document.getElementById('attribute-points');
    const skillPointsEl = document.getElementById('skill-points');
    const submitButton = document.getElementById('submit-button');
    const advantagesSelect = document.getElementById('advantages');
    const disadvantagesSelect = document.getElementById('disadvantages');
    const advantageInput = document.getElementById('available_advantage_points');
    const raceSelect = document.getElementById('race');
    const cultureSelect = document.getElementById('culture');
    const descriptionField = document.getElementById('description');
    const playerName = document.getElementById('player_name');
    const characterName = document.getElementById('character_name');
    const portraitInput = document.getElementById('portrait');
    const continueBtn = document.getElementById('continue-button');
    const advancedFields = document.getElementById('advanced-fields');

    const barbarCombatContainer = document.getElementById('barbar-combat-toggle');
    const barbarCombatSelect = document.getElementById('barbar-combat-select');

    const skillsContainer = document.getElementById('skills-container');
    const addSkillBtn = document.getElementById('add-skill');
    const skillsDatalist = document.getElementById('skills-list');

    const raceDescriptions = {
        Barbar: 'Barbaren sind wilde Krieger.'
    };

    // initialise counters
    updateAPCounter(state.base.AP);
    updateFPCounter(state.base.FP);
    updateAdvantageCounter(state.base.freeAdvantages);

    // event hooks
    if (raceSelect) raceSelect.addEventListener('change', handleRaceChange);
    if (cultureSelect) cultureSelect.addEventListener('change', handleCultureChange);
    if (advantagesSelect) advantagesSelect.addEventListener('change', recomputeAll);
    if (disadvantagesSelect) disadvantagesSelect.addEventListener('change', recomputeAll);
    if (addSkillBtn) addSkillBtn.addEventListener('click', addSkillRow);
    if (playerName) playerName.addEventListener('input', updateContinueVisibility);
    if (characterName) characterName.addEventListener('input', updateContinueVisibility);
    if (raceSelect) raceSelect.addEventListener('change', updateContinueVisibility);
    if (cultureSelect) cultureSelect.addEventListener('change', updateContinueVisibility);
    if (continueBtn) continueBtn.addEventListener('click', () => {
        advancedFields.disabled = false;
        advancedFields.classList.remove('opacity-50');
        continueBtn.classList.add('hidden');
        [playerName, characterName, raceSelect, cultureSelect, portraitInput].forEach(el => {
            if (!el) return;
            el.disabled = true;
            const wrapper = el.closest('div');
            if (wrapper) wrapper.classList.add('opacity-50');
        });
    });
    if (skillsContainer) {
        skillsContainer.addEventListener('input', e => {
            if (e.target.classList.contains('skill-name')) {
                handleSkillNameInput(e.target);
            }
            if (e.target.type === 'number') {
                enforceSkillSpend(e.target);
            }
            recomputeAll();
        });
        skillsContainer.addEventListener('click', e => {
            if (e.target.classList.contains('remove-skill')) {
                e.target.closest('.skill-row').remove();
                recomputeAll();
            }
        });
    }

    lockAdvantage('Zäh');
    updateContinueVisibility();
    recomputeAll();

    // === Attribute handling ===
    function onAttributeInput(e) {
        const id = e.target.id;
        let base = parseInt(e.target.value, 10);
        if (isNaN(base)) base = 0;
        const max = state.race === 'Barbar' ? 2 : 1;
        if (base > max) base = max;
        if (base < -1) base = -1;
        const old = parseInt(attributeInputs[id].dataset.base || '0', 10);
        const sumOthers = sumUserAttributeIncrements() - Math.max(old, 0);
        let maxForThis = state.base.AP + state.raceAPBonus - sumOthers;
        if (maxForThis > max) maxForThis = max;
        if (base > maxForThis) base = maxForThis;
        attributeInputs[id].dataset.base = base;
        e.target.value = base;
        recomputeAll();
    }

    function enforceAttributeCaps() {
        const max = state.race === 'Barbar' ? 2 : 1;
        attributeIds.forEach(id => {
            const el = attributeInputs[id];
            let base = parseInt(el.dataset.base || '0', 10);
            if (base > max) base = max;
            if (base < -1) base = -1;
            el.dataset.base = base;
            el.value = base;
            el.max = max;
        });
    }

    function sumUserAttributeIncrements() {
        return attributeIds.reduce((sum, id) => {
            const base = parseInt(attributeInputs[id].dataset.base || '0', 10);
            return sum + (base > 0 ? base : 0);
        }, 0);
    }

    // === Skill handling ===
    function addSkillRow() {
        const index = skillsContainer.querySelectorAll('.skill-row').length;
        const row = document.createElement('div');
        row.className = 'grid grid-cols-1 sm:grid-cols-4 gap-2 items-center skill-row';
        row.innerHTML = `
            <input type="text" list="skills-list" name="skills[${index}][name]" class="skill-name sm:col-span-2 w-full rounded-md shadow-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:border-[#8B0116] dark:focus:border-[#FF6B81] focus:ring focus:ring-[#8B0116] dark:focus:ring-[#FF6B81] focus:ring-opacity-50" placeholder="Fertigkeit">
            <input type="number" name="skills[${index}][value]" class="w-full rounded-md shadow-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:border-[#8B0116] dark:focus:border-[#FF6B81] focus:ring focus:ring-[#8B0116] dark:focus:ring-[#FF6B81] focus:ring-opacity-50" placeholder="FW" step="1">
            <button type="button" class="remove-skill px-2 py-1 bg-red-500 text-white rounded-md">-</button>
        `;
        skillsContainer.appendChild(row);
    }

    function ensureSkillRow(name) {
        const rows = skillsContainer.querySelectorAll('.skill-row');
        for (const r of rows) {
            const nameInput = r.querySelector('.skill-name');
            if (nameInput.value === name) return r;
        }
        addSkillRow();
        const newRow = skillsContainer.lastElementChild;
        newRow.querySelector('.skill-name').value = name;
        return newRow;
    }

    function addSkillBadge(row, text) {
        let badge = row.querySelector('.skill-badge');
        if (!badge) {
            badge = document.createElement('span');
            badge.className = 'skill-badge text-xs px-2 py-0.5 rounded bg-blue-200 dark:bg-blue-700 text-blue-800 dark:text-blue-200';
            row.appendChild(badge);
        }
        badge.textContent = text;
    }

    function handleSkillNameInput(input) {
        const name = input.value.trim();
        const used = new Set([
            ...Object.keys(state.raceGrants.skills),
            ...Object.keys(state.cultureGrants.skills)
        ]);
        const rows = skillsContainer.querySelectorAll('.skill-row');
        rows.forEach(row => {
            const nInput = row.querySelector('.skill-name');
            if (nInput !== input) {
                const n = nInput.value.trim();
                if (n) used.add(n);
            }
        });
        if (used.has(name)) {
            input.value = '';
        }
        updateSkillOptions();
    }

    function updateSkillOptions() {
        if (!skillsDatalist) return;
        const used = new Set([
            ...Object.keys(state.raceGrants.skills),
            ...Object.keys(state.cultureGrants.skills)
        ]);
        skillsContainer.querySelectorAll('.skill-row').forEach(row => {
            const n = row.querySelector('.skill-name').value.trim();
            if (n) used.add(n);
        });
        if (!state.hasKindZweierWelten) {
            if (state.raceGrants.skills['Intuition']) used.add('Bildung');
            if (state.raceGrants.skills['Bildung']) used.add('Intuition');
        }
        [...skillsDatalist.options].forEach(o => {
            o.disabled = used.has(o.value);
        });
    }

    function enforceSkillSpend(input) {
        const row = input.closest('.skill-row');
        const name = row.querySelector('.skill-name').value;
        const grant = getGrant(name);
        const start = grant ? grant.value : 0;
        let val = parseInt(input.value, 10) || 0;
        if (val < start) val = start;
        const total = sumUserFPSpends();
        const diff = Math.max(val - start, 0);
        const maxForThis = state.base.FP - (total - diff);
        if (diff > maxForThis) {
            val = start + maxForThis;
        }
        input.value = val;
    }

    function setFreeMin(name, value, source) {
        const grants = source === 'Rasse' ? state.raceGrants.skills : state.cultureGrants.skills;
        grants[name] = { type: 'min', value };
        const row = ensureSkillRow(name);
        const nameInput = row.querySelector('.skill-name');
        const valInput = row.querySelector('input[type="number"]');
        nameInput.value = name;
        nameInput.disabled = true;
        valInput.min = value;
        if (parseInt(valInput.value, 10) < value || isNaN(parseInt(valInput.value, 10))) {
            valInput.value = value;
        }
        const removeBtn = row.querySelector('.remove-skill');
        if (removeBtn) removeBtn.remove();
        addSkillBadge(row, source);
    }

    function setFreeExact(name, value, source) {
        const grants = source === 'Rasse' ? state.raceGrants.skills : state.cultureGrants.skills;
        grants[name] = { type: 'exact', value };
        const row = ensureSkillRow(name);
        const nameInput = row.querySelector('.skill-name');
        const valInput = row.querySelector('input[type="number"]');
        nameInput.value = name;
        nameInput.disabled = true;
        valInput.value = value;
        valInput.min = value;
        valInput.disabled = true;
        const removeBtn = row.querySelector('.remove-skill');
        if (removeBtn) removeBtn.remove();
        addSkillBadge(row, source);
    }

    function removeSkillBadgeSource(source) {
        const badges = skillsContainer.querySelectorAll('.skill-badge');
        badges.forEach(badge => {
            if (badge.textContent === source) {
                const row = badge.closest('.skill-row');
                row.remove();
            }
        });
    }

    function enforceSkillCaps(maxFW) {
        const rows = skillsContainer.querySelectorAll('.skill-row');
        rows.forEach(row => {
            const name = row.querySelector('.skill-name').value;
            const valInput = row.querySelector('input[type="number"]');
            const grant = getGrant(name);
            const start = grant ? grant.value : 0;
            if (grant && grant.type === 'exact') {
                valInput.value = start;
                valInput.disabled = true;
            } else {
                if (start > maxFW) {
                    valInput.value = start;
                    valInput.disabled = true;
                } else {
                    valInput.disabled = false;
                    valInput.max = maxFW;
                    valInput.min = start;
                    if (parseInt(valInput.value, 10) < start) valInput.value = start;
                    if (parseInt(valInput.value, 10) > maxFW) valInput.value = maxFW;
                }
            }
        });
    }

    function sumUserFPSpends() {
        let sum = 0;
        const rows = skillsContainer.querySelectorAll('.skill-row');
        rows.forEach(row => {
            const name = row.querySelector('.skill-name').value;
            const valInput = row.querySelector('input[type="number"]');
            const val = parseInt(valInput.value, 10) || 0;
            const grant = getGrant(name);
            const start = grant ? grant.value : 0;
            if (!grant || grant.type === 'min') {
                const diff = val - start;
                if (diff > 0) sum += diff;
            }
        });
        return sum;
    }

    function getGrant(name) {
        if (state.raceGrants.skills[name]) return state.raceGrants.skills[name];
        if (state.cultureGrants.skills[name]) return state.cultureGrants.skills[name];
        return null;
    }

    function enforceEducationIntuitionExclusivity() {
        const intuitionRow = findSkillRow('Intuition');
        const bildungRow = findSkillRow('Bildung');
        const tooltip = "Ohne 'Kind zweier Welten' darf anfangs entweder Intuition oder Bildung > 0 sein.";
        const intuitionRaceGrant = !!state.raceGrants.skills['Intuition'];
        const bildungRaceGrant = !!state.raceGrants.skills['Bildung'];

        if (state.hasKindZweierWelten) {
            if (bildungRow) {
                const valInput = bildungRow.querySelector('input[type="number"]');
                valInput.disabled = false;
                valInput.title = '';
            }
            if (intuitionRow) {
                const valInput = intuitionRow.querySelector('input[type="number"]');
                valInput.disabled = false;
                valInput.title = '';
            }
            return;
        }

        if (intuitionRaceGrant) {
            if (bildungRow) bildungRow.remove();
            return;
        }
        if (bildungRaceGrant) {
            if (intuitionRow) intuitionRow.remove();
            return;
        }

        const intuitionVal = intuitionRow ? parseInt(intuitionRow.querySelector('input[type="number"]').value, 10) || 0 : 0;
        const bildungVal = bildungRow ? parseInt(bildungRow.querySelector('input[type="number"]').value, 10) || 0 : 0;
        if (intuitionVal >= 1 && bildungRow) {
            const valInput = bildungRow.querySelector('input[type="number"]');
            valInput.value = 0;
            valInput.disabled = true;
            valInput.title = tooltip;
        } else if (bildungVal >= 1 && intuitionRow) {
            const valInput = intuitionRow.querySelector('input[type="number"]');
            valInput.value = 0;
            valInput.disabled = true;
            valInput.title = tooltip;
        } else {
            if (bildungRow) {
                const valInput = bildungRow.querySelector('input[type="number"]');
                valInput.disabled = false;
                valInput.title = '';
            }
            if (intuitionRow) {
                const valInput = intuitionRow.querySelector('input[type="number"]');
                valInput.disabled = false;
                valInput.title = '';
            }
        }
    }

    function findSkillRow(name) {
        const rows = skillsContainer.querySelectorAll('.skill-row');
        for (const r of rows) {
            const n = r.querySelector('.skill-name').value;
            if (n === name) return r;
        }
        return null;
    }

    function basicsFilled() {
        return playerName.value.trim() && characterName.value.trim() && raceSelect.value && cultureSelect.value;
    }

    function updateContinueVisibility() {
        if (!continueBtn) return;
        if (basicsFilled()) {
            continueBtn.classList.remove('hidden');
        } else {
            continueBtn.classList.add('hidden');
        }
    }

    // === Advantage handling ===
    function lockAdvantage(name) {
        const option = [...advantagesSelect.options].find(o => o.value === name);
        if (option) {
            option.selected = true;
            option.disabled = true;
        }
    }

    function isAdvantageChosen(name) {
        return [...advantagesSelect.selectedOptions].some(o => o.value === name);
    }

    function countChosenAdvantagesExcl(excl) {
        return [...advantagesSelect.selectedOptions].filter(o => o.value !== excl).length;
    }

    function enforceAdvantageLimit() {
        const free = state.base.freeAdvantages;
        let chosen = countChosenAdvantagesExcl('Zäh');
        if (chosen > free) {
            const selected = [...advantagesSelect.selectedOptions].filter(o => o.value !== 'Zäh');
            for (let i = free; i < selected.length; i++) {
                selected[i].selected = false;
            }
            chosen = free;
        }
        [...advantagesSelect.options].forEach(o => {
            if (o.value === 'Zäh') return;
            o.disabled = !o.selected && chosen >= free;
        });
    }

    function countDisadvantages() {
        if (!disadvantagesSelect) return 0;
        return [...disadvantagesSelect.selectedOptions].length;
    }

    function enforceDisadvantageRequirement(advCount) {
        if (!disadvantagesSelect) return 0;
        let chosen = countDisadvantages();
        if (chosen > advCount) {
            const selected = [...disadvantagesSelect.selectedOptions];
            for (let i = advCount; i < selected.length; i++) {
                selected[i].selected = false;
            }
            chosen = advCount;
        }
        [...disadvantagesSelect.options].forEach(o => {
            o.disabled = !o.selected && chosen >= advCount;
        });
        return chosen;
    }

    // === Counters ===
    function updateAPCounter(val) {
        if (attributePointsEl) attributePointsEl.textContent = `Verfügbare Attributspunkte: ${val}`;
    }
    function updateFPCounter(val) {
        if (skillPointsEl) skillPointsEl.textContent = `Verfügbare Fertigkeitspunkte: ${val}`;
    }
    function updateAdvantageCounter(val) {
        if (advantageInput) advantageInput.value = val;
    }

    function updateSubmitButton(valid) {
        if (!submitButton) return;
        submitButton.disabled = !valid;
        submitButton.classList.toggle('cursor-not-allowed', !valid);
        submitButton.classList.toggle('bg-gray-400', !valid);
        submitButton.classList.toggle('bg-gray-600', !valid);
        submitButton.classList.toggle('bg-[#8B0116]', valid);
        submitButton.classList.toggle('dark:bg-red-400', valid);
    }

    // === Race/Culture handlers ===
    function handleRaceChange() {
        clearRace();
        if (raceSelect.value === 'Barbar') applyRaceBarbar();
        if (descriptionField && raceDescriptions[raceSelect.value] && !descriptionField.value.trim()) {
            descriptionField.value = raceDescriptions[raceSelect.value];
        }
        updateContinueVisibility();
        recomputeAll();
    }

    function clearRace() {
        state.race = null;
        state.raceAPBonus = 0;
        state.raceGrants.skills = {};
        barbarCombatContainer.classList.add('hidden');
        state.barbarCombatSkill = null;
        removeSkillBadgeSource('Rasse');
    }

    function applyRaceBarbar() {
        state.race = 'Barbar';
        state.raceAPBonus = 1;
        setFreeMin('Überleben', 1, 'Rasse');
        setFreeMin('Intuition', 1, 'Rasse');
        setCombatToggle();
    }

    function setCombatToggle() {
        barbarCombatContainer.classList.remove('hidden');
        barbarCombatSelect.addEventListener('change', () => {
            const newSkill = barbarCombatSelect.value;
            const prevSkill = state.barbarCombatSkill;
            delete state.raceGrants.skills['Nahkampf'];
            delete state.raceGrants.skills['Fernkampf'];
            if (prevSkill && prevSkill !== newSkill) {
                const prevRow = findSkillRow(prevSkill);
                if (prevRow) prevRow.remove();
            }
            state.barbarCombatSkill = newSkill;
            state.raceGrants.skills[newSkill] = { type: 'min', value: 1 };
            setFreeMin(newSkill, 1, 'Rasse');
            recomputeAll();
        });
        barbarCombatSelect.value = 'Nahkampf';
        state.barbarCombatSkill = 'Nahkampf';
        state.raceGrants.skills['Nahkampf'] = { type: 'min', value: 1 };
        setFreeMin('Nahkampf', 1, 'Rasse');
    }

    function handleCultureChange() {
        clearCulture();
        if (cultureSelect.value === 'Landbewohner') applyCultureLandbewohner();
        updateContinueVisibility();
        recomputeAll();
    }

    function clearCulture() {
        state.culture = null;
        state.cultureGrants.skills = {};
        removeSkillBadgeSource('Kultur');
    }

    function applyCultureLandbewohner() {
        state.culture = 'Landbewohner';
        setFreeExact('Beruf: Viehzüchter', 2, 'Kultur');
        setFreeExact('Beruf: Landwirt', 2, 'Kultur');
        setFreeMin('Kunde: Wetter', 1, 'Kultur');
    }

    // === Main recompute ===
    function recomputeAll() {
        lockAdvantage('Zäh');
        state.hasKindZweierWelten = isAdvantageChosen('Kind zweier Welten');
        let chosenAdv = countChosenAdvantagesExcl('Zäh');
        updateAdvantageCounter(state.base.freeAdvantages - chosenAdv);
        enforceAdvantageLimit();
        chosenAdv = countChosenAdvantagesExcl('Zäh');
        const chosenDisadv = enforceDisadvantageRequirement(chosenAdv);

        enforceAttributeCaps();
        const apRemaining = state.base.AP + state.raceAPBonus - sumUserAttributeIncrements();
        updateAPCounter(apRemaining);

        enforceEducationIntuitionExclusivity();
        enforceSkillCaps(state.base.maxFW);
        const fpRemaining = state.base.FP - sumUserFPSpends();
        updateFPCounter(fpRemaining);

        updateSkillOptions();

        const valid = apRemaining >= 0 && fpRemaining >= 0 && chosenDisadv === chosenAdv;
        updateSubmitButton(valid);
    }
});

