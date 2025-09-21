export function initTodoDashboard(root = document) {
    const context = root || document;
    const progressBars = context.querySelectorAll('[data-progress-bar]');

    progressBars.forEach((bar) => {
        const valueAttribute = Number(bar.getAttribute('data-progress-value'));
        const maxAttribute = Number(bar.getAttribute('data-progress-max'));
        const label = bar.getAttribute('data-progress-label');

        const sanitizedValue = Number.isFinite(valueAttribute) ? valueAttribute : 0;
        const normalizedValue = Math.max(0, sanitizedValue);

        let normalizedMax;
        if (Number.isFinite(maxAttribute) && maxAttribute > 0) {
            normalizedMax = maxAttribute;
        } else {
            const fallbackMax = Math.max(normalizedValue, 1);
            normalizedMax = fallbackMax;
        }

        const clampedValue = Math.min(normalizedValue, normalizedMax);

        bar.setAttribute('role', 'progressbar');
        bar.setAttribute('aria-valuemin', '0');
        bar.setAttribute('aria-valuemax', normalizedMax.toString());
        bar.setAttribute('aria-valuenow', clampedValue.toString());

        if (label) {
            bar.setAttribute('aria-label', label);
        }

        const fill = bar.querySelector('[data-progress-fill]');
        if (fill) {
            const percent = normalizedMax === 0 ? 0 : Math.round((clampedValue / normalizedMax) * 100);
            fill.style.width = `${percent}%`;
            fill.style.setProperty('--progress-percent', String(percent));
        }
    });
}

export default initTodoDashboard;
