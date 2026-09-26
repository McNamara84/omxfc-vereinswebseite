export function createCheckPoller({ url, onData, onError, interval = 5000 }) {
    let timer = null;
    let controller = null;
    let version = 0;
    let stopped = false;
    let paused = false;
    const clear = () => { clearTimeout(timer); timer = null; };
    const schedule = () => {
        clear();
        if (!stopped && !paused && !document.hidden) timer = setTimeout(refresh, interval);
    };
    async function refresh() {
        clear();
        if (stopped || paused || document.hidden || controller) return;
        const current = ++version;
        const active = new AbortController();
        controller = active;
        try {
            const response = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin', cache: 'no-store', signal: active.signal });
            if (current !== version || stopped) return;
            if ([401, 403, 419].includes(response.status)) {
                stopped = true;
                onError('Deine Anmeldung oder Berechtigung hat sich geändert. Bitte die Seite neu öffnen.', true);
                return;
            }
            if (!response.ok) throw new Error('Aktualisierung fehlgeschlagen. Bitte erneut versuchen.');
            const data = await response.json();
            if (current === version && !stopped) onData(data);
        } catch (error) {
            if (current === version && !stopped && error.name !== 'AbortError') onError('Verbindung unterbrochen. Die Aktualisierung wird erneut versucht.', false);
        } finally {
            if (controller === active) controller = null;
            if (current === version) schedule();
        }
    }
    function invalidate() {
        version++;
        clear();
        controller?.abort();
        controller = null;
    }
    function visibility() {
        if (document.hidden) invalidate();
        else void refresh();
    }
    document.addEventListener('visibilitychange', visibility);
    schedule();
    return {
        refresh,
        pause() { paused = true; invalidate(); },
        resume() { paused = false; schedule(); },
        destroy() { stopped = true; invalidate(); document.removeEventListener('visibilitychange', visibility); },
    };
}
