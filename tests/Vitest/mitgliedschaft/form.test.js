import { beforeEach, describe, expect, it, vi } from 'vitest';
import { initMitgliedschaftForm } from '../../../resources/js/mitgliedschaft/form';

const ALL_FIELDS = [
    'vorname',
    'nachname',
    'strasse',
    'hausnummer',
    'plz',
    'stadt',
    'land',
    'mail',
    'passwort',
    'passwort_confirmation',
    'mitgliedsbeitrag',
    'telefon',
    'verein_gefunden',
];

function buildFormHtml() {
    return `
        <meta name="csrf-token" content="csrf-token-value">
        <div id="form-messages" class="hidden"></div>
        <form id="mitgliedschaft-form" action="/mitglied-werden" data-success-url="/mitglied-werden/erfolgreich">
            ${ALL_FIELDS.map((field) => {
                if (field === 'mitgliedsbeitrag') {
                    return `
                        <label for="mitgliedsbeitrag"></label>
                        <input id="mitgliedsbeitrag" value="12" data-output-target="beitrag-output" data-output-suffix="€">
                        <span id="beitrag-output"></span>
                    `;
                }

                if (field === 'passwort_confirmation') {
                    return `<input id="passwort_confirmation" value="secret123">`;
                }

                if (field === 'telefon' || field === 'verein_gefunden') {
                    return `<input id="${field}" value="">`;
                }

                if (field === 'land') {
                    return `<select id="land"><option value="Deutschland" selected>Deutschland</option></select>`;
                }

                if (field === 'mail') {
                    return `<input id="mail" type="email" value="max@example.com">`;
                }

                if (field === 'plz') {
                    return `<input id="plz" value="12345">`;
                }

                return `<input id="${field}" value="secret123">`;
            }).join('')}
            <div class="col-span-2">
                <input type="checkbox" id="satzung_check" required checked>
            </div>
            <button type="submit" id="submit-button" class="opacity-50 cursor-not-allowed" disabled>Antrag absenden</button>
            <div id="loading-indicator" class="hidden"></div>
        </form>
    `;
}

function setupDom() {
    document.body.innerHTML = buildFormHtml();

    const form = document.getElementById('mitgliedschaft-form');
    const submitButton = document.getElementById('submit-button');
    const loadingIndicator = document.getElementById('loading-indicator');
    const formMessages = document.getElementById('form-messages');
    formMessages.scrollIntoView = vi.fn();

    return { form, submitButton, loadingIndicator, formMessages };
}

beforeEach(() => {
    document.body.innerHTML = '';
    vi.restoreAllMocks();
});

describe('initMitgliedschaftForm', () => {
    it('submits via fetch and redirects on success', async () => {
        const { form, submitButton, loadingIndicator } = setupDom();
        const fetchMock = vi.fn().mockResolvedValue({
            ok: true,
            status: 200,
            clone: () => ({ json: () => Promise.resolve({ success: true }) }),
        });
        const assignMock = vi.fn();
        const consoleMock = { warn: vi.fn(), error: vi.fn() };
        const windowMock = { location: { assign: assignMock, origin: 'https://example.test' } };

        initMitgliedschaftForm(document, {
            fetch: fetchMock,
            window: windowMock,
            console: consoleMock,
        });

        const submitEvent = new Event('submit', { bubbles: true, cancelable: true });
        form.dispatchEvent(submitEvent);

        await vi.waitFor(() => expect(fetchMock).toHaveBeenCalled());

        const [url, options] = fetchMock.mock.calls[0];
        expect(url).toContain('/mitglied-werden');
        expect(options.method).toBe('POST');

        expect(submitButton.classList.contains('hidden')).toBe(true);
        expect(loadingIndicator.classList.contains('hidden')).toBe(false);
        await vi.waitFor(() =>
            expect(assignMock).toHaveBeenCalledWith('/mitglied-werden/erfolgreich'),
        );
    });

    it('resets the button state and shows errors when fetch fails', async () => {
        const { form, submitButton, loadingIndicator, formMessages } = setupDom();
        const fetchMock = vi.fn().mockResolvedValue({
            ok: false,
            status: 500,
            clone: () => ({ json: () => Promise.resolve({}) }),
        });
        const consoleMock = { warn: vi.fn(), error: vi.fn() };
        const windowMock = { location: { assign: vi.fn(), origin: 'https://example.test' } };

        initMitgliedschaftForm(document, {
            fetch: fetchMock,
            window: windowMock,
            console: consoleMock,
        });

        const submitEvent = new Event('submit', { bubbles: true, cancelable: true });
        form.dispatchEvent(submitEvent);

        await vi.waitFor(() => expect(fetchMock).toHaveBeenCalled());
        await vi.waitFor(() => expect(submitButton.disabled).toBe(false));
        expect(submitButton.classList.contains('hidden')).toBe(false);
        expect(loadingIndicator.classList.contains('hidden')).toBe(true);
        expect(formMessages.className).not.toContain('hidden');
        expect(formMessages.textContent).toBe('Unbekannter Fehler aufgetreten.');
    });

    it('falls back to native submission when fetch is unavailable', () => {
        const { form, submitButton } = setupDom();
        const preventDefault = vi.fn();

        const controller = initMitgliedschaftForm(document, {
            fetch: undefined,
            window: { location: { assign: vi.fn(), origin: 'https://example.test' } },
            console: { warn: vi.fn(), error: vi.fn() },
        });

        expect(controller).not.toBeNull();

        controller.handleSubmit({ preventDefault });

        expect(preventDefault).not.toHaveBeenCalled();
        expect(submitButton.disabled).toBe(false);
    });

    it('submits the form natively when fetch is aborted', async () => {
        const { form, submitButton, loadingIndicator } = setupDom();
        const abortError = { name: 'AbortError' };
        const fetchMock = vi.fn().mockRejectedValue(abortError);
        const submitSpy = vi.spyOn(form, 'submit').mockImplementation(() => {});
        const windowMock = { location: { assign: vi.fn(), origin: 'https://example.test' } };

        initMitgliedschaftForm(document, {
            fetch: fetchMock,
            window: windowMock,
            console: { warn: vi.fn(), error: vi.fn() },
        });

        const submitEvent = new Event('submit', { bubbles: true, cancelable: true });
        form.dispatchEvent(submitEvent);

        await vi.waitFor(() => expect(submitSpy).toHaveBeenCalledTimes(1));

        expect(fetchMock).toHaveBeenCalled();
        expect(submitButton.classList.contains('hidden')).toBe(false);
        expect(loadingIndicator.classList.contains('hidden')).toBe(true);
    });
});
