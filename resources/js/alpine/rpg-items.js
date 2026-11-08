export function rpgItems(config) {
    return {
        config, busy: false, error: '', result: null, generation: 0,
        input: { name: '', description: '', category: 'low_tech', size: 'medium', rarity: 'common', beautiful: false,
            material: 0, high_tech: false, tech_factor: 5, utility: 1, bucks: 1, reference: '', minimum_education: 0,
            masterwork: false, attributes: ['st'], hands: 1, throwable: false, weapon_type: 'Projektilwaffe', precision: 0,
            fire_rate: 'E', range_increment: 10, max_range: 50, magazine: 0, ammunition_id: 'ammo:pfeile',
            ammunition_name: '', ammunition_unit: 'Schuss', effectiveness: 0, movement_modifier: 0,
            persons_min: 1, persons_max: 1, protection: 0, land_speed: 0, water_speed: 0, air_speed: 0, hover: false, ...config.input,
            tech_factor: Math.max(5, Number(config.input.tech_factor ?? 5)) },
        invalidate() { this.result = null; this.generation++; },
        categoryChanged() {
            this.input.high_tech = this.input.category === 'high_tech';
            this.invalidate();
        },
        fields() {
            let fields = config.fields[this.input.category] ?? [];
            if (this.input.category === 'melee_weapons' && !this.input.throwable) {
                fields = fields.filter((field) => !config.fields.ranged_weapons.includes(field) || field === 'hands');
            }
            return [...config.common, ...fields];
        },
        has(field) { return this.fields().includes(field); },
        payload() {
            const input = Object.fromEntries(this.fields().map((key) => [key, this.input[key]]));
            input.tech_factor = input.high_tech ? Number(input.tech_factor) : 1;
            if (this.input.category === 'ranged_weapons' || (this.input.category === 'melee_weapons' && this.input.throwable)) {
                if (this.input.category === 'melee_weapons') input.weapon_type = 'Wurfgeschoss';
                if (input.weapon_type === 'Wurfgeschoss') { input.magazine = 0; input.fire_rate = 'E'; }
                if (input.weapon_type === 'Schießpulverwaffe') input.minimum_education = 2;
                if (input.weapon_type === 'Energiewaffe') input.minimum_education = 3;
            }
            return { input, revision: config.revision, submission_key: config.submissionKey, preview_hash: this.result?.preview_hash ?? null };
        },
        async send(url) {
            const response = await fetch(url, { method: 'POST', credentials: 'same-origin', headers: {
                'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': this.$root.querySelector('[name="_token"]').value,
            }, body: JSON.stringify(this.payload()) });
            const data = await response.json();
            if (!response.ok) throw new Error(data.errors ? Object.values(data.errors).flat().join('\n') : 'Die Anfrage ist fehlgeschlagen. Bitte neu laden.');
            return data;
        },
        async preview() {
            if (this.busy) return;
            this.busy = true; this.error = ''; const generation = this.generation;
            try { const result = await this.send(config.previewUrl); if (generation === this.generation) this.result = result; }
            catch (error) { this.error = error.message; this.$nextTick(() => this.$refs.error?.focus()); }
            finally { this.busy = false; }
        },
        async save() {
            if (this.busy || !this.result) return;
            this.busy = true; this.error = '';
            try { const data = await this.send(config.saveUrl); window.location.assign(data.redirect); }
            catch (error) { this.error = error.message; this.invalidate(); this.$nextTick(() => this.$refs.error?.focus()); }
            finally { this.busy = false; }
        },
    };
}

export function registerRpgItems() { window.Alpine?.data('rpgItems', rpgItems); }
