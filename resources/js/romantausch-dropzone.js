const ROOT_SELECTOR = '[data-romantausch-dropzone]';
const REMOVE_BUTTON_CLASS = 'self-start rounded bg-gray-200 px-2 py-1 text-xs font-semibold text-gray-700 transition hover:bg-gray-300 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#8B0116] focus-visible:ring-offset-2 dark:bg-gray-600 dark:text-gray-100 dark:hover:bg-gray-500';
const PREVIEW_CONTAINER_CLASS = 'flex flex-col overflow-hidden rounded-lg border border-gray-200 bg-white text-left shadow-sm dark:border-gray-600 dark:bg-gray-800';
const PREVIEW_IMAGE_WRAPPER_CLASS = 'relative aspect-[4/3] w-full overflow-hidden bg-gray-100 dark:bg-gray-700';
const PREVIEW_IMAGE_CLASS = 'h-full w-full object-cover';
const PREVIEW_INFO_CLASS = 'flex flex-col gap-1 px-3 py-2';
const PREVIEW_NAME_CLASS = 'truncate text-sm font-medium text-gray-800 dark:text-gray-100';
const PREVIEW_SIZE_CLASS = 'text-xs text-gray-600 dark:text-gray-300';
const ACTIVE_DRAG_CLASSES = ['border-[#8B0116]', 'bg-white', 'dark:border-[#FF6B81]', 'dark:bg-gray-800'];

const formatFileSize = (bytes) => {
    if (typeof bytes !== 'number' || Number.isNaN(bytes)) {
        return '';
    }

    const kilobytes = bytes / 1024;
    if (kilobytes >= 1024) {
        const megabytes = kilobytes / 1024;
        return new Intl.NumberFormat('de-DE', { maximumFractionDigits: 1, minimumFractionDigits: 0 }).format(megabytes) + ' MB';
    }

    return new Intl.NumberFormat('de-DE', { maximumFractionDigits: 0 }).format(Math.max(1, kilobytes)) + ' KB';
};

const createFileListFallback = (files) => {
    const list = {
        length: files.length,
        item(index) {
            return this[index] ?? null;
        },
    };

    files.forEach((file, index) => {
        list[index] = file;
    });

    return list;
};

const toArray = (files) => {
    if (!files) {
        return [];
    }

    return Array.from(files).filter(Boolean);
};

const isActivationKey = (event) => {
    return event.key === 'Enter' || event.key === ' ' || event.key === 'Spacebar';
};

const ACCEPTED_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
const ACCEPTED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

const isAcceptedFile = (file) => {
    if (!(file instanceof File)) {
        return false;
    }

    if (file.type && ACCEPTED_TYPES.includes(file.type)) {
        return true;
    }

    const extension = file.name.split('.').pop();
    if (!extension) {
        return false;
    }

    return ACCEPTED_EXTENSIONS.includes(extension.toLowerCase());
};

export class RomantauschDropzone {
    constructor(root) {
        this.root = root;
        this.input = root.querySelector('[data-dropzone-input]');
        this.ui = root.querySelector('[data-dropzone-ui]');
        this.fallback = root.querySelector('[data-dropzone-fallback]');
        this.area = root.querySelector('[data-dropzone-area]');
        this.previews = root.querySelector('[data-dropzone-previews]');
        this.status = root.querySelector('[data-dropzone-status]');
        this.counter = root.querySelector('[data-dropzone-counter]');
        this.remaining = root.querySelector('[data-dropzone-remaining]');
        this.instruction = root.querySelector('[data-dropzone-instruction-text]');
        this.label = root.parentElement ? root.parentElement.querySelector('[data-dropzone-label]') : null;
        this.files = [];
        this.objectUrls = new Map();
        const parsedMaxFiles = Number.parseInt(root.getAttribute('data-max-files') ?? '', 10);
        this.maxFiles = Number.isNaN(parsedMaxFiles) ? 3 : Math.max(0, parsedMaxFiles);
        this.isDisabled = this.maxFiles <= 0;

        this.handleInputChange = this.handleInputChange.bind(this);
        this.handleAreaClick = this.handleAreaClick.bind(this);
        this.handleAreaKeyDown = this.handleAreaKeyDown.bind(this);
        this.handleDragOver = this.handleDragOver.bind(this);
        this.handleDragEnter = this.handleDragEnter.bind(this);
        this.handleDragLeave = this.handleDragLeave.bind(this);
        this.handleDrop = this.handleDrop.bind(this);
        this.handleLabelClick = this.handleLabelClick.bind(this);
    }

    init() {
        if (!this.input) {
            return;
        }

        if (this.ui) {
            this.ui.classList.remove('hidden');
        }

        if (this.fallback) {
            this.fallback.classList.add('hidden');
        }

        this.input.setAttribute('aria-hidden', 'true');
        this.input.setAttribute('tabindex', '-1');
        this.input.classList.add('sr-only');

        this.input.addEventListener('change', this.handleInputChange);

        if (this.area) {
            this.area.addEventListener('click', this.handleAreaClick);
            this.area.addEventListener('keydown', this.handleAreaKeyDown);
            this.area.addEventListener('dragover', this.handleDragOver);
            this.area.addEventListener('dragenter', this.handleDragEnter);
            this.area.addEventListener('dragleave', this.handleDragLeave);
            this.area.addEventListener('drop', this.handleDrop);
        }

        if (this.label instanceof HTMLElement) {
            this.label.addEventListener('click', this.handleLabelClick);
        }

        this.updateCounter();
        this.updateAvailability();
        this.renderPreviews();
        this.announceInitialState();
    }

    getLimitAnnouncement() {
        if (this.maxFiles === 0) {
            return 'Du kannst aktuell keine weiteren Fotos hinzufügen. Entferne zuerst ein bestehendes Foto.';
        }

        return `Du kannst maximal ${this.maxFiles} ${this.maxFiles === 1 ? 'Foto' : 'Fotos'} auswählen.`;
    }

    announceInitialState() {
        if (this.maxFiles === 0) {
            this.announce(this.getLimitAnnouncement());
        } else {
            this.announce(`Bereit. Du kannst noch ${this.maxFiles} ${this.maxFiles === 1 ? 'Foto' : 'Fotos'} auswählen.`);
        }
    }

    handleInputChange(event) {
        this.processFiles(event.target.files);
        event.target.value = '';
    }

    handleAreaClick(event) {
        event.preventDefault();
        this.openFileDialog();
    }

    handleLabelClick(event) {
        event.preventDefault();
        this.openFileDialog();
    }

    handleAreaKeyDown(event) {
        if (!isActivationKey(event)) {
            return;
        }

        event.preventDefault();
        this.openFileDialog();
    }

    handleDragOver(event) {
        if (this.isDisabled) {
            return;
        }

        event.preventDefault();
        event.dataTransfer.dropEffect = 'copy';
    }

    handleDragEnter(event) {
        if (this.isDisabled) {
            return;
        }

        event.preventDefault();
        this.setDragActive(true);
    }

    handleDragLeave(event) {
        if (!this.area || !event.currentTarget) {
            return;
        }

        if (event.currentTarget.contains(event.relatedTarget)) {
            return;
        }

        this.setDragActive(false);
    }

    handleDrop(event) {
        event.preventDefault();
        this.setDragActive(false);
        this.processFiles(event.dataTransfer ? event.dataTransfer.files : []);
    }

    openFileDialog() {
        if (this.isDisabled) {
            this.announce(this.getLimitAnnouncement());
            return;
        }

        if (typeof this.input.click === 'function') {
            this.input.click();
        }
    }

    setDragActive(isActive) {
        if (!this.area) {
            return;
        }

        ACTIVE_DRAG_CLASSES.forEach((className) => {
            this.area.classList.toggle(className, isActive);
        });
    }

    processFiles(fileList) {
        if (this.isDisabled) {
            this.announce(this.getLimitAnnouncement());
            return;
        }

        const incoming = toArray(fileList);
        if (incoming.length === 0) {
            return;
        }

        const acceptedFiles = [];
        const rejectedFiles = [];
        incoming.forEach((file) => {
            if (isAcceptedFile(file)) {
                acceptedFiles.push(file);
            } else {
                rejectedFiles.push(file);
            }
        });

        const messages = [];
        if (rejectedFiles.length > 0) {
            messages.push(`${rejectedFiles.length} ${rejectedFiles.length === 1 ? 'Datei' : 'Dateien'} wurden ignoriert, weil sie kein unterstütztes Bildformat haben.`);
        }

        let availableSlots = this.maxFiles - this.files.length;
        if (availableSlots <= 0) {
            messages.push(this.getLimitAnnouncement());
            this.announce(messages.join(' '));
            return;
        }

        const filesToAdd = acceptedFiles.slice(0, availableSlots);
        if (filesToAdd.length > 0) {
            filesToAdd.forEach((file) => {
                this.addFile(file);
            });

            this.updateInputFiles();
            this.renderPreviews();
            this.updateCounter();
            this.updateAvailability();

            availableSlots = this.maxFiles - this.files.length;
            messages.push(`${filesToAdd.length} ${filesToAdd.length === 1 ? 'Foto wurde' : 'Fotos wurden'} hinzugefügt. ${availableSlots > 0 ? `Noch ${availableSlots} ${availableSlots === 1 ? 'Foto' : 'Fotos'} frei.` : 'Es sind keine freien Plätze mehr verfügbar.'}`);
        }

        if (acceptedFiles.length > filesToAdd.length) {
            messages.push(`Es konnten nur ${filesToAdd.length} ${filesToAdd.length === 1 ? 'Datei' : 'Dateien'} übernommen werden, da maximal ${this.maxFiles} Fotos erlaubt sind.`);
        }

        if (messages.length > 0) {
            this.announce(messages.join(' '));
        }

        this.input.value = '';
    }

    addFile(file) {
        this.files.push(file);
        if (!this.objectUrls.has(file)) {
            const url = URL.createObjectURL(file);
            this.objectUrls.set(file, url);
        }
    }

    renderPreviews() {
        if (!this.previews) {
            return;
        }

        this.previews.innerHTML = '';

        if (this.files.length === 0) {
            this.previews.classList.add('hidden');
            return;
        }

        this.previews.classList.remove('hidden');

        this.files.forEach((file, index) => {
            const container = document.createElement('li');
            container.className = PREVIEW_CONTAINER_CLASS;

            const imageWrapper = document.createElement('div');
            imageWrapper.className = PREVIEW_IMAGE_WRAPPER_CLASS;
            const image = document.createElement('img');
            image.className = PREVIEW_IMAGE_CLASS;
            let objectUrl = this.objectUrls.get(file);
            if (!objectUrl) {
                objectUrl = URL.createObjectURL(file);
                this.objectUrls.set(file, objectUrl);
            }
            image.src = objectUrl;
            image.alt = `${file.name} Vorschau`;
            imageWrapper.appendChild(image);
            container.appendChild(imageWrapper);

            const info = document.createElement('div');
            info.className = PREVIEW_INFO_CLASS;
            const name = document.createElement('p');
            name.className = PREVIEW_NAME_CLASS;
            name.textContent = file.name;
            const size = document.createElement('p');
            size.className = PREVIEW_SIZE_CLASS;
            size.textContent = formatFileSize(file.size);
            const removeButton = document.createElement('button');
            removeButton.type = 'button';
            removeButton.className = REMOVE_BUTTON_CLASS;
            removeButton.textContent = 'Entfernen';
            removeButton.setAttribute('data-dropzone-remove', String(index));
            removeButton.setAttribute('aria-label', `${file.name} entfernen`);
            removeButton.addEventListener('click', () => {
                this.removeFile(index);
            });

            info.appendChild(name);
            info.appendChild(size);
            info.appendChild(removeButton);
            container.appendChild(info);

            this.previews.appendChild(container);
        });
    }

    removeFile(index) {
        if (index < 0 || index >= this.files.length) {
            return;
        }

        const [removed] = this.files.splice(index, 1);
        if (removed) {
            const url = this.objectUrls.get(removed);
            if (url) {
                URL.revokeObjectURL(url);
                this.objectUrls.delete(removed);
            }
        }

        this.updateInputFiles();
        this.renderPreviews();
        this.updateCounter();
        this.updateAvailability();

        const remaining = this.maxFiles - this.files.length;
        this.announce(`Foto entfernt. Noch ${remaining} ${remaining === 1 ? 'Foto' : 'Fotos'} frei.`);
    }

    updateCounter() {
        if (this.counter) {
            this.counter.textContent = String(this.files.length);
        }
    }

    updateAvailability() {
        const remainingSlots = Math.max(0, this.maxFiles - this.files.length);
        this.isDisabled = remainingSlots <= 0;

        if (this.remaining) {
            this.remaining.textContent = String(remainingSlots);
        }

        if (this.area) {
            if (this.isDisabled) {
                this.area.setAttribute('aria-disabled', 'true');
                this.area.classList.add('cursor-not-allowed', 'opacity-60');
            } else {
                this.area.removeAttribute('aria-disabled');
                this.area.classList.remove('cursor-not-allowed', 'opacity-60');
            }
        }

        if (this.instruction) {
            if (this.isDisabled) {
                this.instruction.textContent = 'Du hast die maximale Anzahl an Fotos erreicht. Entferne ein Foto, um ein neues hinzuzufügen.';
            } else {
                this.instruction.textContent = 'Ziehe deine Fotos hierher oder klicke, um sie auszuwählen.';
            }
        }
    }

    updateInputFiles() {
        if (!this.input) {
            return;
        }

        if (typeof DataTransfer !== 'undefined') {
            const transfer = new DataTransfer();
            this.files.forEach((file) => transfer.items.add(file));
            this.input.files = transfer.files;
            return;
        }

        const fallback = createFileListFallback(this.files);
        Object.defineProperty(this.input, 'files', {
            configurable: true,
            get() {
                return fallback;
            },
            set() {
                throw new Error('romantausch-dropzone: Das manuelle Setzen von files wird im Fallback-Modus nicht unterstützt.');
            },
        });
    }

    announce(message) {
        if (!this.status) {
            return;
        }

        this.status.textContent = message;
    }
}

export const initRomantauschDropzone = (root = document) => {
    if (!root) {
        return;
    }

    const elements = root.querySelectorAll(ROOT_SELECTOR);
    elements.forEach((element) => {
        if (element.__romantauschDropzone) {
            return;
        }

        const instance = new RomantauschDropzone(element);
        instance.init();
        element.__romantauschDropzone = instance;
    });
};

if (typeof document !== 'undefined') {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => initRomantauschDropzone());
    } else {
        initRomantauschDropzone();
    }

    document.addEventListener('livewire:navigated', () => initRomantauschDropzone());
}

export default initRomantauschDropzone;
