export function rpgInventory(config) {
    return {
        config, kind: config.state.confirmed ? 'grant' : 'initialize', reason: '', occurredOn: config.today,
        incoming: [], outgoing: [], setRows: [], amount: config.state.confirmed ? '' : 0, catalogSearch: '', catalogCategory: '',
        cashDirection: 'income', externalParty: '', otherId: '', winner: 'none', successPoints: 0,
        priceReason: '', minutes: 0, versionChange: { source: '', target: '', quantity: 1 }, metadata: {}, reversalOf: null,
        busy: false, error: '', previewResult: null, generation: 0, nextId: 0,
        init() { this.resetKind(); },
        invalidate() { this.previewResult = null; this.generation++; },
        resetKind() {
            this.incoming = []; this.outgoing = []; this.setRows = [];
            this.amount = ['initialize', 'correct'].includes(this.kind) ? (config.state.bucks ?? 0) : '';
            this.metadata = this.kind === 'equip' ? {
                clothing: config.state.clothing, armor: config.state.armor,
                shield: config.state.shield, notes: config.state.notes,
            } : {};
            this.versionChange = { source: '', target: '', quantity: 1 };
            this.reversalOf = null; this.otherId = ''; this.externalParty = '';
            this.winner = 'none'; this.successPoints = 0; this.priceReason = ''; this.minutes = 0;
            if (['initialize', 'correct'].includes(this.kind)) {
                this.setRows = Object.entries(config.state.stocks).map(([id, quantity]) => ({ uid: this.nextId++, id, quantity }));
            }
            this.invalidate();
        },
        addRow(side) { this[side].push({ uid: this.nextId++, id: '', quantity: 1 }); this.invalidate(); },
        beginReversal(id, otherId) {
            this.kind = 'reversal'; this.resetKind(); this.reversalOf = id;
            this.otherId = otherId ?? ''; this.reason = ''; this.invalidate();
            this.$nextTick(() => this.$refs.reason?.focus());
        },
        isTrade() { return ['purchase', 'sale', 'swap'].includes(this.kind); },
        catalogEntries(selected) {
            const query = this.catalogSearch.trim().toLocaleLowerCase('de');
            return Object.entries(config.catalog).filter(([id, row]) => id === selected
                || ((!this.catalogCategory || (row.category ?? row.kind) === this.catalogCategory)
                    && row.name.toLocaleLowerCase('de').includes(query)));
        },
        payload() {
            const peer = config.peers.find((row) => row.id === Number(this.otherId));
            const rows = (values) => values.map(({ id, quantity }) => ({ id, quantity: Number(quantity) }));
            return {
                submission_key: config.submissionKey, revision: config.revision, catalog_version: config.catalogVersion,
                kind: this.kind, reason: this.reason, occurred_on: this.occurredOn,
                incoming: rows(this.incoming), outgoing: rows(this.outgoing), set: rows(this.setRows),
                metadata: this.metadata, amount: this.amount === '' ? null : Number(this.amount),
                cash_direction: this.cashDirection, other_character_id: peer?.id ?? null,
                other_revision: peer?.revision ?? null, external_party: peer ? '' : this.externalParty,
                winner: this.winner, success_points: Number(this.successPoints), price_reason: this.priceReason,
                minutes: Number(this.minutes), reversal_of: this.reversalOf,
                preview_hash: this.previewResult?.preview_hash ?? null,
                ...(this.kind === 'item_version_change' ? { version_change: { ...this.versionChange, quantity: Number(this.versionChange.quantity) } } : {}),
            };
        },
        label(id) { return Number(id) === config.characterId ? 'Dieser Charakter' : (config.peers.find((row) => row.id === Number(id))?.name ?? 'Gegenpartei'); },
        display(value) {
            if (value === null) return 'Unbekannt';
            if (typeof value === 'boolean') return value ? 'Ja' : 'Nein';
            return config.catalog['item:' + value]?.name ?? config.catalog['clothing:' + value]?.name ?? (value === '' ? 'Keine' : String(value));
        },
        async send(url) {
            const response = await fetch(url, { method: 'POST', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json',
                    'X-CSRF-TOKEN': this.$root.querySelector('input[name="_token"]').value },
                body: JSON.stringify(this.payload()),
            });
            const data = await response.json().catch(() => ({}));
            if (!response.ok) throw new Error(data.errors ? Object.values(data.errors).flat().join('\n') : 'Die Anfrage ist fehlgeschlagen. Bitte neu laden.');
            return data;
        },
        async preview() {
            if (this.busy) return;
            this.busy = true; this.error = ''; const generation = this.generation;
            try { const result = await this.send(config.previewUrl); if (generation === this.generation) this.previewResult = result; }
            catch (error) { this.error = error.message; this.$nextTick(() => this.$refs.error?.focus()); }
            finally { this.busy = false; }
        },
        async save() {
            if (this.busy || !this.previewResult) return;
            this.busy = true; this.error = '';
            try { const result = await this.send(config.saveUrl); window.location.assign(result.redirect); }
            catch (error) { this.error = error.message; this.invalidate(); this.busy = false; this.$nextTick(() => this.$refs.error?.focus()); }
        },
    };
}

export function registerRpgInventory() { window.Alpine?.data('rpgInventory', rpgInventory); }
