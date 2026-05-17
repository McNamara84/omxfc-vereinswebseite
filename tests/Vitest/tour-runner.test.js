async function flushAsyncWork() {
    await Promise.resolve();
    await Promise.resolve();
    await new Promise((resolve) => setTimeout(resolve, 0));
}

describe('tour runner', () => {
    it('springt bei unsichtbaren Schritten auf den naechsten sichtbaren Schritt', async () => {
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

        HTMLElement.prototype.scrollIntoView = vi.fn();
        window.toast = vi.fn();

        const payload = {
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

        window.axios = {
            get: vi.fn().mockResolvedValue({ data: { tour: payload } }),
            post: vi.fn().mockImplementation(async (url, body = {}) => {
                if (url === '/tour/7/progress') {
                    return {
                        data: {
                            tour: {
                                ...payload,
                                current_step_key: body.step_key,
                            },
                        },
                    };
                }

                return { data: { tour: payload } };
            }),
        };

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
        expect(document.getElementById('tour-runner-title')?.textContent).toBe('Dashboard');

        document.getElementById('tour-runner-next')?.click();
        await flushAsyncWork();

        expect(document.getElementById('tour-runner-title')?.textContent).toBe('Sichtbarer Folgeschritt');
        expect(window.axios.post).toHaveBeenLastCalledWith('/tour/7/progress', {
            step_key: 'visible-follow-up',
        });
    });
});