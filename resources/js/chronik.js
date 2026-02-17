let chronikAbortController = null;

function initChronikLightbox() {
    /* Alte Listener aufräumen (auch wenn Modal fehlt, z.B. nach Wegnavigation) */
    if (chronikAbortController) {
        chronikAbortController.abort();
        chronikAbortController = null;
    }

    const modal = document.getElementById('chronik-modal');
    if (!modal) return;

    const img = document.getElementById('chronik-modal-img');
    const srcAvif = document.getElementById('chronik-modal-avif');
    const srcWebp = document.getElementById('chronik-modal-webp');
    const closeBtn = document.getElementById('chronik-modal-close');

    const ac = new AbortController();
    chronikAbortController = ac;

    document.addEventListener('click', (e) => {
        const trigger = e.target.closest('.chronik-image');
        if (!trigger) return;

        e.preventDefault();
        srcAvif.srcset = trigger.dataset.avif;
        srcWebp.srcset = trigger.dataset.webp;
        img.src = trigger.dataset.webp;
        img.alt = trigger.querySelector('img').alt;
        modal.classList.remove('hidden');
    }, { signal: ac.signal });

    modal.addEventListener('click', (e) => {
        if (e.target === modal || e.target === closeBtn) {
            modal.classList.add('hidden');
        }
    }, { signal: ac.signal });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            modal.classList.add('hidden');
        }
    }, { signal: ac.signal });
}

document.addEventListener('DOMContentLoaded', initChronikLightbox);
document.addEventListener('livewire:navigated', initChronikLightbox);
