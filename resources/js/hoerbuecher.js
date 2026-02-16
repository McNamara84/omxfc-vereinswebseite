// Hinweis: maryUI-Komponenten (<x-checkbox>) generieren eigene IDs
// ("mary" + md5 + übergebene id). Daher werden hier data-filter-Attribute
// statt IDs für die Selektion der Checkboxen verwendet.
//
// Die Initialisierung muss sowohl bei vollem Seitenlade (DOMContentLoaded)
// als auch nach SPA-Navigation via Livewire/Alpine (livewire:navigated)
// erfolgen, da wire:navigate kein neues DOMContentLoaded auslöst.
function initHoerbuecher() {
    const rows = Array.from(document.querySelectorAll('tr[data-href]'));
    if (!rows.length) {
        return; // Nicht auf dieser Seite
    }

    // Verhindere doppelte Initialisierung
    const table = rows[0].closest('table');
    if (table && table.dataset.hoerbucherInitialized) {
        return;
    }
    if (table) {
        table.dataset.hoerbucherInitialized = 'true';
    }

    rows.forEach(row => {
        row.addEventListener('click', () => {
            window.location.href = row.dataset.href;
        });
        row.addEventListener('keydown', e => {
            if (e.key === 'Enter') {
                window.location.href = row.dataset.href;
            }
        });
    });

    const filters = {
        status: document.getElementById('status-filter'),
        type: document.getElementById('type-filter'),
        year: document.getElementById('year-filter'),
        roleName: document.getElementById('role-name-filter'),
        roles: document.querySelector('[data-filter="roles"]'),
        rolesUnfilled: document.querySelector('[data-filter="roles-unfilled"]'),
        hideReleased: document.querySelector('[data-filter="hide-released"]'),
    };

    let onlyEpisodeId = null;

    function applyFilters() {
        const statusVal = filters.status?.value;
        const typeVal = filters.type?.value;
        const yearVal = filters.year?.value;
        const roleNameVal = filters.roleName?.value;
        const rolesChecked = filters.roles?.checked;
        const rolesUnfilledChecked = filters.rolesUnfilled?.checked;
        const hideReleasedChecked = filters.hideReleased?.checked;
        const today = new Date();
        today.setHours(0, 0, 0, 0);

        rows.forEach(row => {
            const matchStatus = !statusVal || row.dataset.status === statusVal;
            const matchType = !typeVal || row.dataset.type === typeVal;
            const matchYear = !yearVal || (row.dataset.year ?? '') === yearVal;
            let roleNames = [];
            const datasetRoleNames = row.dataset.roleNames;
            if (datasetRoleNames) {
                try {
                    const parsed = JSON.parse(datasetRoleNames);
                    if (Array.isArray(parsed)) {
                        roleNames = parsed;
                    }
                } catch (error) {
                    console.error('Konnte Rollenliste nicht parsen', error);
                }
            }
            const matchRoleName = !roleNameVal || roleNames.includes(roleNameVal);
            const rolesFilled = row.dataset.rolesFilled === '1';
            let matchRoles = true;
            if (rolesChecked) {
                matchRoles = rolesFilled;
            } else if (rolesUnfilledChecked) {
                matchRoles = !rolesFilled;
            }
            const plannedReleaseDate = row.dataset.plannedReleaseDate
                ? new Date(row.dataset.plannedReleaseDate)
                : null;
            const isReleased =
                hideReleasedChecked && plannedReleaseDate
                    ? plannedReleaseDate.getTime() < today.getTime()
                    : false;
            const matchEpisode = !onlyEpisodeId || row.dataset.episodeId === onlyEpisodeId;

            row.style.display =
                matchStatus &&
                matchType &&
                matchYear &&
                matchRoleName &&
                matchRoles &&
                matchEpisode &&
                !isReleased
                    ? ''
                    : 'none';
        });
    }

    ['status', 'type', 'year', 'roleName'].forEach(key => {
        const el = filters[key];
        el?.addEventListener('change', applyFilters);
    });

    function handleRolesChange(changed) {
        if (filters.roles && filters.rolesUnfilled) {
            if (changed === 'roles') {
                if (filters.roles.checked) {
                    filters.rolesUnfilled.checked = false;
                    filters.rolesUnfilled.disabled = true;
                } else {
                    filters.rolesUnfilled.disabled = false;
                }
            } else if (changed === 'rolesUnfilled') {
                if (filters.rolesUnfilled.checked) {
                    filters.roles.checked = false;
                    filters.roles.disabled = true;
                } else {
                    filters.roles.disabled = false;
                }
            }
        }
        applyFilters();
    }

    filters.roles?.addEventListener('change', () => handleRolesChange('roles'));
    filters.rolesUnfilled?.addEventListener('change', () =>
        handleRolesChange('rolesUnfilled')
    );
    filters.hideReleased?.addEventListener('change', applyFilters);

    const cardUnfilledRoles = document.getElementById('card-unfilled-roles');
    const cardOpenEpisodes = document.getElementById('card-open-episodes');
    const cardNextEvent = document.getElementById('card-next-event');

    function filterUnfilledRoles(status = '') {
        onlyEpisodeId = null;
        if (filters.status) {
            filters.status.value = status;
        }
        if (filters.roleName) {
            filters.roleName.value = '';
        }
        if (!filters.rolesUnfilled) {
            return;
        }
        filters.rolesUnfilled.checked = true;
        handleRolesChange('rolesUnfilled');
    }

    cardUnfilledRoles?.addEventListener('click', () => filterUnfilledRoles());

    cardOpenEpisodes?.addEventListener('click', () =>
        filterUnfilledRoles('Rollenbesetzung')
    );

    cardNextEvent?.addEventListener('click', () => {
        const id = cardNextEvent.dataset.episodeId;
        if (id) {
            onlyEpisodeId = id;
            if (filters.roles) {
                filters.roles.checked = false;
                filters.roles.disabled = false;
            }
            if (filters.rolesUnfilled) {
                filters.rolesUnfilled.checked = false;
                filters.rolesUnfilled.disabled = false;
            }
            if (filters.status) {
                filters.status.value = '';
            }
            if (filters.roleName) {
                filters.roleName.value = '';
            }
            applyFilters();
        }
    });

    applyFilters();
}

document.addEventListener('DOMContentLoaded', initHoerbuecher);
document.addEventListener('livewire:navigated', initHoerbuecher);
