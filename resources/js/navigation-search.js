export function navigationSpotlight(config) {
    return {
        value: '', results: [], loading: false, error: '', searched: false,
        controller: null, timer: null, focusTimer: null, generation: 0, trigger: null,
        show() {
            this.trigger = document.activeElement;
            this.reset();
            window.dispatchEvent(new CustomEvent('navigation-search-opening'));
            window.dispatchEvent(new CustomEvent('member-drawer-close'));
            // Open during the originating key/click event, before its keyup changes focus.
            if (!this.$refs.dialog.open) this.$refs.dialog.showModal();
            this.$refs.input.focus();
            // As in maryUI Spotlight, settle native dialog and drawer focus first.
            this.focusTimer = setTimeout(() => {
                if (this.$refs.dialog.open) this.$refs.input.focus();
                this.focusTimer = null;
            }, 100);
        },
        reset() {
            clearTimeout(this.timer);
            clearTimeout(this.focusTimer);
            this.focusTimer = null;
            this.controller?.abort();
            this.generation++;
            this.value = '';
            this.results = [];
            this.error = '';
            this.loading = false;
            this.searched = false;
        },
        close(restore = true) {
            const wasOpen = this.$refs.dialog.open;
            this.$refs.dialog.close();
            this.reset();
            if (restore && wasOpen) this.$nextTick(() => {
                const target = this.trigger?.isConnected && this.trigger.getClientRects().length
                    ? this.trigger : document.querySelector('[data-testid="navigation-search-trigger"]');
                target?.focus();
            });
        },
        shortcut(event) {
            if (!(event.ctrlKey || event.metaKey) || event.altKey || event.shiftKey || event.repeat || event.key.toLowerCase() !== 'k') return;
            event.preventDefault();
            this.show();
        },
        schedule() {
            clearTimeout(this.timer);
            this.controller?.abort();
            this.generation++;
            this.results = [];
            this.error = '';
            this.loading = false;
            this.searched = false;
            if (this.value.trim().length >= 2) this.timer = setTimeout(() => this.search(), 250);
        },
        async search() {
            const query = this.value.trim();
            if (query.length < 2) return;
            const generation = ++this.generation;
            this.controller?.abort();
            this.controller = new AbortController();
            this.loading = true;
            this.error = '';
            try {
                const params = new URLSearchParams({ search: query, scope: config.scope });
                const response = await fetch(`${config.url}?${params}`, {
                    signal: this.controller.signal, headers: { Accept: 'application/json' }, cache: 'no-store',
                });
                if (!response.ok) throw new Error('search');
                const results = await response.json();
                if (!Array.isArray(results)) throw new Error('response');
                if (generation === this.generation) {
                    this.results = results;
                    this.searched = true;
                }
            } catch (error) {
                if (generation === this.generation && error.name !== 'AbortError') {
                    this.results = [];
                    this.error = 'Die Suche ist gerade nicht erreichbar. Bitte versuche es erneut.';
                }
            } finally {
                if (generation === this.generation) this.loading = false;
            }
        },
        move(direction) {
            const links = [...this.$refs.results.querySelectorAll('a')];
            if (!links.length) return;
            const index = links.indexOf(document.activeElement);
            const next = index < 0 ? (direction > 0 ? 0 : links.length - 1) : (index + direction + links.length) % links.length;
            links[next].focus();
        },
        destroy() { this.reset(); },
    };
}

const register = () => {
    if (typeof window.Alpine?.data === 'function') window.Alpine.data('navigationSpotlight', navigationSpotlight);
};
document.addEventListener('alpine:init', register);
if (window.Alpine) register();
