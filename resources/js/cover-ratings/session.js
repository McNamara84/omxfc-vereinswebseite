const registeredAlpineInstances = new WeakSet();

const eventDetail = (event) => {
    const detail = event?.detail;

    if (Array.isArray(detail)) {
        return detail[0] ?? {};
    }

    return detail && typeof detail === 'object' ? detail : {};
};

export function createCoverRatingSession({
    documentRef = globalThis.document,
    windowRef = globalThis.window,
    setTimer = globalThis.setTimeout,
    clearTimer = globalThis.clearTimeout,
} = {}) {
    return {
        active: false,
        nativeFullscreen: false,
        feedbackVisible: false,
        ratingPreview: 0,
        fullscreenRequestPending: false,
        returnFocusElement: null,
        scrollX: 0,
        scrollY: 0,
        feedbackTimer: null,
        initialized: false,
        fullscreenPromise: Promise.resolve(false),
        fullscreenRequestGeneration: 0,
        fullscreenExitPromise: null,

        init() {
            if (this.initialized) {
                return;
            }

            this.initialized = true;
            this.onFullscreenChange = () => this.handleFullscreenChange();
            this.onKeydown = (event) => this.handleKeydown(event);
            this.onRatingAdvanced = (event) => this.handleRatingAdvanced(event);
            this.onRatingFeedback = (event) => this.handleRatingFeedback(event);
            this.onLivewireNavigating = () => this.stop({ exitNative: true, restoreFocus: false });

            documentRef.addEventListener('fullscreenchange', this.onFullscreenChange);
            documentRef.addEventListener('keydown', this.onKeydown);
            documentRef.addEventListener('livewire:navigating', this.onLivewireNavigating);
            windowRef.addEventListener('cover-rating-advanced', this.onRatingAdvanced);
            windowRef.addEventListener('cover-rating-feedback', this.onRatingFeedback);
        },

        destroy() {
            if (!this.initialized) {
                return;
            }

            this.stop({ exitNative: true, restoreFocus: false });
            documentRef.removeEventListener('fullscreenchange', this.onFullscreenChange);
            documentRef.removeEventListener('keydown', this.onKeydown);
            documentRef.removeEventListener('livewire:navigating', this.onLivewireNavigating);
            windowRef.removeEventListener('cover-rating-advanced', this.onRatingAdvanced);
            windowRef.removeEventListener('cover-rating-feedback', this.onRatingFeedback);
            this.initialized = false;
        },

        overlayElement() {
            return this.$refs?.session
                ?? documentRef.querySelector('[data-testid="cover-rating-session"]');
        },

        schedule(callback) {
            if (typeof this.$nextTick === 'function') {
                this.$nextTick(callback);
                return;
            }

            queueMicrotask(callback);
        },

        setOverlayActive(isActive) {
            const overlay = this.overlayElement();

            if (!overlay) {
                return;
            }

            overlay.classList.toggle('cover-rating-session--active', isActive);
            overlay.setAttribute('aria-hidden', isActive ? 'false' : 'true');
            overlay.inert = !isActive;
        },

        start(event) {
            const overlay = this.overlayElement();

            if (!overlay || this.active || this.fullscreenRequestPending || this.fullscreenExitPromise) {
                return this.fullscreenExitPromise ?? this.fullscreenPromise;
            }

            this.returnFocusElement = event?.currentTarget ?? documentRef.activeElement;
            this.scrollX = Number(windowRef.scrollX ?? 0);
            this.scrollY = Number(windowRef.scrollY ?? 0);
            this.active = true;
            this.feedbackVisible = false;
            this.ratingPreview = 0;
            this.setOverlayActive(true);
            documentRef.body?.classList.add('cover-rating-session-open');
            this.schedule(() => overlay.querySelector('[data-cover-focus]')?.focus());

            if (typeof overlay.requestFullscreen !== 'function') {
                this.fullscreenRequestPending = false;
                this.nativeFullscreen = false;
                this.fullscreenPromise = Promise.resolve(false);
                return this.fullscreenPromise;
            }

            this.fullscreenRequestPending = true;
            const requestGeneration = ++this.fullscreenRequestGeneration;

            try {
                this.fullscreenPromise = Promise.resolve(overlay.requestFullscreen())
                    .then(() => {
                        if (requestGeneration !== this.fullscreenRequestGeneration || !this.active) {
                            return this.exitOwnedFullscreen(overlay).then(() => false);
                        }

                        this.nativeFullscreen = documentRef.fullscreenElement === overlay;

                        return this.nativeFullscreen;
                    })
                    .catch(() => {
                        if (requestGeneration === this.fullscreenRequestGeneration) {
                            this.nativeFullscreen = false;
                        }

                        return false;
                    })
                    .finally(() => {
                        this.fullscreenRequestPending = false;
                    });
            } catch {
                this.fullscreenRequestPending = false;
                this.nativeFullscreen = false;
                this.fullscreenPromise = Promise.resolve(false);
            }

            return this.fullscreenPromise;
        },

        stop({ exitNative = true, restoreFocus = true } = {}) {
            const overlay = this.overlayElement();
            const focusTarget = this.returnFocusElement;
            const shouldExitNative = exitNative
                && overlay
                && documentRef.fullscreenElement === overlay
                && typeof documentRef.exitFullscreen === 'function';

            this.fullscreenRequestGeneration += 1;
            this.active = false;
            this.nativeFullscreen = false;
            this.feedbackVisible = false;
            this.ratingPreview = 0;
            this.clearFeedbackTimer();
            this.setOverlayActive(false);
            documentRef.body?.classList.remove('cover-rating-session-open');
            let completion = Promise.resolve();

            if (shouldExitNative) {
                completion = this.exitOwnedFullscreen(overlay);
            }

            this.restoreScroll();

            if (restoreFocus) {
                completion = completion.then(() => this.restoreFocus(focusTarget));
            }

            return completion;
        },

        restoreFocus(focusTarget) {
            return new Promise((resolve) => {
                this.schedule(() => {
                    setTimer(() => {
                        this.resolveReturnFocus(focusTarget)?.focus();
                        resolve();
                    }, 0);
                });
            });
        },

        resolveReturnFocus(preferredTarget) {
            const fallbackTarget = documentRef.querySelector('[data-cover-return-focus]');

            return [preferredTarget, fallbackTarget].find((target) => (
                target?.isConnected
                && typeof target.focus === 'function'
                && !target.matches?.(':disabled, [aria-disabled="true"]')
                && !target.closest?.('[inert]')
            )) ?? null;
        },

        exitOwnedFullscreen(overlay = this.overlayElement()) {
            if (
                !overlay
                || documentRef.fullscreenElement !== overlay
                || typeof documentRef.exitFullscreen !== 'function'
            ) {
                return Promise.resolve();
            }

            if (this.fullscreenExitPromise) {
                return this.fullscreenExitPromise;
            }

            try {
                this.fullscreenExitPromise = Promise.resolve(documentRef.exitFullscreen())
                    .catch(() => {})
                    .finally(() => {
                        this.fullscreenExitPromise = null;
                    });
            } catch {
                // The CSS overlay has already been closed; browser cleanup is best-effort.
                return Promise.resolve();
            }

            return this.fullscreenExitPromise;
        },

        restoreScroll() {
            if (typeof windowRef.scrollTo !== 'function') {
                return;
            }

            try {
                windowRef.scrollTo(this.scrollX, this.scrollY);
            } catch {
                // Some test/browser environments expose a non-callable scroll implementation.
            }
        },

        handleFullscreenChange() {
            const overlay = this.overlayElement();

            if (overlay && documentRef.fullscreenElement === overlay) {
                if (!this.active) {
                    this.nativeFullscreen = false;
                    this.exitOwnedFullscreen(overlay);
                    return;
                }

                this.nativeFullscreen = true;
                return;
            }

            const nativeFullscreenWasActive = this.nativeFullscreen;
            this.nativeFullscreen = false;

            if (this.active && nativeFullscreenWasActive && !this.fullscreenRequestPending) {
                this.stop({ exitNative: false, restoreFocus: true });
            }
        },

        handleKeydown(event) {
            if (event.key !== 'Escape' || !this.active) {
                return;
            }

            const overlay = this.overlayElement();

            if (overlay && documentRef.fullscreenElement === overlay) {
                return;
            }

            event.preventDefault();
            this.stop({ exitNative: false, restoreFocus: true });
        },

        handleRatingAdvanced(event) {
            if (!this.active) {
                return;
            }

            const detail = eventDetail(event);
            this.ratingPreview = 0;
            this.schedule(() => {
                const overlay = this.overlayElement();
                const focusSelector = detail.hasCover === false
                    ? '[data-cover-empty-focus]'
                    : '[data-cover-focus]';

                this.ratingPreview = 0;
                overlay?.querySelector(focusSelector)?.focus();
                this.showFeedback(detail);
            });
        },

        handleRatingFeedback(event) {
            if (!this.active) {
                return;
            }

            const detail = eventDetail(event);
            this.schedule(() => {
                const overlay = this.overlayElement();

                overlay
                    ?.querySelector('[data-cover-focus], [data-cover-empty-focus]')
                    ?.focus();
                this.showFeedback(detail);
            });
        },

        showFeedback(detail = {}) {
            const feedback = this.overlayElement()?.querySelector('[data-rating-feedback]');

            if (!feedback) {
                this.feedbackVisible = false;
                this.clearFeedbackTimer();
                return;
            }

            this.feedbackVisible = true;
            this.clearFeedbackTimer();
            const duration = Number(detail.awardedBaxx ?? 0) > 0 ? 7000 : 4500;
            this.feedbackTimer = setTimer(() => {
                this.feedbackVisible = false;
                this.feedbackTimer = null;
            }, duration);
        },

        clearFeedbackTimer() {
            if (this.feedbackTimer === null) {
                return;
            }

            clearTimer(this.feedbackTimer);
            this.feedbackTimer = null;
        },
    };
}

export function registerCoverRatingSession(alpine = globalThis.window?.Alpine) {
    if (!alpine || typeof alpine.data !== 'function' || registeredAlpineInstances.has(alpine)) {
        return false;
    }

    alpine.data('coverRatingSession', () => createCoverRatingSession());
    registeredAlpineInstances.add(alpine);

    return true;
}

if (globalThis.window?.Alpine) {
    registerCoverRatingSession(globalThis.window.Alpine);
} else if (globalThis.document) {
    globalThis.document.addEventListener(
        'alpine:init',
        () => registerCoverRatingSession(globalThis.window?.Alpine),
        { once: true },
    );
}
