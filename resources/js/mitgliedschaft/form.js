const FIELD_RULES = {
    vorname: { regex: /.+/, error: 'Vorname ist erforderlich.' },
    nachname: { regex: /.+/, error: 'Nachname ist erforderlich.' },
    strasse: { regex: /.+/, error: 'Straße ist erforderlich.' },
    hausnummer: { regex: /.+/, error: 'Hausnummer ist erforderlich.' },
    plz: { regex: /^\d{4,6}$/, error: 'Bitte gültige PLZ eingeben (4-6 Zahlen).' },
    stadt: { regex: /.+/, error: 'Stadt ist erforderlich.' },
    land: { regex: /^(Deutschland|Österreich|Schweiz)$/, error: 'Bitte wähle dein Land.' },
    mail: { regex: /^[^@\s]+@[^@\s]+\.[^@\s]+$/, error: 'Bitte gültige Mailadresse eingeben.' },
    passwort: { regex: /^.{6,}$/, error: 'Passwort mindestens 6 Zeichen.' },
    passwort_confirmation: { matchWith: 'passwort', error: 'Passwörter stimmen nicht überein.' },
    telefon: {
        regex: /^(\+\d{1,3}\s?)?(\d{4,14})$/,
        error: 'Bitte gültige Handynummer eingeben.',
        optional: true,
    },
    verein_gefunden: {
        regex: /^(Facebook|Instagram|Leserkontaktseite|Befreundete Person|Fantreffen\/MaddraxCon|Google|Sonstiges)$/,
        error: 'Bitte wähle eine Option aus.',
        optional: true,
    },
};

function resolveDocument(root, customDocument) {
    if (customDocument) {
        return customDocument;
    }

    if (root?.ownerDocument) {
        return root.ownerDocument;
    }

    return root ?? document;
}

export function createMitgliedschaftForm(root = document, options = {}) {
    const win = options.window ?? window;
    const fetchImpl = options.fetch ?? win.fetch;
    const doc = resolveDocument(root, options.document);

    const form = doc?.getElementById('mitgliedschaft-form');

    if (!form || form.dataset.enhanced === 'true') {
        return null;
    }

    const beitrag = doc.getElementById('mitgliedsbeitrag');
    const beitragOutputId = beitrag?.dataset.outputTarget || 'beitrag-output';
    const beitragOutput = doc.getElementById(beitragOutputId);
    const satzungCheck = doc.getElementById('satzung_check');
    const submitButton = doc.getElementById('submit-button');
    const loadingIndicator = doc.getElementById('loading-indicator');
    const messages = doc.getElementById('form-messages');
    const csrfToken = () => doc.querySelector('meta[name="csrf-token"]')?.content ?? '';
    const consoleRef = options.console ?? win.console;
    const missingFieldWarnings = new Set();

    const successUrl = form.dataset.successUrl || `${win.location.origin}/mitglied-werden/erfolgreich`;

    function updateContributionOutput() {
        if (!beitrag || !beitragOutput) {
            return;
        }

        const prefix = beitrag.dataset.outputPrefix || '';
        const suffix = beitrag.dataset.outputSuffix || '';
        beitragOutput.textContent = `${prefix}${beitrag.value}${suffix}`;
    }

    function toggleSubmit(forceDisabled) {
        if (!submitButton) {
            return;
        }

        const isCheckboxChecked = satzungCheck ? satzungCheck.checked : true;
        const shouldDisable =
            typeof forceDisabled === 'boolean' ? forceDisabled : !isCheckboxChecked;

        submitButton.disabled = shouldDisable;
        submitButton.setAttribute('aria-disabled', shouldDisable ? 'true' : 'false');
        submitButton.classList.toggle('opacity-50', shouldDisable);
        submitButton.classList.toggle('cursor-not-allowed', shouldDisable);
    }

    function getErrorElement(id) {
        return (
            doc.getElementById(`error-${id}`) ||
            doc.querySelector(`[data-error-for="${id}"]`)
        );
    }

    function warnMissingField(id) {
        if (missingFieldWarnings.has(id)) {
            return;
        }

        missingFieldWarnings.add(id);

        if (consoleRef && typeof consoleRef.warn === 'function') {
            consoleRef.warn(
                `[Mitgliedschaftsformular] Feld mit ID "${id}" wurde nicht gefunden. Bitte überprüfe die Formularstruktur.`,
            );
        }
    }

    function setFieldError(input, message) {
        const errorElem = getErrorElement(input.id);

        if (errorElem) {
            errorElem.textContent = message;
        }

        if (message) {
            input.setCustomValidity(message);
            input.setAttribute('aria-invalid', 'true');
            return false;
        }

        input.setCustomValidity('');
        input.removeAttribute('aria-invalid');
        return true;
    }

    function validateForm() {
        let isValid = true;

        for (const [id, rules] of Object.entries(FIELD_RULES)) {
            const input = doc.getElementById(id);

            if (!input) {
                warnMissingField(id);
                continue;
            }

            if (rules.optional && !input.value.trim()) {
                setFieldError(input, '');
                continue;
            }

            if (rules.matchWith) {
                const matchElem = doc.getElementById(rules.matchWith);
                const hasError = input.value !== (matchElem?.value ?? '');
                isValid = setFieldError(input, hasError ? rules.error : '') && isValid;
            } else {
                const hasError = !rules.regex.test(input.value.trim());
                isValid = setFieldError(input, hasError ? rules.error : '') && isValid;
            }
        }

        toggleSubmit();
        return isValid;
    }

    function setLoading(isLoading) {
        if (!submitButton || !loadingIndicator) {
            return;
        }

        if (isLoading) {
            submitButton.disabled = true;
            submitButton.setAttribute('aria-disabled', 'true');
            submitButton.classList.add('hidden');
            loadingIndicator.classList.remove('hidden');
            return;
        }

        loadingIndicator.classList.add('hidden');
        submitButton.classList.remove('hidden');
        toggleSubmit();
    }

    function showMessage(type, text) {
        if (!messages) {
            return;
        }

        const baseClasses = 'mb-4 p-4 border rounded';
        const variants = {
            error: `${baseClasses} bg-red-100 border-red-400 text-red-800`,
            success: `${baseClasses} bg-green-100 border-green-400 text-green-800`,
        };

        messages.textContent = text;
        messages.className = variants[type] ?? baseClasses;
        messages.classList.remove('hidden');
        messages.scrollIntoView({ behavior: 'smooth' });
    }

    function clearMessage() {
        if (!messages) {
            return;
        }

        messages.textContent = '';
        messages.className = 'mb-4 hidden';
    }

    function submitWithFallback() {
        setLoading(false);
        form.submit();
    }

    async function handleSubmit(event) {
        const canUseFetch = typeof fetchImpl === 'function';

        if (canUseFetch) {
            event?.preventDefault?.();
        } else {
            return;
        }

        if (!validateForm() || (satzungCheck && !satzungCheck.checked)) {
            return;
        }

        clearMessage();
        setLoading(true);

        const formData = new FormData(form);

        try {
            const response = await fetchImpl(form.action || '/mitglied-werden', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken(),
                    Accept: 'application/json',
                },
                body: formData,
                credentials: 'same-origin',
            });

            let result = null;

            try {
                result = await response.clone().json();
            } catch (jsonError) {
                if (consoleRef && typeof consoleRef.error === 'function') {
                    consoleRef.error('JSON parsing error:', jsonError);
                }
            }

            if ((response.ok && result?.success) || result?.success) {
                win.location.assign(successUrl);
                return;
            }

            if (response.status === 422 && result?.errors) {
                for (const [field, messagesForField] of Object.entries(result.errors)) {
                    const input = doc.getElementById(field);
                    const message = Array.isArray(messagesForField)
                        ? messagesForField.join(' ')
                        : String(messagesForField ?? '');

                    if (input) {
                        setFieldError(input, message);
                    } else {
                        warnMissingField(field);
                        const errorElem = getErrorElement(field);
                        if (errorElem) {
                            errorElem.textContent = message;
                        }
                    }
                }

                showMessage('error', 'Bitte korrigiere die markierten Felder.');
                setLoading(false);
                return;
            }

            showMessage('error', 'Unbekannter Fehler aufgetreten.');
            setLoading(false);
        } catch (error) {
            if (error?.name === 'AbortError') {
                submitWithFallback();
                return;
            }

            showMessage('error', 'Ein unerwarteter Fehler ist aufgetreten. Bitte versuche es später erneut.');

            if (consoleRef && typeof consoleRef.error === 'function') {
                consoleRef.error('Fehler:', error);
            }

            setLoading(false);
        }
    }

    function init() {
        if (form.dataset.enhanced === 'true') {
            return null;
        }

        form.dataset.enhanced = 'true';

        updateContributionOutput();

        if (beitrag) {
            beitrag.addEventListener('input', updateContributionOutput);
            beitrag.addEventListener('change', updateContributionOutput);
        }

        if (satzungCheck) {
            satzungCheck.addEventListener('change', toggleSubmit);
        }

        form.addEventListener('input', validateForm);
        form.addEventListener('change', validateForm);
        form.addEventListener('submit', handleSubmit);

        toggleSubmit();

        return controller;
    }

    function destroy() {
        if (beitrag) {
            beitrag.removeEventListener('input', updateContributionOutput);
            beitrag.removeEventListener('change', updateContributionOutput);
        }

        if (satzungCheck) {
            satzungCheck.removeEventListener('change', toggleSubmit);
        }

        form.removeEventListener('input', validateForm);
        form.removeEventListener('change', validateForm);
        form.removeEventListener('submit', handleSubmit);

        delete form.dataset.enhanced;
    }

    const controller = {
        init,
        destroy,
        validateForm,
        toggleSubmit,
        updateContributionOutput,
        handleSubmit,
    };

    return controller;
}

export function initMitgliedschaftForm(root = document, options = {}) {
    const controller = createMitgliedschaftForm(root, options);

    if (!controller) {
        return null;
    }

    return controller.init();
}

export default initMitgliedschaftForm;
