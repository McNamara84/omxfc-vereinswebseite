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

const extractEditDataset = (element) => {
    const { userId, userName, mitgliedsbeitrag, bezahltBis, mitgliedSeit } = element.dataset;

    return [userId, userName, mitgliedsbeitrag, bezahltBis, mitgliedSeit];
};

const handleClick = (event) => {
    const editTrigger = event.target.closest('[data-kassenbuch-edit="true"]');
    if (editTrigger) {
        emitEditModalEvent(...extractEditDataset(editTrigger));
        return;
    }

    const modalTrigger = event.target.closest('[data-kassenbuch-modal-trigger="true"]');
    if (modalTrigger) {
        emitKassenbuchModalEvent();
    }
};

export const registerKassenbuchModals = (
    target = typeof document !== 'undefined' ? document : undefined,
) => {
    if (!target || typeof target.addEventListener !== 'function') {
        return () => {};
    }

    target.addEventListener('click', handleClick);

    return () => target.removeEventListener('click', handleClick);
};

if (typeof window !== 'undefined') {
    window.openEditModal = openEditModal;
    window.openKassenbuchModal = openKassenbuchModal;
    registerKassenbuchModals(document);
}

export default {
    openEditModal,
    openKassenbuchModal,
    emitEditModalEvent,
    emitKassenbuchModalEvent,
    registerKassenbuchModals,
};
