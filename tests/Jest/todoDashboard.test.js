import { initTodoDashboard } from '../../resources/js/utils/dashboard';

describe('todo dashboard utilities', () => {
    beforeEach(() => {
        document.body.innerHTML = '';
    });

    test('initialises progress bars with accessible attributes', () => {
        document.body.innerHTML = `
            <div data-progress-bar data-progress-value="25" data-progress-max="50" data-progress-label="Wochenziel">
                <div data-progress-fill style="width: 0"></div>
            </div>
        `;

        initTodoDashboard(document);

        const bar = document.querySelector('[data-progress-bar]');
        const fill = document.querySelector('[data-progress-fill]');

        expect(bar?.getAttribute('role')).toBe('progressbar');
        expect(bar?.getAttribute('aria-valuemin')).toBe('0');
        expect(bar?.getAttribute('aria-valuemax')).toBe('50');
        expect(bar?.getAttribute('aria-valuenow')).toBe('25');
        expect(bar?.getAttribute('aria-label')).toBe('Wochenziel');
        expect(fill?.style.width).toBe('50%');
    });

    test('falls back to sane defaults when values are missing', () => {
        document.body.innerHTML = `
            <div data-progress-bar data-progress-value="-10">
                <div data-progress-fill></div>
            </div>
        `;

        initTodoDashboard(document);

        const bar = document.querySelector('[data-progress-bar]');
        const fill = document.querySelector('[data-progress-fill]');

        expect(bar?.getAttribute('aria-valuemax')).toBe('1');
        expect(bar?.getAttribute('aria-valuenow')).toBe('0');
        expect(fill?.style.width).toBe('0%');
    });
});
