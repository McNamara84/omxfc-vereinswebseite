const sanitize = (value) => (value ?? '');

const buildEditModalDetail = (userId, userName, mitgliedsbeitrag, bezahltBis, mitgliedSeit) => ({
    user_id: sanitize(userId),
    user_name: sanitize(userName),
    mitgliedsbeitrag: sanitize(mitgliedsbeitrag),
    bezahlt_bis: sanitize(bezahltBis),
    mitglied_seit: sanitize(mitgliedSeit),
});

export const emitEditModalEvent = (userId, userName, mitgliedsbeitrag, bezahltBis, mitgliedSeit) => {
    const detail = buildEditModalDetail(userId, userName, mitgliedsbeitrag, bezahltBis, mitgliedSeit);

    window.dispatchEvent(new CustomEvent('edit-payment-modal', { detail }));

    return detail;
};

export const emitKassenbuchModalEvent = () => {
    const event = new CustomEvent('kassenbuch-modal');
    window.dispatchEvent(event);
    return event.type;
};

export const openEditModal = (userId, userName, mitgliedsbeitrag, bezahltBis, mitgliedSeit) =>
    emitEditModalEvent(userId, userName, mitgliedsbeitrag, bezahltBis, mitgliedSeit);

export const openKassenbuchModal = () => emitKassenbuchModalEvent();

// Note: Click handlers are now handled by Alpine.js via @click="$dispatch(...)"
// These JavaScript functions are kept for programmatic use and testing

if (typeof window !== 'undefined') {
    window.openEditModal = openEditModal;
    window.openKassenbuchModal = openKassenbuchModal;
}

export default {
    openEditModal,
    openKassenbuchModal,
    emitEditModalEvent,
    emitKassenbuchModalEvent,
};
