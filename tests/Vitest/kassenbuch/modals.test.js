import { beforeEach, describe, expect, it, vi } from 'vitest';
import {
    emitEditModalEvent,
    emitKassenbuchModalEvent,
    openEditModal,
    openKassenbuchModal,
} from '@/js/kassenbuch/modals';

describe('kassenbuch modal helpers', () => {
    beforeEach(() => {
        vi.restoreAllMocks();
    });

    it('emits edit modal events with sanitized payloads', () => {
        const listener = vi.fn();
        window.addEventListener('edit-payment-modal', listener);

        const detail = emitEditModalEvent('42', 'Max Mustermann', undefined, null, '2024-01-01');

        expect(detail).toEqual({
            user_id: '42',
            user_name: 'Max Mustermann',
            mitgliedsbeitrag: '',
            bezahlt_bis: '',
            mitglied_seit: '2024-01-01',
        });

        expect(listener).toHaveBeenCalledTimes(1);
        expect(listener.mock.calls[0][0].detail).toEqual(detail);

        window.removeEventListener('edit-payment-modal', listener);
    });

    it('aliases openEditModal to emitEditModalEvent for global usage', () => {
        expect(window.openEditModal).toBe(openEditModal);

        const spy = vi.fn();
        window.addEventListener('edit-payment-modal', spy);

        const payload = openEditModal('7', 'Erika Beispiel', '12.50', '2025-02-01', '2020-05-01');

        expect(payload).toEqual({
            user_id: '7',
            user_name: 'Erika Beispiel',
            mitgliedsbeitrag: '12.50',
            bezahlt_bis: '2025-02-01',
            mitglied_seit: '2020-05-01',
        });
        expect(spy).toHaveBeenCalledTimes(1);

        window.removeEventListener('edit-payment-modal', spy);
    });

    it('emits the kassenbuch modal event exactly once', () => {
        expect(window.openKassenbuchModal).toBe(openKassenbuchModal);

        const listener = vi.fn();
        window.addEventListener('kassenbuch-modal', listener);

        const eventType = emitKassenbuchModalEvent();
        expect(eventType).toBe('kassenbuch-modal');
        expect(listener).toHaveBeenCalledTimes(1);

        listener.mockClear();
        openKassenbuchModal();
        expect(listener).toHaveBeenCalledTimes(1);

        window.removeEventListener('kassenbuch-modal', listener);
    });

    it('creates isolated payload objects for successive edit modal emissions', () => {
        const first = emitEditModalEvent('11', 'Alpha Tester', '20', '2025-01-01', '2020-06-01');
        const second = emitEditModalEvent('12', 'Beta Nutzerin', '30', '2025-02-01', '2021-01-01');

        expect(first).not.toBe(second);
        expect(first).toEqual({
            user_id: '11',
            user_name: 'Alpha Tester',
            mitgliedsbeitrag: '20',
            bezahlt_bis: '2025-01-01',
            mitglied_seit: '2020-06-01',
        });
        expect(second).toEqual({
            user_id: '12',
            user_name: 'Beta Nutzerin',
            mitgliedsbeitrag: '30',
            bezahlt_bis: '2025-02-01',
            mitglied_seit: '2021-01-01',
        });
    });
});
