document.addEventListener('DOMContentLoaded', async () => {
    const container = document.getElementById('release-notes');
    if (!container) {
        return;
    }

    try {
        const response = await fetch('/changelog.json');
        const releases = await response.json();
        const sortedReleases = Array.isArray(releases)
            ? [...releases].sort((a, b) => new Date(b.pub_date) - new Date(a.pub_date))
            : [];

        container.innerHTML = '';

        sortedReleases.forEach((release, index) => {
            const section = document.createElement('section');
            section.className = 'mb-6';

            const details = document.createElement('details');
            details.className = 'group border border-base-content/20 rounded-lg bg-base-100/80 shadow-sm transition';
            if (index === 0) {
                details.open = true;
            }

            const summaryId = `release-summary-${release.version.replace(/[^a-zA-Z0-9]+/g, '-').toLowerCase()}`;
            const panelId = `${summaryId}-panel`;

            const summary = document.createElement('summary');
            summary.className = 'flex flex-wrap items-center justify-between gap-4 p-4 cursor-pointer text-lg font-semibold text-base-content focus:outline-none focus-visible:ring-2 focus-visible:ring-secondary focus-visible:ring-offset-2 focus-visible:ring-offset-base-100';
            summary.id = summaryId;
            summary.setAttribute('role', 'button');
            summary.setAttribute('aria-controls', panelId);
            summary.setAttribute('aria-expanded', details.open ? 'true' : 'false');

            const headerContent = document.createElement('div');
            headerContent.className = 'flex items-center gap-3';

            const badge = document.createElement('span');
            badge.className = 'bg-secondary/20 text-secondary text-base sm:text-lg font-bold rounded px-3 py-1';
            badge.innerText = release.version;

            const date = document.createElement('span');
            date.className = 'text-sm sm:text-base text-base-content/70';
            const d = new Date(release.pub_date);
            date.innerText = d.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' });

            headerContent.appendChild(badge);
            headerContent.appendChild(date);

            const indicator = document.createElement('span');
            indicator.className = 'ml-auto text-base-content/50 transition-transform group-open:-rotate-180';
            indicator.setAttribute('aria-hidden', 'true');
            indicator.innerHTML = '<svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="m6 9 6 6 6-6" /></svg>';

            summary.appendChild(headerContent);
            summary.appendChild(indicator);

            const content = document.createElement('div');
            content.id = panelId;
            content.setAttribute('role', 'region');
            content.setAttribute('aria-labelledby', summaryId);
            content.className = 'px-4 pb-4 pt-2 border-t border-base-content/20 bg-base-100';

            const list = document.createElement('ul');
            list.className = 'list-none space-y-2';

            release.notes.forEach(note => {
                const item = document.createElement('li');
                item.className = 'flex items-start gap-2';
                const match = note.match(/^\[(\w+)\]\s*(.*)$/);
                if (match) {
                    const type = match[1].toLowerCase();
                    const text = match[2];

                    const typeBadge = document.createElement('span');
                    typeBadge.className = `text-xs font-bold rounded px-2 py-1 min-w-[7rem] text-center ${
                        type === 'new' || type === 'added'
                            ? 'bg-success text-success-content'
                            : type === 'fixed'
                                ? 'bg-error text-error-content'
                                : type === 'improved' || type === 'changed'
                                    ? 'bg-info text-info-content'
                                    : 'bg-neutral text-neutral-content'
                    }`;
                    typeBadge.innerText = match[1];

                    const description = document.createElement('span');
                    description.textContent = text;

                    item.appendChild(typeBadge);
                    item.appendChild(description);
                } else {
                    item.textContent = note;
                }
                list.appendChild(item);
            });

            details.appendChild(summary);
            content.appendChild(list);
            details.appendChild(content);

            details.addEventListener('toggle', () => {
                summary.setAttribute('aria-expanded', details.open ? 'true' : 'false');
            });

            section.appendChild(details);
            container.appendChild(section);
        });
    } catch (e) {
        container.innerText = 'Fehler beim Laden des Changelogs.';
    }
});
