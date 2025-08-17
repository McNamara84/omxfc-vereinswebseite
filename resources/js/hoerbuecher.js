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
        rows.forEach(row => {
            const matchStatus = !filters.status || !filters.status.value || row.dataset.status === filters.status.value;
            const matchType = !filters.type || !filters.type.value || row.dataset.type === filters.type.value;
            const matchYear = !filters.year || !filters.year.value || row.dataset.year === filters.year.value;
            const matchRoles = !filters.roles || !filters.roles.checked || row.dataset.rolesFilled === '1';

            row.style.display = matchStatus && matchType && matchYear && matchRoles ? '' : 'none';
        });
    }

    Object.values(filters).forEach(el => {
        if (el) {
            el.addEventListener('change', applyFilters);
        }
    });

    applyFilters();
});
