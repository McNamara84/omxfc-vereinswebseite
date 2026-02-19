/**
 * Mitglied-werden Formularvalidierung und Submit-Logik.
 *
 * Wird über app.js gebundelt geladen. Guard-Pattern: Initialisierung
 * läuft nur, wenn das Formular (#mitgliedschaft-form) auf der Seite existiert.
 */

function initMitgliedWerden() {
    const form = document.getElementById('mitgliedschaft-form');
    if (!form) return;

    const satzungCheck = document.getElementById('satzung_check');
    const submitButton = document.getElementById('submit-button');
    if (!satzungCheck || !submitButton) return;

    // Verhindere doppelte Initialisierung bei Full-Reload (DOMContentLoaded + livewire:navigated)
    if (form.dataset.initialized) return;
    form.dataset.initialized = 'true';

    const missingFieldWarnings = new Set();

    const fields = {
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
            regex: /^(\+\d{1,3})?\d{4,14}$/,
            normalize: v => v.replace(/[\s\-\/()]/g, ''),
            error: 'Bitte gültige Handynummer eingeben.',
            optional: true,
        },
        verein_gefunden: {
            regex: /^(Facebook|Instagram|Leserkontaktseite|Befreundete Person|Fantreffen\/MaddraxCon|Google|Sonstiges)$/,
            error: 'Bitte wähle eine Option aus.',
            optional: true,
        },
    };

    satzungCheck.addEventListener('change', toggleSubmit);
    form.addEventListener('input', validateForm);
    form.addEventListener('submit', handleSubmit);

    function toggleSubmit() {
        submitButton.disabled = !satzungCheck.checked || !form.checkValidity();
        submitButton.classList.toggle('opacity-50', submitButton.disabled);
        submitButton.classList.toggle('cursor-not-allowed', submitButton.disabled);
    }

    function getErrorElement(id) {
        return (
            document.getElementById(`error-${id}`) ||
            document.querySelector(`[data-error-for="${id}"]`)
        );
    }

    function warnMissingField(id) {
        if (missingFieldWarnings.has(id)) {
            return;
        }

        missingFieldWarnings.add(id);

        if (typeof console !== 'undefined' && typeof console.warn === 'function') {
            console.warn(`[Mitgliedschaftsformular] Feld mit ID "${id}" wurde nicht gefunden. Bitte überprüfe die Formularstruktur.`);
        }
    }

    function setFieldError(input, message) {
        const fieldName = input.getAttribute('name') || input.id;
        const errorElem = getErrorElement(fieldName);

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

        for (const [id, rules] of Object.entries(fields)) {
            const input = form.querySelector(`[name="${id}"]`);

            if (!input) {
                warnMissingField(id);
                continue;
            }

            if (rules.optional && !input.value.trim()) {
                setFieldError(input, '');
                continue;
            }

            if (rules.matchWith) {
                const matchElem = form.querySelector(`[name="${rules.matchWith}"]`);
                const hasError = input.value !== matchElem.value;
                isValid = setFieldError(input, hasError ? rules.error : '') && isValid;
            } else {
                let testValue = input.value.trim();
                if (rules.normalize) {
                    testValue = rules.normalize(testValue);
                }
                const hasError = !rules.regex.test(testValue);
                isValid = setFieldError(input, hasError ? rules.error : '') && isValid;
            }
        }

        toggleSubmit();
        return isValid;
    }

    async function handleSubmit(e) {
        e.preventDefault();

        if (!validateForm() || !satzungCheck.checked) {
            return;
        }

        const formData = new FormData(form);
        const messages = document.getElementById('form-messages');
        const currentSubmitButton = document.getElementById('submit-button');
        const loadingIndicator = document.getElementById('loading-indicator');

        // Button deaktivieren und Lade-Indikator zeigen
        currentSubmitButton.disabled = true;
        currentSubmitButton.classList.add('hidden');
        loadingIndicator.classList.remove('hidden');

        try {
            const response = await fetch('/mitglied-werden', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json'
                },
                body: formData,
                credentials: 'same-origin'
            });

            let result = null;
            try {
                result = await response.json();
            } catch (jsonError) {
                console.error('JSON parsing error:', jsonError);
            }

            if ((response.ok && result && result.success) || (result && result.success)) {
                window.location.href = '/mitglied-werden/erfolgreich';
            } else if (response.status === 422 && result && result.errors) {
                // Serverseitige Validierungsfehler den jeweiligen Feldern zuordnen
                for (const [field, msgs] of Object.entries(result.errors)) {
                    const input = form.querySelector(`[name="${field}"]`);
                    const message = msgs.join(' ');
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

                // Erstes fehlerhaftes Feld fokussieren und Browser-Validierungshinweis anzeigen
                const firstInvalid = form.querySelector('[aria-invalid="true"]');
                if (firstInvalid) {
                    firstInvalid.focus();
                    firstInvalid.reportValidity();
                }

                messages.textContent = 'Bitte korrigiere die markierten Felder.';
                messages.className = 'mb-4 p-4 bg-red-100 border border-red-400 text-red-800 rounded';
                messages.classList.remove('hidden');
                messages.scrollIntoView({ behavior: 'smooth' });

                // Button und Indikator zurücksetzen bei Fehler
                currentSubmitButton.disabled = false;
                currentSubmitButton.classList.remove('hidden');
                loadingIndicator.classList.add('hidden');
            } else {
                messages.textContent = 'Unbekannter Fehler aufgetreten.';
                messages.className = 'mb-4 p-4 bg-red-100 border border-red-400 text-red-800 rounded';
                messages.classList.remove('hidden');
                messages.scrollIntoView({ behavior: 'smooth' });

                // Button und Indikator zurücksetzen bei Fehler
                currentSubmitButton.disabled = false;
                currentSubmitButton.classList.remove('hidden');
                loadingIndicator.classList.add('hidden');
            }
        } catch (error) {
            messages.className = 'mb-4 p-4 bg-red-100 border border-red-400 text-red-800 rounded';
            messages.textContent = 'Ein unerwarteter Fehler ist aufgetreten. Bitte versuche es später erneut.';
            messages.classList.remove('hidden');
            messages.scrollIntoView({ behavior: 'smooth' });
            console.error('Fehler:', error);

            // Button und Indikator zurücksetzen bei Fehler
            currentSubmitButton.disabled = false;
            currentSubmitButton.classList.remove('hidden');
            loadingIndicator.classList.add('hidden');
        }
    }

    // Initialer Check beim Laden der Seite
    toggleSubmit();
}

document.addEventListener('DOMContentLoaded', initMitgliedWerden);
document.addEventListener('livewire:navigated', initMitgliedWerden);
