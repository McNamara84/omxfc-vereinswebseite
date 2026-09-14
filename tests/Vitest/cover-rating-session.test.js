import { vi } from 'vitest';
import {
    createCoverRatingSession,
    registerCoverRatingSession,
} from '../../resources/js/cover-ratings/session';

const originalFullscreenElement = Object.getOwnPropertyDescriptor(document, 'fullscreenElement');
const originalExitFullscreen = document.exitFullscreen;
const originalScrollTo = window.scrollTo;

const setFullscreenElement = (element) => {
    Object.defineProperty(document, 'fullscreenElement', {
        configurable: true,
        value: element,
    });
};

const buildController = () => {
    document.body.innerHTML = `
        <button data-testid="start-cover-rating">Bewertung starten</button>
        <div tabindex="-1" data-cover-return-focus>Bewertung abgeschlossen</div>
        <section data-testid="cover-rating-session" aria-hidden="true" inert>
            <h2 tabindex="-1" data-cover-focus>Testcover</h2>
            <div data-rating-feedback>Gespeichert</div>
            <fieldset data-brina-rating-controls></fieldset>
        </section>
    `;

    const overlay = document.querySelector('[data-testid="cover-rating-session"]');
    const startButton = document.querySelector('[data-testid="start-cover-rating"]');
    const controller = createCoverRatingSession();
    controller.$refs = { session: overlay };
    controller.$nextTick = (callback) => callback();

    return { controller, overlay, startButton };
};

beforeEach(() => {
    setFullscreenElement(null);
    window.scrollTo = vi.fn();
});

afterEach(() => {
    vi.useRealTimers();
    document.body.classList.remove('cover-rating-session-open');
    document.body.innerHTML = '';
    document.exitFullscreen = originalExitFullscreen;
    window.scrollTo = originalScrollTo;
    vi.restoreAllMocks();
});

afterAll(() => {
    if (originalFullscreenElement) {
        Object.defineProperty(document, 'fullscreenElement', originalFullscreenElement);
    } else {
        delete document.fullscreenElement;
    }
});

describe('cover rating fullscreen session', () => {
    test('starts native fullscreen directly from the trigger and focuses the cover', async () => {
        const { controller, overlay, startButton } = buildController();
        overlay.requestFullscreen = vi.fn().mockImplementation(() => {
            setFullscreenElement(overlay);
            document.dispatchEvent(new Event('fullscreenchange'));
            return Promise.resolve();
        });

        controller.init();
        await controller.start({ currentTarget: startButton });

        expect(overlay.requestFullscreen).toHaveBeenCalledOnce();
        expect(controller.active).toBe(true);
        expect(controller.nativeFullscreen).toBe(true);
        expect(overlay.classList.contains('cover-rating-session--active')).toBe(true);
        expect(overlay.inert).toBe(false);
        expect(document.body.classList.contains('cover-rating-session-open')).toBe(true);
        expect(document.activeElement).toBe(document.querySelector('[data-cover-focus]'));

        controller.destroy();
    });

    test.each([
        ['is missing', undefined],
        ['rejects', vi.fn().mockRejectedValue(new Error('denied'))],
        ['throws', vi.fn(() => { throw new Error('blocked'); })],
    ])('keeps the CSS fallback active when requestFullscreen %s', async (_label, requestFullscreen) => {
        const { controller, overlay, startButton } = buildController();
        overlay.requestFullscreen = requestFullscreen;

        await controller.start({ currentTarget: startButton });

        expect(controller.active).toBe(true);
        expect(controller.nativeFullscreen).toBe(false);
        expect(overlay.getAttribute('aria-hidden')).toBe('false');
        expect(overlay.classList.contains('cover-rating-session--active')).toBe(true);
    });

    test('ends native fullscreen, restores scroll and returns focus', async () => {
        const { controller, overlay, startButton } = buildController();
        startButton.focus();
        overlay.requestFullscreen = vi.fn().mockImplementation(() => {
            setFullscreenElement(overlay);
            return Promise.resolve();
        });
        document.exitFullscreen = vi.fn().mockImplementation(() => {
            setFullscreenElement(null);
            return Promise.resolve();
        });

        await controller.start({ currentTarget: startButton });
        await controller.stop();

        expect(document.exitFullscreen).toHaveBeenCalledOnce();
        expect(controller.active).toBe(false);
        expect(controller.ownsScrollPosition).toBe(false);
        expect(overlay.inert).toBe(true);
        expect(overlay.getAttribute('aria-hidden')).toBe('true');
        expect(window.scrollTo).toHaveBeenCalledWith(0, 0);
        expect(document.activeElement).toBe(startButton);
    });

    test.each([
        ['stop()', (controller) => controller.stop({ restoreFocus: false })],
        ['livewire:navigating', (controller) => {
            controller.init();
            document.dispatchEvent(new Event('livewire:navigating'));
        }],
        ['destroy()', (controller) => {
            controller.init();
            controller.destroy();
        }],
    ])('does not restore scroll during %s before a session has started', async (_label, cleanup) => {
        const { controller } = buildController();
        controller.scrollX = 240;
        controller.scrollY = 800;

        await cleanup(controller);

        expect(window.scrollTo).not.toHaveBeenCalled();

        if (controller.initialized) {
            controller.destroy();
        }
    });

    test('restores an owned scroll position only once across repeated cleanup', async () => {
        const { controller, startButton } = buildController();
        controller.init();

        await controller.start({ currentTarget: startButton });
        await controller.stop({ restoreFocus: false });
        controller.destroy();

        expect(window.scrollTo).toHaveBeenCalledOnce();
        expect(controller.ownsScrollPosition).toBe(false);
    });

    test('returns focus to the overview state when the saved trigger became disabled', async () => {
        const { controller, startButton } = buildController();
        const overviewState = document.querySelector('[data-cover-return-focus]');
        startButton.focus();

        await controller.start({ currentTarget: startButton });
        startButton.disabled = true;
        await controller.stop();

        expect(document.activeElement).toBe(overviewState);
    });

    test.each(['stop', 'destroy'])(
        'exits fullscreen when a stale request resolves after %s',
        async (cleanupMethod) => {
            const { controller, overlay, startButton } = buildController();
            let resolveFullscreenRequest;
            overlay.requestFullscreen = vi.fn(() => new Promise((resolve) => {
                resolveFullscreenRequest = resolve;
            }));
            document.exitFullscreen = vi.fn().mockImplementation(() => {
                setFullscreenElement(null);
                return Promise.resolve();
            });
            controller.init();

            const fullscreenRequest = controller.start({ currentTarget: startButton });
            if (cleanupMethod === 'destroy') {
                controller.destroy();
            } else {
                await controller.stop({ restoreFocus: false });
            }

            setFullscreenElement(overlay);
            document.dispatchEvent(new Event('fullscreenchange'));
            resolveFullscreenRequest();
            await fullscreenRequest;

            expect(document.exitFullscreen).toHaveBeenCalledOnce();
            expect(document.fullscreenElement).toBeNull();
            expect(controller.active).toBe(false);
            expect(controller.nativeFullscreen).toBe(false);
            expect(controller.fullscreenRequestPending).toBe(false);
            expect(overlay.inert).toBe(true);
        },
    );

    test('blocks a restart until a stopped fullscreen request has settled', async () => {
        const { controller, overlay, startButton } = buildController();
        let resolveFirstRequest;
        overlay.requestFullscreen = vi.fn()
            .mockImplementationOnce(() => new Promise((resolve) => {
                resolveFirstRequest = resolve;
            }))
            .mockResolvedValueOnce();
        document.exitFullscreen = vi.fn().mockImplementation(() => {
            setFullscreenElement(null);
            return Promise.resolve();
        });
        controller.init();

        const firstRequest = controller.start({ currentTarget: startButton });
        await controller.stop({ restoreFocus: false });
        const blockedRestart = controller.start({ currentTarget: startButton });

        expect(blockedRestart).toBe(firstRequest);
        expect(overlay.requestFullscreen).toHaveBeenCalledOnce();
        expect(controller.active).toBe(false);
        expect(controller.fullscreenRequestPending).toBe(true);

        setFullscreenElement(overlay);
        document.dispatchEvent(new Event('fullscreenchange'));
        resolveFirstRequest();
        await firstRequest;

        expect(controller.fullscreenRequestPending).toBe(false);
        expect(controller.active).toBe(false);
        await controller.start({ currentTarget: startButton });
        expect(overlay.requestFullscreen).toHaveBeenCalledTimes(2);
        expect(controller.active).toBe(true);
        controller.destroy();
    });

    test('blocks a restart until the previous native fullscreen exit has settled', async () => {
        const { controller, overlay, startButton } = buildController();
        let resolveFullscreenExit;
        overlay.requestFullscreen = vi.fn().mockImplementation(() => {
            setFullscreenElement(overlay);
            return Promise.resolve();
        });
        document.exitFullscreen = vi.fn(() => new Promise((resolve) => {
            resolveFullscreenExit = () => {
                setFullscreenElement(null);
                resolve();
            };
        }));

        await controller.start({ currentTarget: startButton });
        const fullscreenExit = controller.stop({ restoreFocus: false });
        const blockedRestart = controller.start({ currentTarget: startButton });

        expect(blockedRestart).toBe(controller.fullscreenExitPromise);
        expect(overlay.requestFullscreen).toHaveBeenCalledOnce();
        expect(controller.active).toBe(false);

        resolveFullscreenExit();
        await Promise.all([fullscreenExit, blockedRestart]);

        expect(controller.fullscreenExitPromise).toBeNull();
        await controller.start({ currentTarget: startButton });
        expect(overlay.requestFullscreen).toHaveBeenCalledTimes(2);
        expect(controller.active).toBe(true);
    });

    test('closes the session when the browser leaves a previously active native fullscreen', async () => {
        const { controller, overlay, startButton } = buildController();
        controller.init();
        controller.active = true;
        controller.returnFocusElement = startButton;
        controller.nativeFullscreen = true;
        controller.setOverlayActive(true);
        setFullscreenElement(null);

        document.dispatchEvent(new Event('fullscreenchange'));
        await Promise.resolve();
        await new Promise((resolve) => setTimeout(resolve, 0));

        expect(controller.active).toBe(false);
        expect(document.activeElement).toBe(startButton);
        controller.destroy();
    });

    test('Escape closes only the CSS fallback directly', async () => {
        const { controller, startButton } = buildController();
        controller.init();
        await controller.start({ currentTarget: startButton });
        const event = new KeyboardEvent('keydown', { key: 'Escape', cancelable: true });

        document.dispatchEvent(event);

        expect(event.defaultPrevented).toBe(true);
        expect(controller.active).toBe(false);
        controller.destroy();
    });

    test('Escape waits for fullscreenchange while native fullscreen is active', () => {
        const { controller, overlay } = buildController();
        controller.init();
        controller.active = true;
        controller.nativeFullscreen = true;
        setFullscreenElement(overlay);
        const event = new KeyboardEvent('keydown', { key: 'Escape', cancelable: true });

        document.dispatchEvent(event);

        expect(event.defaultPrevented).toBe(false);
        expect(controller.active).toBe(true);
        controller.destroy();
    });

    test('shows standard feedback temporarily and focuses the advanced cover', () => {
        vi.useFakeTimers();
        const { controller } = buildController();
        controller.init();
        controller.active = true;
        controller.ratingPreview = 5;

        window.dispatchEvent(new CustomEvent('cover-rating-advanced', {
            detail: { hasCover: true, awardedBaxx: 0 },
        }));

        expect(controller.feedbackVisible).toBe(true);
        expect(controller.ratingPreview).toBe(0);
        expect(document.activeElement).toBe(document.querySelector('[data-cover-focus]'));
        vi.advanceTimersByTime(4499);
        expect(controller.feedbackVisible).toBe(true);
        vi.advanceTimersByTime(1);
        expect(controller.feedbackVisible).toBe(false);
        controller.destroy();
    });

    test('keeps Baxx feedback visible longer and accepts Livewire array details', () => {
        vi.useFakeTimers();
        const { controller } = buildController();
        controller.init();
        controller.active = true;

        window.dispatchEvent(new CustomEvent('cover-rating-feedback', {
            detail: [{ awardedBaxx: 1 }],
        }));

        vi.advanceTimersByTime(5000);
        expect(controller.feedbackVisible).toBe(true);
        vi.advanceTimersByTime(2000);
        expect(controller.feedbackVisible).toBe(false);
        controller.destroy();
    });

    test('restores focus inside the session after undo feedback removes its trigger', () => {
        const { controller, overlay } = buildController();
        const undoButton = document.createElement('button');
        undoButton.textContent = 'Rückgängig';
        overlay.append(undoButton);
        controller.init();
        controller.active = true;
        undoButton.focus();
        undoButton.remove();

        window.dispatchEvent(new CustomEvent('cover-rating-feedback', {
            detail: { awardedBaxx: 0 },
        }));

        expect(document.activeElement).toBe(document.querySelector('[data-cover-focus]'));
        controller.destroy();
    });

    test('focuses the empty state when no next cover exists', () => {
        const { controller, overlay } = buildController();
        overlay.querySelector('[data-cover-focus]').remove();
        overlay.insertAdjacentHTML('beforeend', '<h2 tabindex="-1" data-cover-empty-focus>Fertig</h2>');
        controller.init();
        controller.active = true;

        window.dispatchEvent(new CustomEvent('cover-rating-advanced', {
            detail: { hasCover: false },
        }));

        expect(document.activeElement).toBe(document.querySelector('[data-cover-empty-focus]'));
        controller.destroy();
    });

    test('initializes listeners once and removes them on destroy', () => {
        const { controller } = buildController();
        const addEventListener = vi.spyOn(document, 'addEventListener');
        const removeEventListener = vi.spyOn(document, 'removeEventListener');

        controller.init();
        controller.init();
        controller.destroy();

        expect(addEventListener.mock.calls.filter(([event]) => event === 'fullscreenchange')).toHaveLength(1);
        expect(removeEventListener.mock.calls.filter(([event]) => event === 'fullscreenchange')).toHaveLength(1);
        expect(controller.initialized).toBe(false);
    });

    test('registers the Alpine data provider only once per Alpine instance', () => {
        const alpine = { data: vi.fn() };

        expect(registerCoverRatingSession(alpine)).toBe(true);
        expect(registerCoverRatingSession(alpine)).toBe(false);
        expect(alpine.data).toHaveBeenCalledOnce();
        expect(alpine.data).toHaveBeenCalledWith('coverRatingSession', expect.any(Function));
    });

    test('does not initialize an existing root before Alpine has started', async () => {
        vi.resetModules();
        document.body.innerHTML = '<div x-data="coverRatingSession"></div>';
        const root = document.querySelector('[x-data="coverRatingSession"]');
        const previousAlpine = window.Alpine;
        let sessionFactory;
        const alpine = {
            data: vi.fn((_name, factory) => {
                sessionFactory = factory;
            }),
            initTree: vi.fn((element) => {
                element._x_dataStack = [sessionFactory()];
            }),
            destroyTree: vi.fn(),
            $data: vi.fn((element) => element._x_dataStack?.[0]),
        };
        window.Alpine = alpine;

        try {
            await import('../../resources/js/cover-ratings/session.js');
        } finally {
            if (previousAlpine === undefined) {
                delete window.Alpine;
            } else {
                window.Alpine = previousAlpine;
            }
        }

        expect(alpine.data).toHaveBeenCalledWith('coverRatingSession', expect.any(Function));
        expect(alpine.initTree).not.toHaveBeenCalled();
        expect(alpine.destroyTree).not.toHaveBeenCalled();
        expect(root._x_dataStack).toBeUndefined();
    });

    test('does not reinitialize an existing root with the registered session state', async () => {
        vi.resetModules();
        document.body.innerHTML = '<div x-data="coverRatingSession"></div>';
        const root = document.querySelector('[x-data="coverRatingSession"]');
        const previousAlpine = window.Alpine;
        const existingScope = {
            active: false,
            fullscreenRequestPending: false,
            start: vi.fn(),
            stop: vi.fn(),
        };
        root._x_dataStack = [existingScope];
        const alpine = {
            data: vi.fn(),
            initTree: vi.fn(),
            destroyTree: vi.fn(),
            $data: vi.fn(() => existingScope),
        };
        window.Alpine = alpine;

        try {
            await import('../../resources/js/cover-ratings/session.js');
        } finally {
            if (previousAlpine === undefined) {
                delete window.Alpine;
            } else {
                window.Alpine = previousAlpine;
            }
        }

        expect(alpine.data).toHaveBeenCalledWith('coverRatingSession', expect.any(Function));
        expect(alpine.$data).toHaveBeenCalledOnce();
        expect(alpine.$data).toHaveBeenCalledWith(root);
        expect(alpine.destroyTree).not.toHaveBeenCalled();
        expect(alpine.initTree).not.toHaveBeenCalled();
    });

    test('rebinds an actual descendant Alpine directive after late provider registration', async () => {
        vi.resetModules();
        document.body.innerHTML = `
            <div x-data="coverRatingSession">
                <button type="button" x-on:click="start($event)">Start</button>
                <output x-bind:data-session-active="active ? 'yes' : 'no'"></output>
                <input wire:change="rate(5)">
                <section x-ref="session" data-testid="cover-rating-session" aria-hidden="true" inert></section>
            </div>
        `;
        const root = document.querySelector('[x-data="coverRatingSession"]');
        const startButton = root.querySelector('button');
        const stateOutput = root.querySelector('output');
        const livewireControl = root.querySelector('[wire\\:change]');
        const livewireCleanup = vi.fn();
        livewireControl._x_attributeCleanups = { 'wire:change': [livewireCleanup] };
        const previousAlpine = window.Alpine;
        const { default: alpine } = await import('alpinejs');
        const alpineErrors = [];
        alpine.setErrorHandler((error) => alpineErrors.push(error));
        window.Alpine = alpine;
        // Keep the fallback listener from the file's static import out of this
        // fresh Alpine instance so it can first process the missing provider.
        document.addEventListener('alpine:init', () => {
            window.Alpine = {};
        }, { capture: true, once: true });
        document.addEventListener('alpine:init', () => {
            window.Alpine = alpine;
        }, { once: true });
        alpine.start();

        expect(root._x_dataStack).toBeDefined();
        expect(alpine.$data(root).start).toBeUndefined();

        try {
            await import('../../resources/js/cover-ratings/session.js');

            await new Promise((resolve) => setTimeout(resolve, 0));
            expect(stateOutput.getAttribute('data-session-active')).toBe('no');
            alpineErrors.length = 0;

            startButton.click();
            await new Promise((resolve) => setTimeout(resolve, 0));
            await alpine.nextTick();

            expect(alpineErrors.map(({ message }) => message)).toEqual([]);
            expect(alpine.$data(root).active).toBe(true);
            expect(stateOutput.getAttribute('data-session-active')).toBe('yes');
            expect(livewireCleanup).not.toHaveBeenCalled();
        } finally {
            alpine.destroyTree(root);
            alpine.stopObservingMutations();

            if (previousAlpine === undefined) {
                delete window.Alpine;
            } else {
                window.Alpine = previousAlpine;
            }
        }
    });

    test('waits for alpine:init when a partial global Alpine object has no data API yet', async () => {
        vi.resetModules();
        const previousAlpine = window.Alpine;
        const alpine = {};
        const addEventListener = vi.spyOn(document, 'addEventListener');
        window.Alpine = alpine;

        try {
            await import('../../resources/js/cover-ratings/session.js');

            const alpineInitRegistration = addEventListener.mock.calls
                .find(([eventName]) => eventName === 'alpine:init');

            expect(alpineInitRegistration).toBeDefined();
            alpine.data = vi.fn();
            alpineInitRegistration[1]();

            expect(alpine.data).toHaveBeenCalledOnce();
            expect(alpine.data).toHaveBeenCalledWith('coverRatingSession', expect.any(Function));
        } finally {
            if (previousAlpine === undefined) {
                delete window.Alpine;
            } else {
                window.Alpine = previousAlpine;
            }
        }
    });
});
