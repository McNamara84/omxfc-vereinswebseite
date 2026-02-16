const sortLabels = {
    nachname: 'Nachname',
    mitglied_seit: 'Mitglied seit',
    role: 'Rolle',
    last_activity: 'Zuletzt online',
    mitgliedsbeitrag: 'Beitrag',
};

const resolveSortLabel = (sortKey = 'nachname') => {
    return sortLabels[sortKey] ?? sortLabels.nachname;
};

const resolveDirectionLabel = (direction = 'asc') => {
    return direction === 'desc' ? 'absteigender' : 'aufsteigender';
};

const formatMemberCount = (count) => {
    if (!Number.isFinite(count) || count < 0) {
        return '';
    }

    const rounded = Math.trunc(count);

    return rounded === 1 ? '1 Mitglied' : `${rounded} Mitglieder`;
};

export const buildMembersTableSummary = ({
    sortBy = 'nachname',
    sortDir = 'asc',
    filterOnline = false,
    totalMembers,
} = {}) => {
    const sortLabel = resolveSortLabel(sortBy);
    const directionLabel = resolveDirectionLabel(sortDir);
    const filterSummary = filterOnline
        ? 'Es werden nur Mitglieder angezeigt, die aktuell online sind.'
        : 'Es werden alle aktiven Mitglieder angezeigt.';

    const total = Number.isFinite(totalMembers) ? totalMembers : Number.parseInt(totalMembers ?? Number.NaN, 10);
    const memberSummary = formatMemberCount(total);
    const countSentence = memberSummary ? ` Insgesamt sind ${memberSummary} sichtbar.` : '';

    return `Mitgliederliste, sortiert nach ${sortLabel} in ${directionLabel} Reihenfolge. ${filterSummary}${countSentence}`;
};

const parseBoolean = (value) => {
    if (typeof value === 'boolean') {
        return value;
    }

    if (typeof value === 'string') {
        return value.toLowerCase() === 'true';
    }

    return false;
};

const parseTotal = (value) => {
    const parsed = typeof value === 'number' ? value : Number.parseInt(value ?? Number.NaN, 10);

    return Number.isFinite(parsed) && parsed >= 0 ? parsed : undefined;
};

const resolveSummaryElement = (table, summaryId) => {
    if (!table) {
        return null;
    }

    if (summaryId) {
        const target = table.ownerDocument?.getElementById(summaryId);
        if (target) {
            return target;
        }
    }

    return table.ownerDocument?.querySelector('[data-members-summary]') ?? null;
};

const toAriaSort = (direction) => {
    if (direction === 'desc' || direction === 'descending') {
        return 'descending';
    }

    if (direction === 'asc' || direction === 'ascending') {
        return 'ascending';
    }

    return 'none';
};

export const enhanceMembersTable = (table) => {
    if (!table) {
        return '';
    }

    const dataset = table.dataset ?? {};
    const summaryId = dataset.membersSummaryId ?? table.getAttribute('aria-describedby') ?? '';
    const sortBy = dataset.membersSort ?? 'nachname';
    const sortDir = dataset.membersDir ?? 'asc';
    const filterOnline = parseBoolean(dataset.membersFilterOnline);
    const totalMembers = parseTotal(dataset.membersTotal);

    const summaryText = buildMembersTableSummary({
        sortBy,
        sortDir,
        filterOnline,
        totalMembers,
    });

    if (summaryId && !table.getAttribute('aria-describedby')) {
        table.setAttribute('aria-describedby', summaryId);
    }

    const summaryElement = resolveSummaryElement(table, summaryId);
    if (summaryElement) {
        summaryElement.textContent = summaryText;
    }

    const headers = table.querySelectorAll('[data-members-sort-column]');
    headers.forEach((header) => {
        const column = header.getAttribute('data-members-sort-column');
        if (!column) {
            return;
        }

        const isActive = column === sortBy;
        header.setAttribute('aria-sort', toAriaSort(isActive ? sortDir : 'none'));
    });

    return summaryText;
};

export const setupMitgliederAccessibility = (root = typeof document !== 'undefined' ? document : undefined) => {
    if (!root || typeof root.querySelectorAll !== 'function') {
        return [];
    }

    const tables = Array.from(root.querySelectorAll('[data-members-table]'));

    return tables.map((table) => enhanceMembersTable(table));
};

if (typeof document !== 'undefined') {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            setupMitgliederAccessibility(document);
        });
    } else {
        setupMitgliederAccessibility(document);
    }

    document.addEventListener('livewire:navigated', () => {
        setupMitgliederAccessibility(document);
    });
}
