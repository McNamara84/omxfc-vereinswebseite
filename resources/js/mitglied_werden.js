document.addEventListener('DOMContentLoaded', () => {
    const beitrag = document.getElementById('mitgliedsbeitrag');
    const beitragOutput = document.getElementById('beitrag-output');
    const satzungCheck = document.getElementById('satzung_check');
    const submitButton = document.getElementById('submit-button');
    const form = document.getElementById('mitgliedschaft-form');

    const fields = {
        vorname: { regex: /.+/, error: "Vorname ist erforderlich." },
        nachname: { regex: /.+/, error: "Nachname ist erforderlich." },
        strasse: { regex: /.+/, error: "Straße ist erforderlich." },
        hausnummer: { regex: /.+/, error: "Hausnummer ist erforderlich." },
        plz: { regex: /^\d{4,6}$/, error: "Bitte gültige PLZ eingeben (4-6 Zahlen)." },
        stadt: { regex: /.+/, error: "Stadt ist erforderlich." },
        land: { regex: /^(Deutschland|Österreich|Schweiz)$/, error: "Bitte wähle dein Land." },
        mail: { regex: /^[^@\s]+@[^@\s]+\.[^@\s]+$/, error: "Bitte gültige Mailadresse eingeben." },
        passwort: { regex: /^.{6,}$/, error: "Passwort mindestens 6 Zeichen." },
        passwort_confirmation: { matchWith: 'passwort', error: "Passwörter stimmen nicht überein." },
        telefon: { regex: /^(\+\d{1,3}\s?)?(\d{4,14})$/, error: "Bitte gültige Handynummer eingeben.", optional: true },
        verein_gefunden: { regex: /^(Facebook|Instagram|Leserkontaktseite|Befreundete Person|Fantreffen\/MaddraxCon|Google|Sonstiges)$/, error: "Bitte wähle eine Option aus.", optional: true }
    };

    beitrag.addEventListener('input', () => {
        beitragOutput.textContent = beitrag.value + '€';
    });

    satzungCheck.addEventListener('change', toggleSubmit);
    form.addEventListener('input', validateForm);
    form.addEventListener('submit', handleSubmit);

    function toggleSubmit() {
        submitButton.disabled = !satzungCheck.checked || !form.checkValidity();
        submitButton.classList.toggle('opacity-50', submitButton.disabled);
        submitButton.classList.toggle('cursor-not-allowed', submitButton.disabled);
    }

    function validateForm() {
        let isValid = true;

        for (const [id, rules] of Object.entries(fields)) {
            const input = document.getElementById(id);
            const errorElem = document.getElementById(`error-${id}`);

            if (rules.optional && !input.value.trim()) {
                errorElem.textContent = '';
                continue;
            }

            if (rules.matchWith) {
                const matchElem = document.getElementById(rules.matchWith);
                if (input.value !== matchElem.value) {
                    errorElem.textContent = rules.error;
                    isValid = false;
                } else {
                    errorElem.textContent = '';
                }
            } else if (!rules.regex.test(input.value.trim())) {
                errorElem.textContent = rules.error;
                isValid = false;
            } else {
                errorElem.textContent = '';
            }
        }

        toggleSubmit();
        return isValid;
    }

    async function handleSubmit(e) {
        e.preventDefault();

        if (!validateForm() || !satzungCheck.checked) {
            alert('Bitte korrigiere die rot markierten Fehler im Formular.');
            return;
        }

        let formData = new FormData(form);
        let messages = document.getElementById('form-messages');

        try {
            const response = await fetch('/mitglied-werden', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json'
                },
                body: formData
            });

            if (response.ok) {
                window.location.href = '/mitglied-werden/erfolgreich';
            } else {
                let result;
                try {
                    result = await response.json();
                    messages.textContent = result.errors ? Object.values(result.errors).join(' ') : 'Unbekannter Fehler aufgetreten.';
                } catch {
                    messages.textContent = 'Es ist ein Fehler aufgetreten. Bitte versuche es später erneut.';
                }

                messages.className = 'mb-4 p-4 bg-red-100 border border-red-400 text-red-800 rounded';
                messages.classList.remove('hidden');
                messages.scrollIntoView({ behavior: 'smooth' });
            }
        } catch (error) {
            // Fehlerbehandlung, falls keine JSON-Antwort kommt
            messages.className = 'mb-4 p-4 bg-red-100 border border-red-400 text-red-800 rounded';
            messages.textContent = 'Ein unerwarteter Fehler ist aufgetreten. Bitte versuche es später erneut.';
            messages.classList.remove('hidden');
            messages.scrollIntoView({ behavior: 'smooth' });
            console.error('Fehler:', error);
        }
    }


    // Initialer Check beim Laden der Seite
    toggleSubmit();
});
