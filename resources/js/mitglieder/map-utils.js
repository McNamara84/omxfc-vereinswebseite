const ROLE_ICON_TYPES = {
    vorstand: ['vorstand', 'kassenwart', 'schatzmeisterin', 'schatzmeister', 'vorsitzender', 'vorsitzende'],
    ehrenmitglied: ['ehrenmitglied'],
};

export function normalizeRole(role) {
    return (role ?? '').toString().trim().toLowerCase();
}

export function getRoleIconType(role) {
    const normalized = normalizeRole(role);

    if (ROLE_ICON_TYPES.vorstand.some((value) => normalized.includes(value))) {
        return 'vorstand';
    }

    if (ROLE_ICON_TYPES.ehrenmitglied.some((value) => normalized.includes(value))) {
        return 'ehrenmitglied';
    }

    return 'mitglied';
}

export function escapeHtml(value) {
    return (value ?? '')
        .toString()
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

export function buildMemberPopup(member) {
    const name = escapeHtml(member?.name ?? '');
    const city = escapeHtml(member?.city ?? '');
    const role = escapeHtml(member?.role ?? '');
    const profileUrl = escapeHtml(member?.profile_url ?? '#');

    return `
        <div class="text-center" role="group" aria-label="Mitglied ${name}">
            <strong>${name}</strong><br>
            ${city}<br>
            <em>${role}</em><br>
            <a href="${profileUrl}" class="text-blue-500 hover:underline mt-2 inline-block" aria-label="Profil von ${name} öffnen">
                Zum Profil
            </a>
        </div>
    `;
}

export const defaultLegendItems = [
    { key: 'stammtisch', label: 'Regionalstammtisch' },
    { key: 'center', label: 'Mittelpunkt' },
    { key: 'vorstand', label: 'Vorstand' },
    { key: 'ehrenmitglied', label: 'Ehrenmitglied' },
    { key: 'mitglied', label: 'Mitglied' },
];

export function createLegendMarkup(items = defaultLegendItems) {
    const listItems = items
        .map(({ key, label }) => {
            const safeLabel = escapeHtml(label);

            return `
                <li class="flex items-center mb-1 last:mb-0" role="listitem">
                    <span class="marker-icon ${escapeHtml(key)} mr-2" aria-hidden="true"></span>
                    <span>${safeLabel}</span>
                </li>
            `;
        })
        .join('');

    return `
        <h4 class="font-semibold mb-2" id="member-map-legend-heading">Legende</h4>
        <ul class="p-0 m-0 list-none" role="list" aria-labelledby="member-map-legend-heading">
            ${listItems}
        </ul>
    `;
}

const mapUtils = {
    getRoleIconType,
    buildMemberPopup,
    createLegendMarkup,
    defaultLegendItems,
};

if (typeof window !== 'undefined') {
    const existing = window.omxfcMemberMap;
    const target = existing && typeof existing === 'object' && !Array.isArray(existing)
        ? existing
        : {};

    Object.entries(mapUtils).forEach(([key, value]) => {
        target[key] = value;
    });

    window.omxfcMemberMap = target;
}

export default mapUtils;
