const sanitizeTopUsers = (raw) => {
    if (!raw) {
        return [];
    }

    if (Array.isArray(raw)) {
        return raw;
    }

    try {
        const parsed = JSON.parse(raw);

        return Array.isArray(parsed) ? parsed : [];
    } catch (error) {
        return [];
    }
};

export const buildTopUserSummary = (users) => {
    const normalized = sanitizeTopUsers(users)
        .filter((user) => user && typeof user.name === 'string')
        .map((user, index) => ({
            position: index + 1,
            name: user.name.trim(),
            points: Number.parseInt(user.points ?? 0, 10) || 0,
        }));

    if (normalized.length === 0) {
        return '';
    }

    const header = `Top ${normalized.length} Baxx-Sammler: `;
    const items = normalized
        .map((user) => `${user.position}. ${user.name} (${user.points} Baxx)`)
        .join(', ');

    return `${header}${items}`;
};

export const enhanceTopUserList = (container, users = undefined) => {
    if (!container) {
        return '';
    }

    const summary = buildTopUserSummary(users ?? container.dataset.dashboardTopUsers ?? []);

    if (!summary) {
        container.removeAttribute('aria-label');
        return '';
    }

    if (!container.hasAttribute('role')) {
        container.setAttribute('role', 'list');
    }

    container.setAttribute('aria-label', summary);

    const summaryTarget = container.querySelector('[data-dashboard-top-summary]');
    if (summaryTarget) {
        summaryTarget.textContent = summary;
    }

    container
        .querySelectorAll('[data-dashboard-top-user-item]')
        .forEach((item) => {
            if (!item.hasAttribute('role')) {
                item.setAttribute('role', 'listitem');
            }
        });

    return summary;
};

export const setupDashboardAccessibility = (root = document) => {
    const containers = Array.from(root.querySelectorAll('[data-dashboard-top-users]'));

    containers.forEach((container) => {
        enhanceTopUserList(container);
    });
};
