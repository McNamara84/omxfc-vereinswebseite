async function flushAsyncWork() {
    await Promise.resolve();
    await Promise.resolve();
    await new Promise((resolve) => window.requestAnimationFrame(() => resolve()));
    await new Promise((resolve) => window.requestAnimationFrame(() => resolve()));
    await new Promise((resolve) => setTimeout(resolve, 0));
}

function renderRunnerDom() {
    document.body.innerHTML = `
        <div
            id="tour-runner-root"
            data-tour-current-url="/tour/current"
            data-tour-start-url-template="/tour/__TOUR_ASSIGNMENT__/start"
            data-tour-progress-url-template="/tour/__TOUR_ASSIGNMENT__/progress"
            data-tour-dismiss-url-template="/tour/__TOUR_ASSIGNMENT__/dismiss"
            data-tour-complete-url-template="/tour/__TOUR_ASSIGNMENT__/complete"
        >
            <div id="tour-runner-backdrop" class="hidden"></div>
            <div id="tour-runner-highlight" class="hidden"></div>
            <section id="tour-runner-panel" class="hidden">
                <h2 id="tour-runner-title"></h2>
                <p id="tour-runner-description"></p>
                <p id="tour-runner-counter"></p>
                <span id="tour-runner-progress-label"></span>
                <div id="tour-runner-progress-bar"></div>
                <button id="tour-runner-back" type="button">Zurueck</button>
                <button id="tour-runner-skip" type="button">Spaeter</button>
                <button id="tour-runner-next" type="button">Weiter</button>
                <button id="tour-runner-complete" type="button" class="hidden">Tour abschliessen</button>
            </section>
        </div>

        <button id="visible-step-one">Erster Schritt</button>
        <button id="hidden-step" style="display: none;">Versteckter Schritt</button>
        <button id="visible-step-two">Sichtbarer Folgeschritt</button>
    `;
}

function renderRunnerDomWithDesktopDropdown() {
    document.body.innerHTML = `
        <div
            id="tour-runner-root"
            data-tour-current-url="/tour/current"
            data-tour-start-url-template="/tour/__TOUR_ASSIGNMENT__/start"
            data-tour-progress-url-template="/tour/__TOUR_ASSIGNMENT__/progress"
            data-tour-dismiss-url-template="/tour/__TOUR_ASSIGNMENT__/dismiss"
            data-tour-complete-url-template="/tour/__TOUR_ASSIGNMENT__/complete"
        >
            <div id="tour-runner-backdrop" class="hidden"></div>
            <div id="tour-runner-highlight" class="hidden"></div>
            <section id="tour-runner-panel" class="hidden">
                <h2 id="tour-runner-title"></h2>
                <p id="tour-runner-description"></p>
                <p id="tour-runner-counter"></p>
                <span id="tour-runner-progress-label"></span>
                <div id="tour-runner-progress-bar"></div>
                <button id="tour-runner-back" type="button">Zurueck</button>
                <button id="tour-runner-skip" type="button">Spaeter</button>
                <button id="tour-runner-next" type="button">Weiter</button>
                <button id="tour-runner-complete" type="button" class="hidden">Tour abschliessen</button>
            </section>
        </div>

        <details id="community-dropdown">
            <summary>
                <div data-tour-device="desktop" data-tour-key="section-community" data-tour-open="false">Community</div>
            </summary>
            <a id="community-members-link" href="/mitglieder" data-tour-device="desktop" data-tour-key="community-members">Mitgliederliste</a>
            <a id="community-reviews-link" href="/rezensionen" data-tour-device="desktop" data-tour-key="community-reviews">Rezensionen</a>
        </details>
    `;

    const dropdown = document.getElementById('community-dropdown');
    const summary = document.querySelector('[data-tour-device="desktop"][data-tour-key="section-community"]');

    if (dropdown instanceof HTMLDetailsElement && summary instanceof HTMLElement) {
        summary.click = vi.fn(() => {
            const isOpen = dropdown.hasAttribute('open');

            if (isOpen) {
                dropdown.removeAttribute('open');
            } else {
                dropdown.setAttribute('open', '');
            }

            summary.dataset.tourOpen = dropdown.hasAttribute('open') ? 'true' : 'false';
        });
    }
}

function openDesktopDropdown() {
    const dropdown = document.getElementById('community-dropdown');
    const trigger = document.querySelector('[data-tour-device="desktop"][data-tour-key="section-community"]');

    if (!(dropdown instanceof HTMLDetailsElement) || !(trigger instanceof HTMLElement)) {
        return;
    }

    dropdown.setAttribute('open', '');
    trigger.dataset.tourOpen = 'true';
}

function closeDesktopDropdown() {
    const dropdown = document.getElementById('community-dropdown');
    const trigger = document.querySelector('[data-tour-device="desktop"][data-tour-key="section-community"]');

    if (!(dropdown instanceof HTMLDetailsElement) || !(trigger instanceof HTMLElement)) {
        return;
    }

    dropdown.removeAttribute('open');
    trigger.dataset.tourOpen = 'false';
}

function createPayload() {
    return {
        assignment_id: 7,
        status: 'open',
        current_step_key: 'dashboard',
        steps: [
            {
                key: 'dashboard',
                title: 'Dashboard',
                description: 'Erster sichtbarer Schritt',
                selectors: {
                    desktop: '#visible-step-one',
                    mobile: '#visible-step-one',
                },
            },
            {
                key: 'hidden-step',
                title: 'Unsichtbarer Schritt',
                description: 'Dieser Schritt bleibt unsichtbar',
                selectors: {
                    desktop: '#hidden-step',
                    mobile: '#hidden-step',
                },
            },
            {
                key: 'visible-follow-up',
                title: 'Sichtbarer Folgeschritt',
                description: 'Naechster sichtbarer Schritt',
                selectors: {
                    desktop: '#visible-step-two',
                    mobile: '#visible-step-two',
                },
            },
        ],
    };
}

function clonePayload(payload, overrides = {}) {
    return {
        ...payload,
        ...overrides,
        steps: payload.steps.map((step) => ({
            ...step,
            selectors: {
                ...step.selectors,
            },
        })),
    };
}

function stubAxios(payload) {
    let currentStepKey = payload.current_step_key;

    const responseFor = (stepKey = currentStepKey) => ({
        data: {
            tour: clonePayload(payload, {
                current_step_key: stepKey,
            }),
        },
    });

    const post = vi.fn().mockImplementation(async (url, body = {}) => {
        if (url === '/tour/7/progress') {
            currentStepKey = body.step_key;

            return responseFor(currentStepKey);
        }

        return responseFor();
    });

    window.axios = {
        get: vi.fn().mockImplementation(async () => responseFor()),
        post,
    };

    return { post };
}

async function bootRunner() {
    Object.defineProperty(document, 'readyState', {
        configurable: true,
        value: 'loading',
    });

    const { initTourRunner } = await import('@/tours/runner');

    Object.defineProperty(document, 'readyState', {
        configurable: true,
        value: 'complete',
    });

    await initTourRunner();
}

describe('tour runner', () => {
    beforeEach(() => {
        vi.resetModules();
        renderRunnerDom();

        HTMLElement.prototype.scrollIntoView = vi.fn();
        window.toast = vi.fn();
    });

    it('springt bei unsichtbaren Schritten auf den naechsten sichtbaren Schritt', async () => {
        const payload = createPayload();
        stubAxios(payload);

        await bootRunner();
        expect(document.getElementById('tour-runner-title')?.textContent).toBe('Dashboard');

        document.getElementById('tour-runner-next')?.click();
        await flushAsyncWork();

        expect(document.getElementById('tour-runner-title')?.textContent).toBe('Sichtbarer Folgeschritt');
        expect(document.getElementById('tour-runner-counter')?.textContent).toBe('2 / 2');
        expect(window.axios.post).toHaveBeenLastCalledWith('/tour/7/progress', {
            step_key: 'visible-follow-up',
        });
    });

    it('navigiert ueber uebersprungene unsichtbare Schritte auch rueckwaerts zum vorherigen sichtbaren Schritt', async () => {
        const payload = createPayload();
        const axios = stubAxios(payload);

        await bootRunner();

        document.getElementById('tour-runner-next')?.click();
        await flushAsyncWork();
        expect(document.getElementById('tour-runner-title')?.textContent).toBe('Sichtbarer Folgeschritt');

        document.getElementById('tour-runner-back')?.click();
        await flushAsyncWork();

        expect(document.getElementById('tour-runner-title')?.textContent).toBe('Dashboard');
        expect(document.getElementById('tour-runner-counter')?.textContent).toBe('1 / 2');
        expect(axios.post).toHaveBeenLastCalledWith('/tour/7/progress', {
            step_key: 'dashboard',
        });
    });

    it('haelt remorphte Tour-Buttons fuer weiter, zurueck und spaeter klickbar', async () => {
        const payload = createPayload();
        const axios = stubAxios(payload);

        await bootRunner();

        ['tour-runner-next', 'tour-runner-back', 'tour-runner-skip'].forEach((id) => {
            const button = document.getElementById(id);

            if (button) {
                button.replaceWith(button.cloneNode(true));
            }
        });

        document.getElementById('tour-runner-next')?.click();
        await flushAsyncWork();
        expect(document.getElementById('tour-runner-title')?.textContent).toBe('Sichtbarer Folgeschritt');

        document.getElementById('tour-runner-back')?.click();
        await flushAsyncWork();
        expect(document.getElementById('tour-runner-title')?.textContent).toBe('Dashboard');

        document.getElementById('tour-runner-skip')?.click();
        await flushAsyncWork();

        expect(document.getElementById('tour-runner-panel')?.classList.contains('hidden')).toBe(true);
        expect(axios.post).toHaveBeenLastCalledWith('/tour/7/dismiss');
    });

    it('oeffnet Desktop-Dropdowns ueber den Tour-Trigger, bevor Unterpunkte gezeigt werden', async () => {
        vi.resetModules();
        renderRunnerDomWithDesktopDropdown();
        Object.defineProperty(window, 'innerWidth', {
            configurable: true,
            value: 1440,
        });

        HTMLElement.prototype.scrollIntoView = vi.fn();
        window.toast = vi.fn();

        stubAxios({
            assignment_id: 9,
            status: 'open',
            current_step_key: 'community-members',
            steps: [
                {
                    key: 'community-members',
                    title: 'Mitgliederliste',
                    description: 'Unterpunkt der Community-Navigation',
                    selectors: {
                        desktop: '[data-tour-device="desktop"][data-tour-key="community-members"]',
                        mobile: '[data-tour-device="mobile"][data-tour-key="community-members"]',
                    },
                    reveal: {
                        desktop: ['[data-tour-device="desktop"][data-tour-key="section-community"]'],
                    },
                },
            ],
        });

        await bootRunner();

        expect(document.getElementById('community-dropdown')?.hasAttribute('open')).toBe(true);
        expect(document.getElementById('tour-runner-title')?.textContent).toBe('Mitgliederliste');
    });

    it('oeffnet den Desktop-Dropdown beim Wechsel zum naechsten Unterpunkt erneut, wenn click.outside ihn schliesst', async () => {
        vi.resetModules();
        renderRunnerDomWithDesktopDropdown();
        Object.defineProperty(window, 'innerWidth', {
            configurable: true,
            value: 1440,
        });

        HTMLElement.prototype.scrollIntoView = vi.fn();
        window.toast = vi.fn();

        openDesktopDropdown();

        stubAxios({
            assignment_id: 10,
            status: 'open',
            current_step_key: 'community-members',
            steps: [
                {
                    key: 'community-members',
                    title: 'Mitgliederliste',
                    description: 'Unterpunkt der Community-Navigation',
                    selectors: {
                        desktop: '[data-tour-device="desktop"][data-tour-key="community-members"]',
                        mobile: '[data-tour-device="mobile"][data-tour-key="community-members"]',
                    },
                    reveal: {
                        desktop: ['[data-tour-device="desktop"][data-tour-key="section-community"]'],
                    },
                },
                {
                    key: 'community-reviews',
                    title: 'Rezensionen',
                    description: 'Unterpunkt der Community-Navigation',
                    selectors: {
                        desktop: '[data-tour-device="desktop"][data-tour-key="community-reviews"]',
                        mobile: '[data-tour-device="mobile"][data-tour-key="community-reviews"]',
                    },
                    reveal: {
                        desktop: ['[data-tour-device="desktop"][data-tour-key="section-community"]'],
                    },
                },
            ],
        });

        document.getElementById('tour-runner-next')?.addEventListener('click', () => {
            window.requestAnimationFrame(() => {
                closeDesktopDropdown();
            });
        });

        await bootRunner();

        document.getElementById('tour-runner-next')?.click();
        await flushAsyncWork();
        await flushAsyncWork();
        await flushAsyncWork();

        expect(document.getElementById('community-dropdown')?.hasAttribute('open')).toBe(true);
        expect(document.getElementById('tour-runner-title')?.textContent).toBe('Rezensionen');
        expect(window.axios.post).toHaveBeenLastCalledWith('/tour/10/progress', {
            step_key: 'community-reviews',
        });
    });
});