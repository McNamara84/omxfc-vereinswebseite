document.addEventListener('DOMContentLoaded', () => {
    const rows = Array.from(document.querySelectorAll('tr[data-href]'));

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
        roles: document.getElementById('roles-filter'),
        rolesUnfilled: document.getElementById('roles-unfilled-filter'),
    };

    let onlyEpisodeId = null;

    function applyFilters() {
        if (filters.roles && filters.rolesUnfilled) {
            if (filters.roles.checked) {
                filters.rolesUnfilled.checked = false;
                filters.rolesUnfilled.disabled = true;
                filters.roles.disabled = false;
            } else if (filters.rolesUnfilled.checked) {
                filters.roles.checked = false;
                filters.roles.disabled = true;
                filters.rolesUnfilled.disabled = false;
            } else {
                filters.roles.disabled = false;
                filters.rolesUnfilled.disabled = false;
            }
        }

        const statusVal = filters.status?.value;
        const typeVal = filters.type?.value;
        const yearVal = filters.year?.value;
        const rolesChecked = filters.roles?.checked;
        const rolesUnfilledChecked = filters.rolesUnfilled?.checked;

        rows.forEach(row => {
            const matchStatus = !statusVal || row.dataset.status === statusVal;
            const matchType = !typeVal || row.dataset.type === typeVal;
            const matchYear = !yearVal || (row.dataset.year ?? '') === yearVal;
            const matchRolesFilled = !rolesChecked || row.dataset.rolesFilled === '1';
            const matchRolesUnfilled = !rolesUnfilledChecked || row.dataset.rolesFilled === '0';
            const matchEpisode = !onlyEpisodeId || row.dataset.episodeId === onlyEpisodeId;

            row.style.display =
                matchStatus &&
                matchType &&
                matchYear &&
                matchRolesFilled &&
                matchRolesUnfilled &&
                matchEpisode
                    ? ''
                    : 'none';
        });
    }

    Object.values(filters)
        .filter(el => el)
        .forEach(el => {
            el.addEventListener('change', applyFilters);
        });

    const cardUnfilledRoles = document.getElementById('card-unfilled-roles');
    const cardOpenEpisodes = document.getElementById('card-open-episodes');
    const cardNextEvent = document.getElementById('card-next-event');

    function filterUnfilledRoles(status = '') {
        if (filters.rolesUnfilled) {
            filters.rolesUnfilled.checked = true;
        }
        if (filters.roles) {
            filters.roles.checked = false;
        }
        onlyEpisodeId = null;
        if (filters.status) {
            filters.status.value = status;
        }
        applyFilters();
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
            }
            if (filters.rolesUnfilled) {
                filters.rolesUnfilled.checked = false;
            }
            if (filters.status) {
                filters.status.value = '';
            }
            applyFilters();
        }
    });

    applyFilters();
});
