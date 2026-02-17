/* resources/js/statistik-navigation.js */

/* Cleanup-Referenzen für vorherige Initialisierung */
let previousObserver = null;
let previousAbortController = null;

function setupStatistikNavigation() {
    /* ── Alte Observer/Listener aufräumen (vor Early-Returns, damit
       beim Wegnavigieren keine verwaisten Referenzen bleiben) ──────────── */
    if (previousObserver) {
        previousObserver.disconnect();
        previousObserver = null;
    }
    if (previousAbortController) {
        previousAbortController.abort();
        previousAbortController = null;
    }

    const nav = document.querySelector('[data-statistik-nav]');
    if (!nav) return;

    const links = Array.from(nav.querySelectorAll('[data-statistik-nav-link]'));
    const sections = Array.from(document.querySelectorAll('[data-statistik-section]'));
    if (!links.length || !sections.length) {
        return;
    }

    const abortController = new AbortController();
    previousAbortController = abortController;

    const prefersReducedMotion = window.matchMedia
        ? window.matchMedia('(prefers-reduced-motion: reduce)')
        : { matches: false };
    const linkMap = new Map();

    links.forEach((link) => {
        const rawId = link.getAttribute('data-section') || link.getAttribute('href') || '';
        const targetId = rawId.replace('#', '');
        if (!targetId) {
            return;
        }
        link.dataset.active = link.dataset.active ?? 'false';
        link.setAttribute('aria-current', 'false');
        linkMap.set(targetId, link);

        link.addEventListener('click', (event) => {
            const section = document.getElementById(targetId);
            if (!section) {
                return;
            }

            event.preventDefault();
            const behavior = prefersReducedMotion.matches ? 'auto' : 'smooth';
            section.scrollIntoView({ behavior, block: 'start' });
            window.history.replaceState(null, '', `#${targetId}`);
            window.requestAnimationFrame(() => {
                section.focus({ preventScroll: true });
            });
            setActive(targetId);
        }, { signal: abortController.signal });
    });

    let currentActiveId = null;

    function setActive(id) {
        if (!id || currentActiveId === id) {
            return;
        }

        currentActiveId = id;
        links.forEach((link) => {
            const isActive = linkMap.get(id) === link;
            link.dataset.active = String(isActive);
            link.setAttribute('aria-current', isActive ? 'true' : 'false');
        });
    }

    const observer = new IntersectionObserver((entries) => {
        const visible = entries
            .filter((entry) => entry.isIntersecting)
            .sort((a, b) => b.intersectionRatio - a.intersectionRatio);
        if (visible.length > 0) {
            const newId = visible[0].target.id;
            if (newId) {
                setActive(newId);
            }
        }
    }, {
        rootMargin: '-40% 0px -40% 0px',
        threshold: [0.25, 0.5, 0.75],
    });
    previousObserver = observer;

    sections.forEach((section) => {
        if (section.id) {
            observer.observe(section);
        }
    });

    const initialHash = window.location.hash.replace('#', '');
    if (initialHash && linkMap.has(initialHash)) {
        setActive(initialHash);
    } else if (sections[0]?.id) {
        setActive(sections[0].id);
    }

    window.addEventListener('hashchange', () => {
        const newHash = window.location.hash.replace('#', '');
        if (newHash && linkMap.has(newHash)) {
            setActive(newHash);
        }
    }, { signal: abortController.signal });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', setupStatistikNavigation);
} else {
    setupStatistikNavigation();
}

document.addEventListener('livewire:navigated', setupStatistikNavigation);

export { setupStatistikNavigation };
