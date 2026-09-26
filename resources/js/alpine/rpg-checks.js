import { createCheckPoller } from '../rpg-check-polling';

async function post(url, payload, token) {
    const response = await fetch(url, {
        method: 'POST', credentials: 'same-origin', cache: 'no-store',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': token },
        body: JSON.stringify(payload),
    });
    const data = await response.json().catch(() => ({}));
    if (!response.ok) {
        const error = new Error(data.errors ? Object.values(data.errors).flat().join('\n') : 'Die Anfrage konnte nicht verarbeitet werden. Bitte die Seite neu öffnen.');
        error.status = response.status;
        throw error;
    }
    return data;
}

export function rpgCheckForm(config) {
    let nextId = 0;
    const spec = () => ({ uid: nextId++, check_type: 'attribute', attribute_key: 'wa', skill_name: '', modifiers: [] });
    return {
        config, mode: 'fixed', visibility: '', description: '', difficulty: 10,
        participants: [], common: spec(), selectedCharacter: '', busy: false, error: '', previewResult: null, generation: 0,
        invalidate() { this.previewResult = null; this.generation++; },
        addParticipant() {
            const character = config.characters.find((c) => c.id === Number(this.selectedCharacter));
            if (!character || this.participants.some((p) => p.character_id === character.id) || this.participants.length >= (this.mode === 'opposed' ? 2 : 100)) return;
            this.participants.push({ character_id: character.id, label: character.label, settings: spec() });
            this.selectedCharacter = '';
            this.invalidate();
        },
        changeMode() { this.participants = []; this.invalidate(); },
        specifications() { return this.mode === 'fixed' ? [this.common] : this.participants.map((p) => p.settings); },
        specificationLabel(settings) { return this.mode === 'fixed' ? 'Gemeinsame Vorgaben' : this.participants.find((p) => p.settings.uid === settings.uid)?.label; },
        availableSkills(settings) {
            const selected = this.mode === 'fixed' ? this.participants : this.participants.filter((p) => p.settings.uid === settings.uid);
            const lists = selected.map((p) => config.characters.find((c) => c.id === p.character_id)?.skills ?? config.skills);
            return lists.length ? lists[0].filter((name) => lists.every((list) => list.includes(name))) : config.skills;
        },
        addModifier(settings) {
            if (settings.modifiers.length < 20) settings.modifiers.push({ uid: nextId++, value: 0, description: '' });
            this.invalidate();
        },
        payload(withRevisions = false) {
            return {
                submission_key: config.submission_key, mode: this.mode, description: this.description,
                visibility: this.visibility, difficulty: this.mode === 'fixed' ? Number(this.difficulty) : null,
                participants: this.participants.map((p) => {
                    const settings = this.mode === 'fixed' ? this.common : p.settings;
                    return {
                        character_id: p.character_id,
                        ...(withRevisions ? { revision: this.previewResult.participants.find((row) => row.rpg_character_id === p.character_id).character_revision } : {}),
                        check_type: settings.check_type, attribute_key: settings.attribute_key,
                        skill_name: settings.check_type === 'skill' ? settings.skill_name : null,
                        modifiers: settings.modifiers.map((m) => ({ value: Number(m.value), description: m.description })),
                    };
                }),
            };
        },
        async preview() {
            if (this.busy) return;
            this.busy = true; this.error = '';
            const generation = this.generation;
            try {
                const result = await post(config.preview_url, this.payload(), this.$root.querySelector('[name="_token"]').value);
                if (generation === this.generation) this.previewResult = result;
            } catch (error) { this.error = error.message; }
            finally { this.busy = false; }
        },
        async save() {
            if (this.busy || !this.previewResult) return;
            this.busy = true; this.error = '';
            try {
                const result = await post(config.save_url, this.payload(true), this.$root.querySelector('[name="_token"]').value);
                window.location.assign(result.redirect);
            } catch (error) { this.error = error.message; this.busy = false; }
        },
    };
}

export function rpgChecks(config) {
    let poller;
    return {
        data: config.initial, error: '', busy: false, announcement: '', reason: '', resolution: 'neutral', actionId: null,
        init() {
            poller = createCheckPoller({
                url: config.url,
                onData: (data) => {
                    const next = config.detail ? { ...this.data, checks: [data] } : data;
                    if (JSON.stringify(next) !== JSON.stringify(this.data)) {
                        this.data = next;
                        this.announcement = 'Die Proben wurden aktualisiert.';
                    }
                    this.error = '';
                },
                onError: (message, revoked) => {
                    this.error = message;
                    if (revoked) this.data = { checks: [], recent: [], total: 0 };
                },
            });
        },
        destroy() { poller?.destroy(); },
        refresh() { return poller?.refresh(); },
        resultLabel(kind, status = 'pending') {
            return { success: 'Erfolg', failure: 'Fehlgeschlagen', critical_success: 'Kritischer Erfolg', fumble: 'Patzer' }[kind]
                ?? ({ completed: 'Ausgang durch die Spielleitung entschieden', cancelled: 'Vergleich storniert', awaiting_decision: 'Gleichstand' }[status] ?? 'Auswertung ausstehend');
        },
        outcome(check) {
            if (check.mode !== 'opposed' || check.status !== 'completed') return '';
            return check.winner_position ? 'Gewonnen: ' + check.participants.find((p) => p.position === check.winner_position)?.character_name : 'Unentschieden / anderer Ausgang';
        },
        async act(url, payload = {}) {
            if (this.busy) return;
            this.busy = true; this.error = ''; poller?.pause();
            try {
                const check = await post(url, payload, this.$root.querySelector('[name="_token"]').value);
                this.data.checks = this.data.checks.map((c) => c.id === check.id ? check : c);
                this.actionId = null; this.reason = '';
                this.announcement = check.status_label;
            } catch (error) {
                this.error = error.message;
                if ([401, 403, 419].includes(error.status)) {
                    this.data = { checks: [], recent: [], total: 0 };
                    poller?.destroy();
                }
            } finally { this.busy = false; poller?.resume(); }
        },
    };
}

document.addEventListener('alpine:init', () => {
    window.Alpine.data('rpgCheckForm', rpgCheckForm);
    window.Alpine.data('rpgChecks', rpgChecks);
});
