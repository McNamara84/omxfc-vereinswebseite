document.addEventListener('DOMContentLoaded', async () => {
    const container = document.getElementById('release-notes');
    if (!container) {
        return;
    }
    try {
        const response = await fetch('/changelog.json');
        const releases = await response.json();
        releases.forEach(release => {
            const section = document.createElement('section');
            section.className = 'mb-8';

            const header = document.createElement('header');
            header.className = 'flex items-center mb-4';

            const badge = document.createElement('span');
            badge.className = 'bg-purple-100 text-purple-800 dark:bg-purple-600 dark:text-white text-sm font-bold rounded px-2 py-1 mr-2';
            badge.innerText = release.version;

            const date = document.createElement('span');
            date.className = 'text-lg';
            const d = new Date(release.pub_date);
            date.innerText = d.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' });

            header.appendChild(badge);
            header.appendChild(date);

            const list = document.createElement('ul');
            list.className = 'list-none';

            release.notes.forEach(note => {
                const item = document.createElement('li');
                item.className = 'flex items-start mb-2';
                const match = note.match(/^\[(\w+)\]\s*(.*)$/);
                if (match) {
                    const type = match[1].toLowerCase();
                    const text = match[2];

                    const typeBadge = document.createElement('span');
                    typeBadge.className = `text-xs font-bold rounded px-2 py-1 mr-2 ${
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

            section.appendChild(header);
            section.appendChild(list);
            container.appendChild(section);
        });
    } catch (e) {
        container.innerText = 'Fehler beim Laden des Changelogs.';
    }
});
