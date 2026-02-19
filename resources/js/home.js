/**
 * Startseite: Bildergalerie-Slideshow und Rezensionen-Fetch.
 *
 * Wird über app.js gebundelt geladen. Guard-Pattern: Initialisierung
 * läuft nur, wenn die jeweiligen DOM-Elemente auf der Seite existieren.
 */

let galleryIntervalId = null;

function initHomeGallery() {
    // Cleanup vorheriger Instanz (bei SPA-Navigation)
    if (galleryIntervalId) {
        clearInterval(galleryIntervalId);
        galleryIntervalId = null;
    }

    const images = document.querySelectorAll('#gallery img');
    if (images.length === 0) return;

    let current = 0;
    images[current].classList.remove('opacity-0');

    galleryIntervalId = setInterval(() => {
        images[current].classList.add('opacity-0');
        current = (current + 1) % images.length;
        images[current].classList.remove('opacity-0');
    }, 4000);
}

function initLatestReviews() {
    const list = document.getElementById('latest-reviews-list');
    const loading = document.getElementById('latest-reviews-loading');
    const empty = document.getElementById('latest-reviews-empty');

    if (!list || !loading) return;

    // Verhindere doppelten Fetch
    if (list.dataset.initialized) return;
    list.dataset.initialized = 'true';

    const renderReview = (review) => {
        const item = document.createElement('li');
        item.className = 'py-4';

        const header = document.createElement('div');
        header.className = 'flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2';

        const badge = document.createElement('span');
        badge.className = 'inline-flex w-fit items-center gap-2 rounded-full bg-[#8B0116]/10 text-[#8B0116] dark:bg-[#ff4b63]/15 dark:text-[#ff4b63] px-3 py-1 text-xs font-semibold';
        badge.textContent = `Roman Nr. ${review.roman_number}`;
        badge.setAttribute('aria-label', `Roman Nummer ${review.roman_number}`);

        const romanTitle = document.createElement('p');
        romanTitle.className = 'text-sm text-gray-700 dark:text-gray-200 font-medium';
        romanTitle.textContent = review.roman_title;

        header.appendChild(badge);
        header.appendChild(romanTitle);

        const reviewTitle = document.createElement('h3');
        reviewTitle.className = 'mt-3 text-base font-semibold text-gray-900 dark:text-white flex flex-wrap items-center gap-1';

        const titleText = document.createElement('span');
        titleText.textContent = review.review_title;

        const reviewDate = document.createElement('time');
        reviewDate.className = 'text-sm font-normal text-gray-700 dark:text-gray-300';
        reviewDate.dateTime = review.reviewed_at;

        const parsedDate = new Date(review.reviewed_at);
        const formattedDate = Number.isNaN(parsedDate.getTime())
            ? 'Unbekanntes Datum'
            : new Intl.DateTimeFormat('de-DE', { dateStyle: 'medium' }).format(parsedDate);

        reviewDate.textContent = formattedDate;
        reviewDate.setAttribute('aria-label', `Rezension veröffentlicht am ${formattedDate}`);

        reviewTitle.appendChild(titleText);
        reviewTitle.appendChild(document.createTextNode(' vom '));
        reviewTitle.appendChild(reviewDate);

        const excerpt = document.createElement('p');
        excerpt.className = 'mt-2 text-sm text-gray-600 dark:text-gray-300 leading-relaxed';
        excerpt.textContent = review.excerpt;

        item.appendChild(header);
        item.appendChild(reviewTitle);
        item.appendChild(excerpt);

        return item;
    };

    // API-URL aus data-Attribut oder Fallback
    const apiUrl = list.dataset.apiUrl || '/api/reviews/latest';

    fetch(apiUrl, {
        headers: {
            'Accept': 'application/json',
        },
    })
        .then((response) => response.ok ? response.json() : Promise.reject(response))
        .then((data) => {
            loading.setAttribute('aria-busy', 'false');
            loading.classList.add('hidden');
            if (!Array.isArray(data) || data.length === 0) {
                empty.classList.remove('hidden');
                list.classList.add('hidden');
                return;
            }

            list.innerHTML = '';
            data.forEach((review) => list.appendChild(renderReview(review)));
            list.classList.remove('hidden');
        })
        .catch(() => {
            loading.setAttribute('aria-busy', 'false');
            loading.classList.add('hidden');

            const errorMessage = document.createElement('div');
            errorMessage.className = 'mt-4 flex items-center gap-2 text-sm text-red-700 dark:text-red-300';
            errorMessage.setAttribute('role', 'status');
            errorMessage.setAttribute('aria-live', 'polite');

            const dot = document.createElement('span');
            dot.className = 'inline-block h-2 w-2 rounded-full bg-red-600';

            const text = document.createElement('span');
            text.textContent = 'Rezensionen konnten nicht geladen werden.';

            errorMessage.appendChild(dot);
            errorMessage.appendChild(text);

            loading.after(errorMessage);
        });
}

function initHome() {
    initHomeGallery();
    initLatestReviews();
}

document.addEventListener('DOMContentLoaded', initHome);
document.addEventListener('livewire:navigated', initHome);
