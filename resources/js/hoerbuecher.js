document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('tr[data-href]').forEach(row => {
        row.addEventListener('click', () => {
            window.location.href = row.dataset.href;
        });
        row.addEventListener('keydown', e => {
            if (e.key === 'Enter') {
                window.location.href = row.dataset.href;
            }
        });
    });
});
