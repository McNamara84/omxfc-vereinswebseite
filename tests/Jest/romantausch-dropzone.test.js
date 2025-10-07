import { jest } from '@jest/globals';
import { RomantauschDropzone, initRomantauschDropzone } from '../../resources/js/romantausch-dropzone';

describe('RomantauschDropzone', () => {
    const buildMarkup = (maxFiles = 3) => `
        <div class="wrapper">
            <label for="photos" data-dropzone-label>Neue Fotos hochladen</label>
            <div data-romantausch-dropzone data-max-files="${maxFiles}">
                <div data-dropzone-ui class="hidden">
                    <div data-dropzone-area aria-describedby="photos-help photos-size photos-status" role="button" tabindex="0">
                        <span data-dropzone-instruction-text>Ziehe deine Fotos hierher oder klicke, um sie auszuwählen.</span>
                        <span>
                            <span data-dropzone-counter>0</span>
                            /
                            <span data-dropzone-max="true">${maxFiles}</span>
                            Dateien ausgewählt (<span data-dropzone-remaining>${maxFiles}</span> frei)
                        </span>
                    </div>
                    <div id="photos-status" data-dropzone-status aria-live="polite" role="status"></div>
                    <ul data-dropzone-previews class="hidden"></ul>
                </div>
                <div data-dropzone-fallback>
                    <input type="file" id="photos" data-dropzone-input multiple accept="image/*" />
                </div>
            </div>
        </div>
    `;

    const createFile = (name) => new File(['content'], name, { type: 'image/jpeg' });

    let urlCounter = 0;

    beforeAll(() => {
        if (!global.URL.createObjectURL) {
            global.URL.createObjectURL = () => `blob:mock-${Math.random().toString(36).slice(2)}`;
        }
        if (!global.URL.revokeObjectURL) {
            global.URL.revokeObjectURL = () => {};
        }
    });

    beforeEach(() => {
        document.body.innerHTML = '';
        urlCounter = 0;
        jest.spyOn(global.URL, 'createObjectURL').mockImplementation(() => {
            urlCounter += 1;
            return `blob:mock-${urlCounter}`;
        });
        jest.spyOn(global.URL, 'revokeObjectURL').mockImplementation(() => {});
    });

    afterEach(() => {
        jest.restoreAllMocks();
    });

    test('initialises dropzone and hides fallback while keeping accessible status region', () => {
        document.body.innerHTML = buildMarkup();
        initRomantauschDropzone();

        const fallback = document.querySelector('[data-dropzone-fallback]');
        const ui = document.querySelector('[data-dropzone-ui]');
        const status = document.querySelector('[data-dropzone-status]');

        expect(fallback).not.toBeNull();
        expect(fallback.classList.contains('hidden')).toBe(true);
        expect(ui.classList.contains('hidden')).toBe(false);
        expect(status.getAttribute('aria-live')).toBe('polite');
        expect(status.getAttribute('role')).toBe('status');
        expect(status.textContent).toContain('Bereit');
    });

    test('adds previews up to the configured limit and announces when maximum reached', () => {
        document.body.innerHTML = buildMarkup(2);
        const root = document.querySelector('[data-romantausch-dropzone]');
        const dropzone = new RomantauschDropzone(root);
        dropzone.init();

        dropzone.processFiles([createFile('one.jpg')]);
        dropzone.processFiles([createFile('two.jpg'), createFile('three.jpg')]);

        const previews = root.querySelector('[data-dropzone-previews]');
        const status = root.querySelector('[data-dropzone-status]');
        const counter = root.querySelector('[data-dropzone-counter]');
        const area = root.querySelector('[data-dropzone-area]');

        expect(previews.children).toHaveLength(2);
        expect(counter.textContent).toBe('2');
        expect(status.textContent).toContain('maximal 2');
        expect(area.getAttribute('aria-disabled')).toBe('true');
    });

    test('allows removing files and frees slots afterwards', () => {
        document.body.innerHTML = buildMarkup(1);
        const root = document.querySelector('[data-romantausch-dropzone]');
        const dropzone = new RomantauschDropzone(root);
        dropzone.init();

        dropzone.processFiles([createFile('keep.jpg')]);

        const removeButton = root.querySelector('[data-dropzone-remove]');
        expect(removeButton).not.toBeNull();

        removeButton.click();

        const previews = root.querySelector('[data-dropzone-previews]');
        const status = root.querySelector('[data-dropzone-status]');
        const area = root.querySelector('[data-dropzone-area]');

        expect(previews.children).toHaveLength(0);
        expect(status.textContent).toContain('frei');
        expect(area.getAttribute('aria-disabled')).toBeNull();
    });

    test('label click opens file dialog when enabled', () => {
        document.body.innerHTML = buildMarkup(1);
        const root = document.querySelector('[data-romantausch-dropzone]');
        const dropzone = new RomantauschDropzone(root);
        dropzone.init();

        const clickSpy = jest.fn();
        dropzone.input.click = clickSpy;

        const label = document.querySelector('[data-dropzone-label]');
        label.click();

        expect(clickSpy).toHaveBeenCalledTimes(1);
    });
});
