import { jest } from '@jest/globals';
import { RomantauschPhotoGallery } from '../../resources/js/romantausch-gallery.js';

describe('RomantauschPhotoGallery', () => {
    let gallery;
    let root;
    let originalRAF;

    const createGalleryMarkup = () => `
        <div data-romantausch-gallery>
            <button
                type="button"
                data-photo-dialog-trigger
                data-photo-src="https://example.com/photo-1.jpg"
                data-photo-alt="Foto 1 von Beispiel"
                data-photo-label="Foto 1 von Beispiel"
                data-photo-index="0"
            ></button>
            <button
                type="button"
                data-photo-dialog-trigger
                data-photo-src="https://example.com/photo-2.jpg"
                data-photo-alt="Foto 2 von Beispiel"
                data-photo-label="Foto 2 von Beispiel"
                data-photo-index="1"
            ></button>
            <button
                type="button"
                data-photo-dialog-trigger
                data-photo-src="https://example.com/photo-3.jpg"
                data-photo-alt=""
                data-photo-label=""
                data-photo-index="2"
            ></button>
            <div class="hidden" data-photo-dialog>
                <div data-photo-dialog-overlay></div>
                <div data-photo-dialog-panel tabindex="-1">
                    <button data-photo-dialog-close></button>
                    <button data-photo-dialog-prev></button>
                    <button hidden data-hidden-test></button>
                    <img
                        src="https://example.com/photo-1.jpg"
                        alt="Foto 1 von Beispiel"
                        data-photo-dialog-image
                    >
                    <button data-photo-dialog-next></button>
                    <div>
                        <span data-photo-dialog-counter>1 / 3</span>
                        <span data-photo-dialog-caption>Foto 1 von Beispiel</span>
                    </div>
                </div>
            </div>
        </div>
    `;

    beforeEach(() => {
        document.body.innerHTML = createGalleryMarkup();
        root = document.querySelector('[data-romantausch-gallery]');
        gallery = new RomantauschPhotoGallery(root);
        originalRAF = window.requestAnimationFrame;
    });

    afterEach(() => {
        gallery.close();
        document.body.innerHTML = '';
        document.body.className = '';
        window.requestAnimationFrame = originalRAF;
    });

    const clickTrigger = (index) => {
        const trigger = root.querySelectorAll('[data-photo-dialog-trigger]')[index];
        trigger.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));
    };

    test('opens the dialog with the clicked thumbnail image', () => {
        clickTrigger(1);

        const image = root.querySelector('[data-photo-dialog-image]');
        const caption = root.querySelector('[data-photo-dialog-caption]');
        const counter = root.querySelector('[data-photo-dialog-counter]');
        const dialog = root.querySelector('[data-photo-dialog]');

        expect(dialog.classList.contains('hidden')).toBe(false);
        expect(dialog.getAttribute('aria-hidden')).toBeNull();
        expect(image.getAttribute('src')).toBe('https://example.com/photo-2.jpg');
        expect(image.getAttribute('alt')).toBe('Foto 2 von Beispiel');
        expect(caption.textContent).toBe('Foto 2 von Beispiel');
        expect(counter.textContent).toBe('2 / 3');
    });

    test('falls back to a generated caption when no label is provided', () => {
        clickTrigger(2);

        const image = root.querySelector('[data-photo-dialog-image]');
        const caption = root.querySelector('[data-photo-dialog-caption]');

        expect(image.getAttribute('src')).toBe('https://example.com/photo-3.jpg');
        expect(image.getAttribute('alt')).toBe('Foto 3');
        expect(caption.textContent).toBe('Foto 3');
    });

    test('focus trapping skips hidden controls and wraps correctly', () => {
        clickTrigger(0);

        const dialog = root.querySelector('[data-photo-dialog]');
        const focusable = gallery.getFocusableElements();
        const first = focusable[0];
        const last = focusable[focusable.length - 1];

        expect(focusable.some((el) => el.hasAttribute('data-hidden-test'))).toBe(false);

        last.focus();
        dialog.dispatchEvent(new KeyboardEvent('keydown', { key: 'Tab', bubbles: true, cancelable: true }));
        expect(document.activeElement).toBe(first);

        first.focus();
        dialog.dispatchEvent(new KeyboardEvent('keydown', { key: 'Tab', shiftKey: true, bubbles: true, cancelable: true }));
        expect(document.activeElement).toBe(last);
    });

    test('closing the dialog keeps aria-hidden off while hiding the panel', () => {
        clickTrigger(0);
        gallery.close();

        const dialog = root.querySelector('[data-photo-dialog]');

        expect(dialog.classList.contains('hidden')).toBe(true);
        expect(dialog.hasAttribute('aria-hidden')).toBe(false);
    });

    test('clicking a trigger uses the cached photo index', () => {
        const trigger = root.querySelectorAll('[data-photo-dialog-trigger]')[1];
        const originalGetAttribute = trigger.getAttribute.bind(trigger);
        trigger.getAttribute = jest.fn(() => {
            throw new Error('Unexpected attribute access after initialisation');
        });

        expect(() => clickTrigger(1)).not.toThrow();

        const image = root.querySelector('[data-photo-dialog-image]');
        expect(image.getAttribute('src')).toBe('https://example.com/photo-2.jpg');

        trigger.getAttribute = originalGetAttribute;
    });

    test('opening the dialog swallows focus errors when the focus target disappears', () => {
        const closeButton = root.querySelector('[data-photo-dialog-close]');
        const originalFocus = closeButton.focus;

        window.requestAnimationFrame = (callback) => {
            callback();
            return 1;
        };

        closeButton.focus = jest.fn(() => {
            throw new Error('Focus failed');
        });

        expect(() => clickTrigger(0)).not.toThrow();

        closeButton.focus = originalFocus;
    });

    test('closing the dialog ignores focus errors from the previously focused trigger', () => {
        const trigger = root.querySelector('[data-photo-dialog-trigger]');
        const originalFocus = trigger.focus;

        window.requestAnimationFrame = (callback) => {
            callback();
            return 1;
        };

        clickTrigger(0);

        trigger.focus = jest.fn(() => {
            throw new Error('Focus failed on close');
        });

        expect(() => gallery.close()).not.toThrow();

        trigger.focus = originalFocus;
    });
});
