let observers = [];

export const disconnectDashboardActivityFeedObservers = () => {
    observers.forEach((observer) => observer.disconnect());
    observers = [];
};

export const setupDashboardActivityFeed = () => {
    disconnectDashboardActivityFeedObservers();

    if (typeof window.IntersectionObserver !== 'function') {
        return 0;
    }

    document.querySelectorAll('[data-dashboard-activity-feed]').forEach((feed) => {
        const sentinel = feed.querySelector('[data-dashboard-feed-sentinel]');
        const button = feed.querySelector('[data-dashboard-feed-load-more]');

        if (!sentinel || !button) {
            return;
        }

        let requestPending = false;
        const observer = new window.IntersectionObserver(
            (entries) => {
                if (!entries.some((entry) => entry.isIntersecting) || requestPending || button.disabled) {
                    return;
                }

                requestPending = true;
                button.click();
            },
            { rootMargin: '400px 0px' },
        );

        observer.observe(sentinel);
        observers.push(observer);
    });

    return observers.length;
};
