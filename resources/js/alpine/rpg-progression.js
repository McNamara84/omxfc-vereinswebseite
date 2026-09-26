export function buildOperation(operation) {
    const result = {
        type: operation.type,
        name: operation.name,
        steps: Number(operation.steps),
        target: operation.type === 'advantage' ? operation.target : '',
        reason: operation.reason,
    };
    if (operation.type === 'skill' && operation.specialization?.trim()) {
        result.name += ': ' + operation.specialization.trim();
    }
    if ((operation.type === 'skill' && operation.name === 'Sprachen')
        || (operation.type === 'advantage' && operation.name === 'Sprachbegabt')) {
        result.languages = operation.languageText.split('\n').map((name) => name.trim()).filter(Boolean);
    }
    if (operation.type === 'advantage' && operation.name === 'High-Tech-Ausrüstung') {
        result.items = operation.items;
    }
    return result;
}

export function rpgProgression(config) {
    return {
        config, busy: false, error: '', previewResult: null, generation: 0, nextId: 0,
        title: '', completedOn: config.today, hours: 6, minutes: 0, cycleBonus: 0,
        selectedCharacter: '', participants: [], operations: [],
        init() {
            if (config.mode === 'improve') this.addOperation();
        },
        invalidate() {
            this.previewResult = null;
            this.generation++;
        },
        addParticipant() {
            const character = config.characters.find((item) => item.id === Number(this.selectedCharacter));
            if (!character || this.participants.some((item) => item.character_id === character.id)) return;
            this.participants.push({
                character_id: character.id, label: character.name, survived: true,
                roleplay: 0, humor: false, rescue: false, unwounded: false, points: '', reason: '',
            });
            this.selectedCharacter = '';
            this.invalidate();
        },
        addOperation() {
            if (this.operations.length >= 30) return;
            this.operations.push({
                uid: this.nextId++, type: 'skill', name: 'Nahkampf', steps: 1, target: '',
                reason: '', specialization: '', languageText: (config.languages ?? []).join('\n'),
                items: ['', '', '', ''],
            });
            this.invalidate();
        },
        changeType(operation) {
            operation.name = config.names[operation.type]?.[0] ?? '';
            operation.steps = 1;
            operation.target = '';
            operation.specialization = '';
            this.invalidate();
        },
        payload() {
            if (config.mode === 'award') {
                return {
                    submission_key: config.submission_key, title: this.title,
                    completed_on: this.completedOn, minutes: Number(this.hours) * 60 + Number(this.minutes),
                    cycle_bonus: Number(this.cycleBonus),
                    participants: this.participants.map(({ label, ...participant }) => ({
                        ...participant, points: participant.points === '' ? null : Number(participant.points),
                        roleplay: Number(participant.roleplay),
                    })),
                };
            }
            return {
                submission_key: config.submission_key, revision: config.revision,
                operations: this.operations.map(buildOperation),
            };
        },
        async send(url) {
            const response = await fetch(url, {
                method: 'POST', credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json', Accept: 'application/json',
                    'X-CSRF-TOKEN': this.$root.querySelector('input[name="_token"]').value,
                },
                body: JSON.stringify(this.payload()),
            });
            const data = await response.json().catch(() => ({}));
            if (!response.ok) {
                const messages = data.errors ? Object.values(data.errors).flat() : [];
                throw new Error(messages.length ? messages.join('\n') : 'Die Anfrage konnte nicht gespeichert werden. Bitte die Seite neu laden und erneut versuchen.');
            }
            return data;
        },
        async preview() {
            if (this.busy) return;
            this.busy = true;
            this.error = '';
            const generation = this.generation;
            try {
                const result = await this.send(config.previewUrl);
                if (generation === this.generation) this.previewResult = result;
            } catch (error) {
                this.error = error.message;
                this.$nextTick(() => this.$refs.error?.focus());
            } finally {
                this.busy = false;
            }
        },
        async save() {
            if (this.busy || !this.previewResult) return;
            this.busy = true;
            this.error = '';
            try {
                const result = await this.send(config.saveUrl);
                window.location.assign(result.redirect);
            } catch (error) {
                this.error = error.message;
                this.$nextTick(() => this.$refs.error?.focus());
                this.busy = false;
            }
        },
    };
}

document.addEventListener('alpine:init', () => {
    window.Alpine.data('rpgProgression', rpgProgression);
});
