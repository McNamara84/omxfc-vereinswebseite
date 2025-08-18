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
    };

    let showUnfilledRolesOnly = false;
    let onlyEpisodeId = null;

    function applyFilters() {
        const statusVal = filters.status?.value;
        const typeVal = filters.type?.value;
        const yearVal = filters.year?.value;
        const rolesChecked = filters.roles?.checked;

        rows.forEach(row => {
            const matchStatus = !statusVal || row.dataset.status === statusVal;
            const matchType = !typeVal || row.dataset.type === typeVal;
            const matchYear = !yearVal || (row.dataset.year ?? '') === yearVal;
            const matchRoles = !rolesChecked || row.dataset.rolesFilled === '1';
            const matchUnfilled = !showUnfilledRolesOnly || row.dataset.rolesFilled === '0';
            const matchEpisode = !onlyEpisodeId || row.dataset.episodeId === onlyEpisodeId;

            row.style.display = matchStatus && matchType && matchYear && matchRoles && matchUnfilled && matchEpisode ? '' : 'none';
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

    cardUnfilledRoles?.addEventListener('click', () => {
        showUnfilledRolesOnly = true;
        onlyEpisodeId = null;
        if (filters.status) {
            filters.status.value = '';
        }
        applyFilters();
    });

    cardOpenEpisodes?.addEventListener('click', () => {
        showUnfilledRolesOnly = true;
        onlyEpisodeId = null;
        if (filters.status) {
            filters.status.value = 'Rollenbesetzung';
        }
        applyFilters();
    });

    cardNextEvent?.addEventListener('click', () => {
        const id = cardNextEvent.dataset.episodeId;
        if (id) {
            onlyEpisodeId = id;
            showUnfilledRolesOnly = false;
            if (filters.status) {
                filters.status.value = '';
            }
            applyFilters();
        }
    });

    applyFilters();
});
