function initializeUploadToggles() {
    document.querySelectorAll('form[data-auto-submit="change"]').forEach(form => {
        const checkbox = form.querySelector('input[type="checkbox"]');
        const hidden = form.querySelector('input[type="hidden"][name="uploaded"]');

        if (!checkbox) {
            return;
        }

        if (hidden) {
            hidden.disabled = checkbox.checked;
        }

        checkbox.addEventListener('change', () => {
            if (hidden) {
                hidden.disabled = checkbox.checked;
            }

            if (typeof form.requestSubmit === 'function') {
                try {
                    form.requestSubmit();
                    return;
                } catch (error) {
                    if (!(error && error.name === 'NotImplementedError')) {
                        throw error;
                    }
                }
            }

            form.submit();
        });
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeUploadToggles);
} else {
    initializeUploadToggles();
}
