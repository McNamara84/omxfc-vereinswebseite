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

function renderRunnerDomWithMemberSidebar() {
    renderRunnerDom();

    document.body.insertAdjacentHTML('beforeend', `
        <button data-tour-key="mobile-menu-toggle" data-tour-open="false">Menü</button>
        <ul>
            <li data-tour-key="section-community" data-tour-open="false">
                <details>
                    <summary>Community</summary>
                    <a href="/mitglieder" data-tour-key="community-members">Mitgliederliste</a>
                </details>
            </li>
        </ul>
    `);

    const drawerToggle = document.querySelector('[data-tour-key="mobile-menu-toggle"]');
    const section = document.querySelector('[data-tour-key="section-community"]');
    const details = section?.querySelector('details');

    if (drawerToggle instanceof HTMLElement) {
        drawerToggle.click = vi.fn(() => {
            drawerToggle.dataset.tourOpen = 'true';
        });
    }

    if (section instanceof HTMLElement && details instanceof HTMLDetailsElement) {
        section.click = vi.fn(() => {
            details.open = true;
            section.dataset.tourOpen = 'true';
        });
    }
}

function renderRunnerDomWithDuplicatePublicTargets() {
    renderRunnerDom();

    document.body.insertAdjacentHTML('beforeend', `
        <nav id="desktop-navigation" style="display: none;">
            <button id="desktop-community" data-tour-key="section-community" data-tour-open="false">Community Desktop</button>
            <a id="desktop-members" data-tour-key="community-members">Mitglieder Desktop</a>
        </nav>
        <button id="mobile-menu-toggle" data-tour-key="mobile-menu-toggle" data-tour-open="false">Menü</button>
        <nav id="mobile-navigation" style="display: none;">
            <button id="mobile-community" data-tour-key="section-community" data-tour-open="false">Community Mobil</button>
            <a id="mobile-members" data-tour-key="community-members" style="display: none;">Mitglieder Mobil</a>
        </nav>
    `);

    const mobileMenuToggle = document.getElementById('mobile-menu-toggle');
    const mobileNavigation = document.getElementById('mobile-navigation');
    const mobileCommunity = document.getElementById('mobile-community');
    const mobileMembers = document.getElementById('mobile-members');

    if (mobileMenuToggle instanceof HTMLElement && mobileNavigation instanceof HTMLElement) {
        mobileMenuToggle.click = vi.fn(() => {
            mobileNavigation.style.display = 'block';
            mobileMenuToggle.dataset.tourOpen = 'true';
        });
    }

    if (mobileCommunity instanceof HTMLElement && mobileMembers instanceof HTMLElement) {
        mobileCommunity.click = vi.fn(() => {
            mobileMembers.style.display = 'block';
            mobileCommunity.dataset.tourOpen = 'true';
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

function stubHttp(payload) {
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

    window.omxfcHttp = {
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
        stubHttp(payload);

        await bootRunner();
        expect(document.getElementById('tour-runner-title')?.textContent).toBe('Dashboard');

        document.getElementById('tour-runner-next')?.click();
        await flushAsyncWork();

        expect(document.getElementById('tour-runner-title')?.textContent).toBe('Sichtbarer Folgeschritt');
        expect(document.getElementById('tour-runner-counter')?.textContent).toBe('2 / 2');
        expect(window.omxfcHttp.post).toHaveBeenLastCalledWith('/tour/7/progress', {
            step_key: 'visible-follow-up',
        });
    });

    it('navigiert ueber uebersprungene unsichtbare Schritte auch rueckwaerts zum vorherigen sichtbaren Schritt', async () => {
        const payload = createPayload();
        const http = stubHttp(payload);

        await bootRunner();

        document.getElementById('tour-runner-next')?.click();
        await flushAsyncWork();
        expect(document.getElementById('tour-runner-title')?.textContent).toBe('Sichtbarer Folgeschritt');

        document.getElementById('tour-runner-back')?.click();
        await flushAsyncWork();

        expect(document.getElementById('tour-runner-title')?.textContent).toBe('Dashboard');
        expect(document.getElementById('tour-runner-counter')?.textContent).toBe('1 / 2');
        expect(http.post).toHaveBeenLastCalledWith('/tour/7/progress', {
            step_key: 'dashboard',
        });
    });

    it('haelt remorphte Tour-Buttons fuer weiter, zurueck und spaeter klickbar', async () => {
        const payload = createPayload();
        const http = stubHttp(payload);

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
        expect(http.post).toHaveBeenLastCalledWith('/tour/7/dismiss');
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

        stubHttp({
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

    it('oeffnet im mobilen Member-Shell zuerst Drawer und Sidebar-Bereich', async () => {
        vi.resetModules();
        renderRunnerDomWithMemberSidebar();
        Object.defineProperty(window, 'innerWidth', {
            configurable: true,
            value: 390,
        });

        HTMLElement.prototype.scrollIntoView = vi.fn();
        window.toast = vi.fn();

        stubHttp({
            assignment_id: 11,
            status: 'open',
            current_step_key: 'community-members',
            steps: [
                {
                    key: 'community-members',
                    title: 'Mitgliederliste',
                    description: 'Unterpunkt der gemeinsamen Sidebar',
                    selectors: {
                        desktop: '[data-tour-key="community-members"]',
                        mobile: '[data-tour-key="community-members"]',
                    },
                    reveal: {
                        mobile: [
                            '[data-tour-key="mobile-menu-toggle"]',
                            '[data-tour-key="section-community"]',
                        ],
                    },
                },
            ],
        });

        await bootRunner();

        expect(document.querySelector('[data-tour-key="mobile-menu-toggle"]')?.dataset.tourOpen).toBe('true');
        expect(document.querySelector('[data-tour-key="section-community"]')?.dataset.tourOpen).toBe('true');
        expect(document.getElementById('tour-runner-title')?.textContent).toBe('Mitgliederliste');
    });

    it('verwendet bei gemeinsamen Public-Tour-Keys die sichtbaren mobilen Reveal- und Ziel-Elemente', async () => {
        vi.resetModules();
        renderRunnerDomWithDuplicatePublicTargets();
        Object.defineProperty(window, 'innerWidth', {
            configurable: true,
            value: 390,
        });

        const desktopCommunity = document.getElementById('desktop-community');
        const mobileCommunity = document.getElementById('mobile-community');
        const mobileMembers = document.getElementById('mobile-members');

        if (desktopCommunity instanceof HTMLElement) {
            desktopCommunity.click = vi.fn();
        }

        if (mobileMembers instanceof HTMLElement) {
            mobileMembers.scrollIntoView = vi.fn();
        }

        window.toast = vi.fn();

        stubHttp({
            assignment_id: 12,
            status: 'open',
            current_step_key: 'community-members',
            steps: [
                {
                    key: 'community-members',
                    title: 'Mitgliederliste',
                    description: 'Unterpunkt der mobilen Public-Navigation',
                    selectors: {
                        desktop: '[data-tour-key="community-members"]',
                        mobile: '[data-tour-key="community-members"]',
                    },
                    reveal: {
                        mobile: [
                            '[data-tour-key="mobile-menu-toggle"]',
                            '[data-tour-key="section-community"]',
                        ],
                    },
                },
            ],
        });

        await bootRunner();

        expect(desktopCommunity?.click).not.toHaveBeenCalled();
        expect(mobileCommunity?.click).toHaveBeenCalledOnce();
        expect(mobileMembers?.scrollIntoView).toHaveBeenCalledOnce();
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

        stubHttp({
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
        expect(window.omxfcHttp.post).toHaveBeenLastCalledWith('/tour/10/progress', {
            step_key: 'community-reviews',
        });
    });
});
