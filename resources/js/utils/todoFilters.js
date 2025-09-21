const FILTER_MESSAGES = {
    all: 'Zeigt alle verfügbaren Challenges.',
    assigned: 'Zeigt deine übernommenen Challenges.',
    open: 'Zeigt offene Challenges, die noch übernommen werden können.',
    pending: 'Zeigt Challenges, die auf eine Verifizierung warten.',
};

const FILTER_SECTIONS = {
    all: null,
    assigned: new Set(['assigned']),
    open: new Set(['open']),
    pending: new Set(['pending']),
};

export function normaliseFilter(filter) {
    if (typeof filter !== 'string') {
        return 'all';
    }

    const trimmed = filter.trim().toLowerCase();

    return Object.prototype.hasOwnProperty.call(FILTER_MESSAGES, trimmed) ? trimmed : 'all';
}

export function getFilterMessage(filter) {
    const key = normaliseFilter(filter);

    return FILTER_MESSAGES[key];
}

export function updateButtons(buttons, filter) {
    const activeFilter = normaliseFilter(filter);

    buttons.forEach((button) => {
        if (!(button instanceof HTMLElement)) {
            return;
        }

        const targetFilter = normaliseFilter(button.dataset.filter || 'all');
        const isActive = targetFilter === activeFilter;

        button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
        button.dataset.active = isActive ? 'true' : 'false';
    });
}

export function updateSections(sections, filter) {
    const activeFilter = normaliseFilter(filter);
    const allowed = FILTER_SECTIONS[activeFilter];

    sections.forEach((section) => {
        if (!(section instanceof HTMLElement)) {
            return;
        }

        const sectionKey = normaliseFilter(section.dataset.todoSection || 'all');
        const shouldShow = !allowed || allowed.has(sectionKey);

        section.classList.toggle('hidden', !shouldShow);
        section.setAttribute('aria-hidden', shouldShow ? 'false' : 'true');
    });
}

export function applyFilterState({
    buttons,
    sections,
    statusElement,
    filter,
}) {
    const activeFilter = normaliseFilter(filter);

    updateButtons(buttons, activeFilter);

    if (statusElement instanceof HTMLElement) {
        statusElement.textContent = getFilterMessage(activeFilter);
    }

    updateSections(sections, activeFilter);

    return activeFilter;
}

export function initTodoFilters(root = document) {
    if (!root || typeof root.querySelector !== 'function') {
        return;
    }

    const form = root.querySelector('[data-todo-filter-form]');

    if (!form) {
        return;
    }

    const buttons = Array.from(form.querySelectorAll('[data-todo-filter]'));

    if (buttons.length === 0) {
        return;
    }

    const sections = Array.from(root.querySelectorAll('[data-todo-section]'));
    const statusElement = form.querySelector('[data-todo-filter-status]');
    const initialFilter = normaliseFilter(form.dataset.currentFilter || 'all');

    applyFilterState({
        buttons,
        sections,
        statusElement,
        filter: initialFilter,
    });

    form.addEventListener('click', (event) => {
        const target = event.target instanceof HTMLElement
            ? event.target.closest('[data-todo-filter]')
            : null;

        if (!target) {
            return;
        }

        const nextFilter = normaliseFilter(target.dataset.filter || 'all');

        applyFilterState({
            buttons,
            sections,
            statusElement,
            filter: nextFilter,
        });

        form.dataset.currentFilter = nextFilter;
    });
}
