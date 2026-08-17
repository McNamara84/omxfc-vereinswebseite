import {
    disconnectDashboardActivityFeedObservers,
    setupDashboardActivityFeed,
} from '@/dashboard/activity-feed';

describe('dashboard activity feed infinite scrolling', () => {
    let callbacks;
    let instances;

    beforeEach(() => {
        callbacks = [];
        instances = [];
        document.body.innerHTML = `
            <div data-dashboard-activity-feed>
                <button data-dashboard-feed-load-more>Mehr laden</button>
                <span data-dashboard-feed-sentinel></span>
            </div>
        `;

        window.IntersectionObserver = class {
            constructor(callback, options) {
                this.observe = vi.fn();
                this.disconnect = vi.fn();
                this.options = options;
                callbacks.push(callback);
                instances.push(this);
            }
        };
    });

    afterEach(() => {
        disconnectDashboardActivityFeedObservers();
        vi.restoreAllMocks();
    });

    it('observes the sentinel with an early-loading margin', () => {
        expect(setupDashboardActivityFeed()).toBe(1);
        expect(instances[0].observe).toHaveBeenCalledWith(
            document.querySelector('[data-dashboard-feed-sentinel]'),
        );
        expect(instances[0].options).toEqual({ rootMargin: '400px 0px' });
    });

    it('clicks the accessible fallback button once when the sentinel intersects', () => {
        const button = document.querySelector('[data-dashboard-feed-load-more]');
        const click = vi.spyOn(button, 'click');
        setupDashboardActivityFeed();

        callbacks[0]([{ isIntersecting: true }]);
        callbacks[0]([{ isIntersecting: true }]);

        expect(click).toHaveBeenCalledTimes(1);
    });

    it('does not request a page for disabled controls or non-intersections', () => {
        const button = document.querySelector('[data-dashboard-feed-load-more]');
        const click = vi.spyOn(button, 'click');
        button.disabled = true;
        setupDashboardActivityFeed();

        callbacks[0]([{ isIntersecting: false }]);
        callbacks[0]([{ isIntersecting: true }]);

        expect(click).not.toHaveBeenCalled();
    });

    it('disconnects old observers before wiring a refreshed Livewire tree', () => {
        setupDashboardActivityFeed();
        const oldObserver = instances[0];

        setupDashboardActivityFeed();

        expect(oldObserver.disconnect).toHaveBeenCalledOnce();
        expect(instances).toHaveLength(2);
    });

    it('keeps the manual button usable when IntersectionObserver is unavailable', () => {
        delete window.IntersectionObserver;

        expect(setupDashboardActivityFeed()).toBe(0);
        expect(document.querySelector('[data-dashboard-feed-load-more]')).not.toBeNull();
    });
});
