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
});
