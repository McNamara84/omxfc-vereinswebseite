export function renderProfileReward(payload) {
    const element = document.getElementById('tour-runner-reward');
    if (!element) return;
    element.classList.toggle('hidden', !payload?.profile);
    if (!payload?.profile) return;
    const { filled, total } = payload.profile;
    const points = payload.reward_points;
    element.textContent = payload.reward_received
        ? `Die einmaligen ${points} Baxx hast du bereits erhalten. ${filled} von ${total} Angaben gespeichert.`
        : `Einmalig ${points} Baxx verdienen: Alle ${total} Serienangaben speichern und diese Tour erfolgreich abschließen. ${filled} von ${total} Angaben gespeichert. Bereits ausgefüllte Angaben zählen mit.`;
}

export function renderTourError(error) {
    const element = document.getElementById('tour-runner-error');
    if (!element) return;
    element.replaceChildren();
    element.classList.remove('hidden');
    const details = error?.response?.data;
    const message = document.createElement('p');
    message.textContent = details?.errors
        ? 'Bitte prüfe die folgenden Angaben. Die Tour bleibt offen.'
        : (details?.message ?? error?.message ?? 'Die Aktion ist fehlgeschlagen. Bitte versuche es erneut.');
    element.append(message);
    for (const [field, messages] of Object.entries(details?.errors ?? {})) {
        const target = document.getElementById(field);
        const entry = document.createElement(target ? 'button' : 'p');
        entry.textContent = Array.isArray(messages) ? messages.join(' ') : String(messages);
        if (target) {
            entry.type = 'button';
            entry.dataset.tourMissingField = field;
            entry.className = 'block text-left underline';
        }
        element.append(entry);
    }
    element.focus({ preventScroll: true });
}

export function clearTourError() {
    document.getElementById('tour-runner-error')?.classList.add('hidden');
}

export function renderInvitation(payload) {
    const panel = document.getElementById('tour-runner-panel');
    panel?.classList.remove('hidden');
    if (panel) {
        panel.dataset.invitation = 'true';
        panel.dataset.profileTour = 'true';
    }
    document.getElementById('tour-runner-highlight')?.classList.add('hidden');
    document.getElementById('tour-runner-backdrop')?.classList.add('hidden');
    for (const suffix of ['back', 'next', 'complete', 'progress', 'counter']) {
        document.getElementById(`tour-runner-${suffix}`)?.classList.add('hidden');
    }
    const title = document.getElementById('tour-runner-title');
    if (title) title.textContent = 'Profil pflegen';
    const description = document.getElementById('tour-runner-description');
    if (description) description.textContent = 'Lerne dein Mitgliederprofil kennen und ergänze deine Serienangaben. Andere Mitglieder können diese Angaben in deinem Profil sehen. Du kannst die Tour jederzeit unterbrechen und später fortsetzen.';
    const button = document.getElementById('tour-runner-start');
    if (button) {
        button.classList.remove('hidden');
        button.textContent = payload.status === 'in_progress' ? 'Fortsetzen' : 'Tour starten';
    }
    renderProfileReward(payload);
}

export function profileFormIsDirty() {
    return document.querySelector('[data-profile-series-form]')?.dataset.unsaved === 'true';
}

export function requireSavedProfile() {
    if (profileFormIsDirty()) {
        throw new Error('Deine Änderungen sind noch nicht gespeichert. Klicke im Serienformular auf Speichern und versuche es anschließend erneut.');
    }
}
