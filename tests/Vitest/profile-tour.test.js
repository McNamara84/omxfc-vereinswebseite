import { clearTourError, profileFormIsDirty, renderInvitation, renderProfileReward, renderTourError, requireSavedProfile } from '@/tours/profile';

const frame = () => new Promise(resolve => requestAnimationFrame(resolve));
async function flush() {
    for (let i = 0; i < 5; i++) await frame();
}

function render() {
    document.body.innerHTML = `
        <div id="tour-runner-root" data-tour-current-url="/current" data-tour-start-url-template="/__TOUR_ASSIGNMENT__/start"
            data-tour-progress-url-template="/__TOUR_ASSIGNMENT__/progress" data-tour-dismiss-url-template="/__TOUR_ASSIGNMENT__/dismiss"
            data-tour-complete-url-template="/__TOUR_ASSIGNMENT__/complete">
            <div id="tour-runner-backdrop" class="hidden"></div><div id="tour-runner-highlight" class="hidden"></div>
            <section id="tour-runner-panel" class="hidden"><h2 id="tour-runner-title"></h2><p id="tour-runner-description"></p>
                <p id="tour-runner-reward"></p><div id="tour-runner-error" tabindex="-1"></div>
                <div id="tour-runner-progress"><span id="tour-runner-progress-label"></span><div id="tour-runner-progress-bar"></div></div>
                <span id="tour-runner-counter"></span>
                <button id="tour-runner-start">Start</button><button id="tour-runner-skip">Später</button>
                <button id="tour-runner-back">Zurück</button><button id="tour-runner-next">Weiter</button>
                <button id="tour-runner-complete">Abschließen</button>
            </section>
        </div>
        <div data-profile-series-form><input id="lieblingsmutation"><div id="profile-complete"></div></div>`;
}

function payload() {
    return {
        assignment_id: 12, key: 'profilpflege', status: 'pending', start_mode: 'invitation', entry_url: '/user/profile',
        active_in_session: false, reward_received: false, profile: { filled: 10, total: 11, missing: { lieblingsmutation: 'Lieblingsmutation' } },
        steps: [
            { key: 'profile-field-lieblingsmutation', title: 'Lieblingsmutation', required_target: true, selectors: { desktop: '#lieblingsmutation', mobile: '#lieblingsmutation' } },
            { key: 'profile-complete', title: 'Abschluss', required_target: true, selectors: { desktop: '#profile-complete', mobile: '#profile-complete' } },
        ],
    };
}

function deferred() {
    let resolve;
    let reject;
    const promise = new Promise((res, rej) => { resolve = res; reject = rej; });
    return { promise, resolve, reject };
}

describe('profile tour presentation', () => {
    beforeEach(render);
    it('announces all reward conditions and handles already rewarded repeats', () => {
        const tour = payload();
        renderInvitation(tour);
        expect(document.getElementById('tour-runner-reward').textContent).toContain('Einmalig 5 Baxx');
        expect(document.getElementById('tour-runner-reward').textContent).toContain('10 von 11');
        expect(document.getElementById('tour-runner-description').textContent).toContain('Andere Mitglieder');
        renderInvitation({ ...tour, reward_received: true, status: 'in_progress' });
        expect(document.getElementById('tour-runner-reward').textContent).toContain('bereits erhalten');
        expect(document.getElementById('tour-runner-start').textContent).toBe('Fortsetzen');
        renderProfileReward(null);
        expect(document.getElementById('tour-runner-reward').classList.contains('hidden')).toBe(true);
    });

    it('renders validation errors as safe, focusable field links', () => {
        renderTourError({ response: { data: { errors: { lieblingsmutation: ['Bitte speichern'], tour: '<script>bad</script>' } } } });
        expect(document.querySelector('[data-tour-missing-field]').textContent).toBe('Bitte speichern');
        expect(document.querySelector('#tour-runner-error script')).toBeNull();
        expect(document.activeElement.id).toBe('tour-runner-error');
        clearTourError();
        expect(document.getElementById('tour-runner-error').classList.contains('hidden')).toBe(true);
        renderTourError(new Error('Netzwerkfehler'));
        expect(document.getElementById('tour-runner-error').textContent).toContain('Netzwerkfehler');
        renderTourError({ response: { data: { message: 'Neue Tour starten' } } });
        expect(document.getElementById('tour-runner-error').textContent).toBe('Neue Tour starten');
        renderTourError(null);
        expect(document.getElementById('tour-runner-error').textContent).toContain('fehlgeschlagen');
    });

    it('requires saving dirty form inputs and tolerates absent markup', () => {
        expect(profileFormIsDirty()).toBe(false);
        requireSavedProfile();
        document.querySelector('[data-profile-series-form]').dataset.unsaved = 'true';
        expect(() => requireSavedProfile()).toThrow('nicht gespeichert');
        document.body.replaceChildren();
        renderInvitation(payload());
        renderTourError(new Error('offline'));
        clearTourError();
        renderProfileReward(payload());
        requireSavedProfile();
    });
});

describe('profile tour workflow', () => {
    let tour;
    let completeResult;
    let completeError;
    beforeAll(async () => {
        document.body.replaceChildren();
        await import('@/tours/runner');
    });
    beforeEach(async () => {
        render();
        tour = payload();
        completeResult = { completed: true, reward: { status: 'awarded', points_awarded: 5 } };
        completeError = null;
        HTMLElement.prototype.scrollIntoView = vi.fn();
        window.toast = vi.fn();
        window.Livewire = { navigate: vi.fn() };
        window.omxfcHttp = {
            get: vi.fn(async () => ({ data: { tour: structuredClone(tour) } })),
            post: vi.fn(async (url, data) => {
                if (url.endsWith('/complete')) {
                    if (completeError) throw completeError;
                    return { data: completeResult };
                }
                if (url.endsWith('/start')) tour = { ...tour, status: 'in_progress', active_in_session: true };
                if (url.endsWith('/progress')) tour.current_step_key = data.step_key;
                return { data: { tour: structuredClone(tour) } };
            }),
        };
        document.dispatchEvent(new CustomEvent('livewire:navigated'));
        await flush();
    });
    afterEach(() => {
        document.body.replaceChildren();
    });

    const click = async id => { document.getElementById(id).click(); await flush(); };
    const startAndFinish = async () => { await click('tour-runner-start'); await click('tour-runner-next'); };

    it('waits for explicit start and navigates from dashboard to the profile', async () => {
        expect(window.omxfcHttp.post).not.toHaveBeenCalled();
        document.querySelector('[data-profile-series-form]').remove();
        await click('tour-runner-start');
        expect(window.omxfcHttp.post).toHaveBeenCalledWith('/12/start');
        expect(window.Livewire.navigate).toHaveBeenCalledWith('/user/profile');
    });

    it('only reports a reward after the completion server confirms it', async () => {
        await startAndFinish();
        await click('tour-runner-complete');
        expect(window.toast).toHaveBeenCalledWith(expect.objectContaining({ toast: expect.objectContaining({ description: '5 Baxx wurden dir gutgeschrieben.' }) }));
        expect(document.getElementById('tour-runner-panel').classList.contains('hidden')).toBe(true);
    });

    it('keeps an incomplete tour open, links to missing field and allows retry', async () => {
        await startAndFinish();
        completeError = { response: { status: 422, data: { errors: { lieblingsmutation: ['Lieblingsmutation: Bitte ergänzen und speichern.'] } } } };
        await click('tour-runner-complete');
        expect(window.toast).not.toHaveBeenCalled();
        expect(document.getElementById('tour-runner-panel').classList.contains('hidden')).toBe(false);
        document.querySelector('[data-tour-missing-field]').click();
        await flush();
        expect(document.activeElement.id).toBe('lieblingsmutation');
        expect(tour.current_step_key).toBe('profile-field-lieblingsmutation');
        completeError = null;
        await click('tour-runner-next');
        await click('tour-runner-complete');
        expect(window.toast).toHaveBeenCalled();
    });

    it('blocks unsaved changes and refreshes authoritative completion after saving', async () => {
        await startAndFinish();
        document.getElementById('lieblingsmutation').dispatchEvent(new Event('input', { bubbles: true }));
        await click('tour-runner-complete');
        expect(window.omxfcHttp.post.mock.calls.some(([url]) => url.endsWith('/complete'))).toBe(false);
        expect(document.getElementById('tour-runner-error').textContent).toContain('nicht gespeichert');
        tour.profile = { filled: 11, total: 11, missing: [] };
        window.dispatchEvent(new CustomEvent('profile-series-saved'));
        await flush();
        expect(document.getElementById('tour-runner-reward').textContent).toContain('11 von 11');
        await click('tour-runner-complete');
        expect(window.toast).toHaveBeenCalled();
    });

    it('handles already awarded repeats and network errors without false rewards', async () => {
        await startAndFinish();
        completeError = new Error('Offline');
        await click('tour-runner-complete');
        expect(document.getElementById('tour-runner-error').textContent).toContain('Offline');
        expect(window.toast).not.toHaveBeenCalled();
        completeError = null;
        completeResult.reward = { status: 'already_awarded', points_awarded: 0 };
        await click('tour-runner-complete');
        expect(window.toast.mock.calls[0][0].toast.description).toContain('bereits erhalten');
    });

    it('supports later and Escape without completing or awarding', async () => {
        await click('tour-runner-skip');
        expect(window.omxfcHttp.post).toHaveBeenCalledWith('/12/dismiss');
        document.dispatchEvent(new CustomEvent('livewire:navigated'));
        await flush();
        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));
        await flush();
        expect(window.omxfcHttp.post.mock.calls.filter(([url]) => url.endsWith('/dismiss'))).toHaveLength(2);
        expect(window.omxfcHttp.post.mock.calls.some(([url]) => url.endsWith('/complete'))).toBe(false);
    });

    it('disables navigation while progress is saving and accepts the next click afterwards', async () => {
        let release;
        const pending = new Promise(resolve => { release = resolve; });
        const original = window.omxfcHttp.post.getMockImplementation();
        let delay = true;
        window.omxfcHttp.post.mockImplementation(async (url, data) => {
            if (url.endsWith('/progress') && delay) await pending;
            return original(url, data);
        });
        await click('tour-runner-start');
        expect(document.getElementById('tour-runner-next').disabled).toBe(true);
        await click('tour-runner-next');
        expect(window.omxfcHttp.post.mock.calls.filter(([url]) => url.endsWith('/progress'))).toHaveLength(1);
        delay = false;
        release();
        await flush();
        expect(document.getElementById('tour-runner-next').disabled).toBe(false);
        await click('tour-runner-next');
        expect(tour.current_step_key).toBe('profile-complete');
    });

    it('does not skip a missing required field into successful completion', async () => {
        document.getElementById('lieblingsmutation').remove();
        await click('tour-runner-start');
        expect(document.getElementById('tour-runner-error').textContent).toContain('Profilfeld');
        expect(window.omxfcHttp.post.mock.calls.some(([url]) => url.endsWith('/complete'))).toBe(false);
    });

    it('keeps the tour open when a successful HTTP response does not confirm completion', async () => {
        await startAndFinish();
        completeResult = {};
        await click('tour-runner-complete');
        expect(window.toast).not.toHaveBeenCalled();
        expect(document.getElementById('tour-runner-error').textContent).toContain('nicht bestätigt');
        expect(document.getElementById('tour-runner-panel').classList.contains('hidden')).toBe(false);
    });

    it.each(['start', 'complete', 'dismiss'])('ignores a stale %s response after navigating to another tour', async action => {
        if (action === 'complete') await startAndFinish();
        let release;
        const pending = new Promise(resolve => { release = resolve; });
        const original = window.omxfcHttp.post.getMockImplementation();
        window.omxfcHttp.post.mockImplementation(async (url, data) => {
            const response = await original(url, data);
            if (url.endsWith(`/${action}`)) await pending;
            return response;
        });
        document.getElementById(`tour-runner-${action === 'dismiss' ? 'skip' : action}`).click();
        await flush();
        tour = { ...payload(), assignment_id: 99 };
        document.dispatchEvent(new CustomEvent('livewire:navigated'));
        await flush();
        release();
        await flush();
        expect(window.toast).not.toHaveBeenCalled();
        expect(document.getElementById('tour-runner-start').classList.contains('hidden')).toBe(false);
        expect(document.getElementById('tour-runner-panel').classList.contains('hidden')).toBe(false);
        await click('tour-runner-start');
        expect(window.omxfcHttp.post).toHaveBeenCalledWith('/99/start');
    });

    it.each(['start', 'complete', 'dismiss', 'progress'])('ignores a stale %s failure without replacing errors or stealing focus', async action => {
        if (action === 'complete') await startAndFinish();
        if (action === 'progress') await click('tour-runner-start');
        const request = deferred();
        const original = window.omxfcHttp.post.getMockImplementation();
        window.omxfcHttp.post.mockImplementation((url, data) => url === `/12/${action}` ? request.promise : original(url, data));
        const button = { dismiss: 'skip', progress: 'next' }[action] ?? action;
        await click(`tour-runner-${button}`);
        expect(window.omxfcHttp.post).toHaveBeenCalledWith(`/12/${action}`, ...(action === 'progress' ? [{ step_key: 'profile-complete' }] : []));

        tour = { ...payload(), assignment_id: 99 };
        document.dispatchEvent(new CustomEvent('livewire:navigated'));
        await flush();
        renderTourError(new Error('Aktueller Hinweis'));
        document.getElementById('lieblingsmutation').focus();
        request.reject(new Error('Veralteter Fehler'));
        await flush();

        expect(document.getElementById('tour-runner-error').textContent).toBe('Aktueller Hinweis');
        expect(document.activeElement.id).toBe('lieblingsmutation');
        expect(window.toast).not.toHaveBeenCalled();
        await click('tour-runner-start');
        expect(window.omxfcHttp.post).toHaveBeenCalledWith('/99/start');
    });

    it('ignores a superseded fetch failure after navigation', async () => {
        const request = deferred();
        window.omxfcHttp.get.mockImplementationOnce(() => request.promise);
        document.dispatchEvent(new CustomEvent('livewire:navigated'));
        await flush();
        tour = { ...payload(), assignment_id: 99 };
        document.dispatchEvent(new CustomEvent('livewire:navigated'));
        await flush();
        clearTourError();
        document.getElementById('lieblingsmutation').focus();
        request.reject(new Error('Veraltete Einladung'));
        await flush();
        expect(document.getElementById('tour-runner-error').classList.contains('hidden')).toBe(true);
        expect(document.activeElement.id).toBe('lieblingsmutation');
        await click('tour-runner-start');
        expect(window.omxfcHttp.post).toHaveBeenCalledWith('/99/start');
    });

    it.each(['navigation', 'profile-save'])('shows a current fetch failure during %s despite the fetch incrementing its generation', async source => {
        window.omxfcHttp.get.mockRejectedValueOnce(new Error('Einladung nicht erreichbar'));
        if (source === 'navigation') document.dispatchEvent(new CustomEvent('livewire:navigated'));
        else window.dispatchEvent(new CustomEvent('profile-series-saved'));
        await flush();
        expect(document.getElementById('tour-runner-error').textContent).toBe('Einladung nicht erreichbar');
        expect(document.activeElement.id).toBe('tour-runner-error');
    });

    it('retries failed final progress before completion and waits for the successful sync', async () => {
        await click('tour-runner-start');
        const retry = deferred();
        const original = window.omxfcHttp.post.getMockImplementation();
        let failures = 2;
        window.omxfcHttp.post.mockImplementation(async (url, data) => {
            if (url.endsWith('/progress') && data.step_key === 'profile-complete') {
                if (failures-- > 0) throw new Error('Fortschritt offline');
                await retry.promise;
            }
            return original(url, data);
        });
        await click('tour-runner-next');
        expect(document.getElementById('tour-runner-title').textContent).toBe('Abschluss');
        expect(tour.current_step_key).toBe('profile-field-lieblingsmutation');
        window.omxfcHttp.post.mockClear();

        await click('tour-runner-complete');
        expect(window.omxfcHttp.post.mock.calls.map(([url]) => url)).toEqual(['/12/progress']);
        expect(document.getElementById('tour-runner-error').textContent).toContain('Fortschritt offline');
        expect(window.toast).not.toHaveBeenCalled();

        await click('tour-runner-complete');
        expect(document.getElementById('tour-runner-complete').disabled).toBe(true);
        await click('tour-runner-complete');
        expect(window.omxfcHttp.post.mock.calls.map(([url]) => url)).toEqual(['/12/progress', '/12/progress']);
        retry.resolve();
        await flush();
        expect(tour.current_step_key).toBe('profile-complete');
        expect(window.omxfcHttp.post.mock.calls.map(([url]) => url)).toEqual(['/12/progress', '/12/progress', '/12/complete']);
        expect(document.getElementById('tour-runner-panel').classList.contains('hidden')).toBe(true);
        expect(window.toast).toHaveBeenCalledTimes(1);
    });

    it('does not complete a superseded assignment after its progress retry succeeds', async () => {
        await click('tour-runner-start');
        const retry = deferred();
        const original = window.omxfcHttp.post.getMockImplementation();
        window.omxfcHttp.post.mockRejectedValueOnce(new Error('Fortschritt offline'));
        await click('tour-runner-next');
        window.omxfcHttp.post.mockImplementation((url, data) => url === '/12/progress' ? retry.promise : original(url, data));
        await click('tour-runner-complete');
        tour = { ...payload(), assignment_id: 99 };
        document.dispatchEvent(new CustomEvent('livewire:navigated'));
        await flush();
        retry.resolve({ data: { tour: { ...payload(), current_step_key: 'profile-complete' } } });
        await flush();
        expect(window.omxfcHttp.post.mock.calls.some(([url]) => url.endsWith('/complete'))).toBe(false);
        expect(window.toast).not.toHaveBeenCalled();
        await click('tour-runner-start');
        expect(window.omxfcHttp.post).toHaveBeenCalledWith('/99/start');
    });
});
