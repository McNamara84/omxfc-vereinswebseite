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

    function applyFilters() {
        const statusVal = filters.status?.value;
        const typeVal = filters.type?.value;
        const yearVal = filters.year?.value;
        const rolesChecked = filters.roles?.checked;

        rows.forEach(row => {
            const matchStatus = !statusVal || row.dataset.status === statusVal;
            const matchType = !typeVal || row.dataset.type === typeVal;
            const matchYear = !yearVal || row.dataset.year === yearVal;
            const matchRoles = !rolesChecked || row.dataset.rolesFilled === '1';

            row.style.display = matchStatus && matchType && matchYear && matchRoles ? '' : 'none';
        });
    }

    Object.values(filters)
        .filter(el => el)
        .forEach(el => {
            el.addEventListener('change', applyFilters);
        });

    applyFilters();
});
