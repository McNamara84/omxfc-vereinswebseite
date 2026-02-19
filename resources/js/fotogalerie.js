/**
 * Fotogalerie: Tab-Navigation und Bildergalerie mit Thumbnails.
 *
 * Wird über app.js gebundelt geladen. Guard-Pattern: Initialisierung
 * läuft nur, wenn .jahr-tab Elemente auf der Seite existieren.
 */

function initFotogalerie() {
    const jahresTabs = document.querySelectorAll('.jahr-tab');
    if (jahresTabs.length === 0) return;

    // Jahr-Tab-Wechsel
    jahresTabs.forEach(tab => {
        // Verhindere doppelte Listener bei Re-Initialisierung
        if (tab.dataset.initialized) return;
        tab.dataset.initialized = 'true';

        tab.addEventListener('click', function() {
            const year = this.getAttribute('data-year');

            // Alle Tabs zurücksetzen
            jahresTabs.forEach(t => {
                t.classList.remove('border-primary', 'text-primary');
                t.classList.add('border-transparent', 'text-base-content');
            });

            // Aktiven Tab setzen
            this.classList.remove('border-transparent', 'text-base-content');
            this.classList.add('border-primary', 'text-primary');

            // Galerien anzeigen/verstecken
            document.querySelectorAll('.gallery-container').forEach(gallery => {
                gallery.classList.add('hidden');
            });
            document.getElementById(`gallery-${year}`).classList.remove('hidden');
        });
    });

    // Für jede Jahresgalerie die Bildnavigation einrichten
    document.querySelectorAll('.gallery-container').forEach(gallery => {
        // Verhindere doppelte Initialisierung
        if (gallery.dataset.initialized) return;
        gallery.dataset.initialized = 'true';

        const thumbnails = gallery.querySelectorAll('.thumbnail');
        const mainImage = gallery.querySelector('.main-image');
        const prevButton = gallery.querySelector('.prev-button');
        const nextButton = gallery.querySelector('.next-button');

        let currentIndex = 0;

        if (thumbnails.length === 0) return;

        // Thumbnail-Klick
        thumbnails.forEach(thumb => {
            thumb.addEventListener('click', function() {
                currentIndex = parseInt(this.getAttribute('data-index'));
                updateMainImage();
                updateThumbnailSelection();
            });
        });

        // Pfeil-Buttons
        if (prevButton && nextButton) {
            prevButton.addEventListener('click', function() {
                currentIndex = (currentIndex - 1 + thumbnails.length) % thumbnails.length;
                updateMainImage();
                updateThumbnailSelection();
                scrollToThumbnail();
            });

            nextButton.addEventListener('click', function() {
                currentIndex = (currentIndex + 1) % thumbnails.length;
                updateMainImage();
                updateThumbnailSelection();
                scrollToThumbnail();
            });
        }

        function updateMainImage() {
            if (mainImage && thumbnails[currentIndex]) {
                mainImage.src = thumbnails[currentIndex].src;
            }
        }

        function updateThumbnailSelection() {
            thumbnails.forEach(thumb => {
                thumb.classList.remove('ring-2', 'ring-primary');
            });
            thumbnails[currentIndex].classList.add('ring-2', 'ring-primary');
        }

        function scrollToThumbnail() {
            thumbnails[currentIndex].scrollIntoView({
                behavior: 'smooth',
                block: 'nearest',
                inline: 'center'
            });
        }
    });
}

document.addEventListener('DOMContentLoaded', initFotogalerie);
document.addEventListener('livewire:navigated', initFotogalerie);
