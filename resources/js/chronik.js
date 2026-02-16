function initChronikLightbox() {
    const modal = document.getElementById('chronik-modal');
    if (!modal) return;

    // Verhindere doppelte Listener
    if (modal.dataset.chronikInitialized) {
        return;
    }
    modal.dataset.chronikInitialized = 'true';

    const img = document.getElementById('chronik-modal-img');
    const srcAvif = document.getElementById('chronik-modal-avif');
    const srcWebp = document.getElementById('chronik-modal-webp');
    const closeBtn = document.getElementById('chronik-modal-close');

    document.addEventListener('click', (e) => {
        const trigger = e.target.closest('.chronik-image');
        if (!trigger) return;

        e.preventDefault();
        srcAvif.srcset = trigger.dataset.avif;
        srcWebp.srcset = trigger.dataset.webp;
        img.src = trigger.dataset.webp;
        img.alt = trigger.querySelector('img').alt;
        modal.classList.remove('hidden');
    });

    modal.addEventListener('click', (e) => {
        if (e.target === modal || e.target === closeBtn) {
            modal.classList.add('hidden');
        }
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            modal.classList.add('hidden');
        }
    });
}

document.addEventListener('DOMContentLoaded', initChronikLightbox);
document.addEventListener('livewire:navigated', initChronikLightbox);
