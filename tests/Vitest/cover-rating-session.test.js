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
        <section data-testid="cover-rating-session" aria-hidden="true" inert>
            <h2 tabindex="-1" data-cover-focus>Testcover</h2>
            <div data-rating-feedback>Gespeichert</div>
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
        expect(overlay.inert).toBe(true);
        expect(overlay.getAttribute('aria-hidden')).toBe('true');
        expect(window.scrollTo).toHaveBeenCalledWith(0, 0);
        expect(document.activeElement).toBe(startButton);
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

        window.dispatchEvent(new CustomEvent('cover-rating-advanced', {
            detail: { hasCover: true, awardedBaxx: 0 },
        }));

        expect(controller.feedbackVisible).toBe(true);
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
});
