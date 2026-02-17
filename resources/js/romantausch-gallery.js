const GALLERY_SELECTOR = '[data-romantausch-gallery]';
const FOCUSABLE_SELECTOR = [
    'a[href]','button:not([disabled])','textarea','input','select','details summary',
    '[tabindex]:not([tabindex="-1"])'
].join(',');

export class RomantauschPhotoGallery {
    constructor(root) {
        this.root = root;
        this.dialog = root.querySelector('[data-photo-dialog]');
        this.panel = this.dialog ? this.dialog.querySelector('[data-photo-dialog-panel]') : null;
        this.image = this.dialog ? this.dialog.querySelector('[data-photo-dialog-image]') : null;
        this.counter = this.dialog ? this.dialog.querySelector('[data-photo-dialog-counter]') : null;
        this.caption = this.dialog ? this.dialog.querySelector('[data-photo-dialog-caption]') : null;
        this.prevButton = this.dialog ? this.dialog.querySelector('[data-photo-dialog-prev]') : null;
        this.nextButton = this.dialog ? this.dialog.querySelector('[data-photo-dialog-next]') : null;
        this.closeButtons = this.dialog ? Array.from(this.dialog.querySelectorAll('[data-photo-dialog-close]')) : [];
        this.initialFocus = this.dialog ? this.dialog.querySelector('[data-photo-dialog-initial-focus]') : null;
        this.triggerIndices = new WeakMap();

        const triggerEntries = Array.from(root.querySelectorAll('[data-photo-dialog-trigger]'))
            .map((trigger) => {
                const src = trigger.getAttribute('data-photo-src');
                if (!src) {
                    return null;
                }

                return {
                    trigger,
                    photo: {
                        src,
                        alt: trigger.getAttribute('data-photo-alt'),
                        label: trigger.getAttribute('data-photo-label') || ''
                    }
                };
            })
            .filter(Boolean);

        this.triggers = triggerEntries.map((entry) => entry.trigger);
        this.photos = triggerEntries.map((entry) => entry.photo);
        this.currentIndex = 0;
        this.isOpen = false;
        this.previouslyFocused = null;
        this.handleKeyDown = this.handleKeyDown.bind(this);
        this.handleTriggerClick = this.handleTriggerClick.bind(this);
        this.handleOverlayClick = this.handleOverlayClick.bind(this);
        this.handleFocusTrap = this.handleFocusTrap.bind(this);
        this.init();
    }

    init() {
        if (!this.dialog || this.photos.length === 0) {
            return;
        }

        this.triggers.forEach((trigger, index) => {
            const resolvedIndex = this.resolveTriggerIndex(trigger, index);
            this.triggerIndices.set(trigger, resolvedIndex);
            trigger.addEventListener('click', this.handleTriggerClick);
        });

        this.closeButtons.forEach((button) => {
            button.addEventListener('click', () => this.close());
        });

        const overlay = this.dialog.querySelector('[data-photo-dialog-overlay]');
        if (overlay) {
            overlay.addEventListener('click', this.handleOverlayClick);
        }

        if (this.prevButton) {
            this.prevButton.addEventListener('click', () => this.showPrevious());
        }

        if (this.nextButton) {
            this.nextButton.addEventListener('click', () => this.showNext());
        }

        this.dialog.addEventListener('keydown', this.handleKeyDown);
        this.dialog.addEventListener('focusin', this.handleFocusTrap);

        this.updateNavigationState();
    }

    handleTriggerClick(event) {
        event.preventDefault();
        const trigger = event.currentTarget;
        const targetIndex = this.getTriggerIndex(trigger);
        this.open(targetIndex);
    }

    handleOverlayClick(event) {
        event.preventDefault();
        this.close();
    }

    open(index = 0) {
        if (this.isOpen || !this.dialog) {
            return;
        }

        this.isOpen = true;
        this.previouslyFocused = document.activeElement instanceof HTMLElement ? document.activeElement : null;
        this.dialog.classList.remove('hidden');
        this.dialog.setAttribute('data-open', 'true');
        document.body.classList.add('overflow-hidden');
        this.setCurrentIndex(index);

        const elementToFocus = this.initialFocus || this.closeButtons[0] || this.panel;
        window.requestAnimationFrame(() => {
            if (elementToFocus instanceof HTMLElement) {
                try {
                    elementToFocus.focus({ preventScroll: true });
                } catch (error) {
                    // Swallow focus errors to avoid crashing the gallery when the element is gone.
                }
            }
        });
    }

    close() {
        if (!this.isOpen || !this.dialog) {
            return;
        }

        this.isOpen = false;
        this.dialog.classList.add('hidden');
        this.dialog.removeAttribute('data-open');
        document.body.classList.remove('overflow-hidden');

        const toFocus = this.previouslyFocused;
        if (toFocus && typeof toFocus.focus === 'function') {
            window.requestAnimationFrame(() => {
                try {
                    toFocus.focus({ preventScroll: true });
                } catch (error) {
                    // Ignore focus errors triggered by removed or inert elements.
                }
            });
        }
    }

    getTriggerIndex(trigger) {
        const storedIndex = this.triggerIndices.get(trigger);
        if (typeof storedIndex === 'number' && this.photos[storedIndex]) {
            return storedIndex;
        }

        const fallbackIndex = this.triggers.indexOf(trigger);
        if (fallbackIndex !== -1 && this.photos[fallbackIndex]) {
            return fallbackIndex;
        }

        return 0;
    }

    resolveTriggerIndex(trigger, fallbackIndex) {
        const attributeValue = trigger.getAttribute('data-photo-index');
        const parsedIndex = attributeValue === null ? fallbackIndex : Number.parseInt(attributeValue, 10);
        const candidate = Number.isNaN(parsedIndex) ? fallbackIndex : parsedIndex;
        if (this.photos.length === 0) {
            return 0;
        }
        return clamp(candidate, 0, this.photos.length - 1);
    }

    showPrevious() {
        if (this.photos.length <= 1) {
            return;
        }
        const previousIndex = (this.currentIndex - 1 + this.photos.length) % this.photos.length;
        this.setCurrentIndex(previousIndex);
    }

    showNext() {
        if (this.photos.length <= 1) {
            return;
        }
        const nextIndex = (this.currentIndex + 1) % this.photos.length;
        this.setCurrentIndex(nextIndex);
    }

    setCurrentIndex(index) {
        if (!this.photos[index]) {
            return;
        }

        this.currentIndex = index;
        const photo = this.photos[index];

        if (this.image) {
            const altText = photo.alt || photo.label || `Foto ${index + 1}`;
            this.image.setAttribute('src', photo.src);
            this.image.setAttribute('alt', altText);
        }

        if (this.counter) {
            this.counter.textContent = `${index + 1} / ${this.photos.length}`;
        }

        if (this.caption) {
            this.caption.textContent = photo.label || photo.alt || `Foto ${index + 1}`;
        }

        this.updateNavigationState();
    }

    updateNavigationState() {
        const disableNavigation = this.photos.length <= 1;
        if (this.prevButton) {
            this.prevButton.disabled = disableNavigation;
        }
        if (this.nextButton) {
            this.nextButton.disabled = disableNavigation;
        }
    }

    handleKeyDown(event) {
        if (!this.isOpen) {
            return;
        }

        if (event.key === 'Escape') {
            event.preventDefault();
            this.close();
            return;
        }

        if (event.key === 'ArrowLeft') {
            event.preventDefault();
            this.showPrevious();
            return;
        }

        if (event.key === 'ArrowRight') {
            event.preventDefault();
            this.showNext();
            return;
        }

        if (event.key === 'Tab') {
            this.trapFocus(event);
        }
    }

    handleFocusTrap() {
        if (!this.isOpen || !this.dialog) {
            return;
        }

        const focusable = this.getFocusableElements();
        if (focusable.length === 0 && this.panel instanceof HTMLElement) {
            this.panel.focus();
        }
    }

    trapFocus(event) {
        const focusable = this.getFocusableElements();
        if (focusable.length === 0) {
            event.preventDefault();
            return;
        }

        const activeElement = document.activeElement;
        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        const activeIndex = focusable.indexOf(activeElement);

        if (event.shiftKey) {
            if (activeIndex <= 0) {
                event.preventDefault();
                last.focus();
            }
            return;
        }

        if (activeIndex === -1) {
            event.preventDefault();
            first.focus();
            return;
        }

        if (activeIndex === focusable.length - 1) {
            event.preventDefault();
            first.focus();
        }
    }

    getFocusableElements() {
        if (!this.dialog) {
            return [];
        }

        return Array.from(this.dialog.querySelectorAll(FOCUSABLE_SELECTOR))
            .filter((element) => element instanceof HTMLElement
                && isElementVisible(element)
                && !element.hasAttribute('disabled'));
    }
}

const isElementVisible = (element) => {
    if (!(element instanceof HTMLElement)) {
        return false;
    }

    if (typeof element.checkVisibility === 'function') {
        try {
            return element.checkVisibility();
        } catch (error) {
            // Fallback to computed styles below when checkVisibility throws in older browsers
        }
    }

    if (element.hidden || element.closest('[hidden]')) {
        return false;
    }

    const style = window.getComputedStyle(element);
    if (style.display === 'none' || style.visibility === 'hidden' || style.opacity === '0') {
        return false;
    }

    return true;
};

const clamp = (value, min, max) => {
    if (max < min) {
        return min;
    }
    return Math.min(Math.max(value, min), max);
};

export const initialiseGalleries = () => {
    document.querySelectorAll(GALLERY_SELECTOR).forEach((root) => {
        if (!root.__romantauschGallery) {
            root.__romantauschGallery = new RomantauschPhotoGallery(root);
        }
    });
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initialiseGalleries);
} else {
    initialiseGalleries();
}

document.addEventListener('livewire:navigated', initialiseGalleries);
