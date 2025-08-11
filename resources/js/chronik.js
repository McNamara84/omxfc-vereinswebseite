document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('chronik-modal');
    if (!modal) return;

    const img = document.getElementById('chronik-modal-img');
    const srcAvif = document.getElementById('chronik-modal-avif');
    const srcWebp = document.getElementById('chronik-modal-webp');

    document.addEventListener('click', (e) => {
        const trigger = e.target.closest('.chronik-image');
        if (trigger) {
            e.preventDefault();
            srcAvif.srcset = trigger.dataset.avif;
            srcWebp.srcset = trigger.dataset.webp;
            img.src = trigger.dataset.webp;
            img.alt = trigger.querySelector('img').alt;
            modal.classList.remove('hidden');
            return;
        }

        if (e.target.id === 'chronik-modal-close' || e.target === modal) {
            modal.classList.add('hidden');
        }
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            modal.classList.add('hidden');
        }
    });
});
