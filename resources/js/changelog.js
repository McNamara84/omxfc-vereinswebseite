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
            details.className = 'group border border-gray-200 dark:border-gray-700 rounded-lg bg-white/80 dark:bg-gray-900/60 shadow-sm transition';
            if (index === 0) {
                details.open = true;
            }

            const summaryId = `release-summary-${release.version.replace(/[^a-zA-Z0-9]+/g, '-').toLowerCase()}`;
            const panelId = `${summaryId}-panel`;

            const summary = document.createElement('summary');
            summary.className = 'flex flex-wrap items-center justify-between gap-4 p-4 cursor-pointer text-lg font-semibold text-gray-900 dark:text-gray-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-purple-500 focus-visible:ring-offset-2 focus-visible:ring-offset-white dark:focus-visible:ring-offset-gray-900';
            summary.id = summaryId;
            summary.setAttribute('role', 'button');
            summary.setAttribute('aria-controls', panelId);
            summary.setAttribute('aria-expanded', details.open ? 'true' : 'false');

            const headerContent = document.createElement('div');
            headerContent.className = 'flex items-center gap-3';

            const badge = document.createElement('span');
            badge.className = 'bg-purple-100 text-purple-800 dark:bg-purple-600 dark:text-white text-base sm:text-lg font-bold rounded px-3 py-1';
            badge.innerText = release.version;

            const date = document.createElement('span');
            date.className = 'text-sm sm:text-base text-gray-700 dark:text-gray-300';
            const d = new Date(release.pub_date);
            date.innerText = d.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' });

            headerContent.appendChild(badge);
            headerContent.appendChild(date);

            const indicator = document.createElement('span');
            indicator.className = 'ml-auto text-gray-500 dark:text-gray-400 transition-transform group-open:-rotate-180';
            indicator.setAttribute('aria-hidden', 'true');
            indicator.innerHTML = '<svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="m6 9 6 6 6-6" /></svg>';

            summary.appendChild(headerContent);
            summary.appendChild(indicator);

            const content = document.createElement('div');
            content.id = panelId;
            content.setAttribute('role', 'region');
            content.setAttribute('aria-labelledby', summaryId);
            content.className = 'px-4 pb-4 pt-2 border-t border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900';

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
                            ? 'bg-green-600 text-white'
                            : type === 'fixed'
                                ? 'bg-red-600 text-white'
                                : type === 'improved' || type === 'changed'
                                    ? 'bg-blue-600 text-white'
                                    : 'bg-gray-600 text-white'
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
