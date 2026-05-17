import {
    detectTourDevice,
    filterReachableSteps,
    isElementVisible,
    resolveCurrentStepIndex,
    revealSelectorsForStep,
    selectorForStep,
} from './helpers';

const TOAST_CLASSES = {
    success: 'alert-success',
    info: 'alert-info',
};

const TOUR_DEBUG_STORAGE_KEY = 'omxfc-tour-debug';
const TOUR_DEBUG_QUERY_PARAM = 'tour_debug';

const state = {
    root: null,
    payload: null,
    steps: [],
    stepIndex: 0,
    target: null,
    lastSyncedStepKey: null,
    skippedStepKeys: new Set(),
    boundRoot: null,
    handleRootClick: null,
};

const elementIds = {
    backdrop: 'tour-runner-backdrop',
    highlight: 'tour-runner-highlight',
    panel: 'tour-runner-panel',
    title: 'tour-runner-title',
    description: 'tour-runner-description',
    counter: 'tour-runner-counter',
    progressLabel: 'tour-runner-progress-label',
    progressBar: 'tour-runner-progress-bar',
    back: 'tour-runner-back',
    skip: 'tour-runner-skip',
    next: 'tour-runner-next',
    complete: 'tour-runner-complete',
};

function isTourDebugEnabled() {
    if (typeof window === 'undefined') {
        return false;
    }

    if (window.__OMXFC_TOUR_DEBUG__ === true) {
        return true;
    }

    try {
        const params = new URLSearchParams(window.location.search);

        if (params.get(TOUR_DEBUG_QUERY_PARAM) === '1') {
            return true;
        }
    } catch {
        // Ignore malformed URLs while probing debug state.
    }

    try {
        return window.localStorage?.getItem(TOUR_DEBUG_STORAGE_KEY) === '1';
    } catch {
        return false;
    }
}

function tourDebugSnapshot(extra = {}) {
    return {
        assignmentId: state.payload?.assignment_id ?? null,
        status: state.payload?.status ?? null,
        currentStepKey: state.payload?.current_step_key ?? null,
        stepIndex: state.stepIndex,
        activeSteps: state.steps.map((step) => step?.key).filter(Boolean),
        ...extra,
    };
}

function serializeTourError(error) {
    if (error instanceof Error) {
        return {
            name: error.name,
            message: error.message,
            stack: error.stack,
        };
    }

    if (typeof error === 'object' && error !== null) {
        const response = error.response && typeof error.response === 'object'
            ? {
                status: error.response.status ?? null,
                statusText: error.response.statusText ?? null,
                data: error.response.data ?? null,
            }
            : null;

        const config = error.config && typeof error.config === 'object'
            ? {
                method: error.config.method ?? null,
                url: error.config.url ?? null,
            }
            : null;

        return {
            ...error,
            response,
            config,
        };
    }

    return {
        message: String(error),
    };
}

function logTourDebug(event, extra = {}) {
    if (!isTourDebugEnabled()) {
        return;
    }

    console.debug('[tour-runner]', event, tourDebugSnapshot(extra));
}

function logTourError(event, error, extra = {}) {
    if (!isTourDebugEnabled()) {
        return;
    }

    console.error('[tour-runner]', event, {
        ...tourDebugSnapshot(extra),
        error: serializeTourError(error),
    });
}

function runTourTask(label, task) {
    void Promise.resolve()
        .then(task)
        .catch((error) => logTourError(label, error));
}

function getElement(id) {
    return document.getElementById(id);
}

function urlFor(template, assignmentId) {
    return template.replace('__TOUR_ASSIGNMENT__', String(assignmentId));
}

function currentUrlTemplates() {
    if (!state.root) {
        return null;
    }

    return {
        current: state.root.dataset.tourCurrentUrl,
        start: state.root.dataset.tourStartUrlTemplate,
        progress: state.root.dataset.tourProgressUrlTemplate,
        dismiss: state.root.dataset.tourDismissUrlTemplate,
        complete: state.root.dataset.tourCompleteUrlTemplate,
    };
}

function toast(type, title, description = '') {
    if (typeof window.toast !== 'function') {
        return;
    }

    window.toast({
        toast: {
            title,
            description,
            css: TOAST_CLASSES[type] ?? TOAST_CLASSES.info,
            timeout: 3000,
            position: 'toast-top toast-end',
            noProgress: false,
        },
    });
}

function waitForFrame() {
    return new Promise((resolve) => window.requestAnimationFrame(() => resolve()));
}

async function revealStep(step) {
    for (const selector of revealSelectorsForStep(step, detectTourDevice())) {
        const toggle = document.querySelector(selector);

        if (!(toggle instanceof HTMLElement)) {
            continue;
        }

        if (toggle.dataset.tourOpen === 'true' || toggle.getAttribute('aria-expanded') === 'true') {
            continue;
        }

        toggle.click();
        await waitForFrame();
        await waitForFrame();
    }
}

function showRunnerChrome() {
    getElement(elementIds.backdrop)?.classList.remove('hidden');
    getElement(elementIds.panel)?.classList.remove('hidden');
}

function hideRunner(resetPayload = false) {
    getElement(elementIds.backdrop)?.classList.add('hidden');
    getElement(elementIds.panel)?.classList.add('hidden');
    getElement(elementIds.highlight)?.classList.add('hidden');
    state.target = null;

    if (resetPayload) {
        state.payload = null;
        state.steps = [];
        state.stepIndex = 0;
        state.lastSyncedStepKey = null;
        state.skippedStepKeys.clear();
    }
}

function activeSteps(device) {
    if (!state.payload) {
        return [];
    }

    const steps = state.payload.steps.filter((step) => !state.skippedStepKeys.has(step?.key));

    return filterReachableSteps(steps, device, document);
}

function skipHiddenStep(stepKey) {
    state.skippedStepKeys.add(stepKey);
    state.steps = state.steps.filter((step) => step?.key !== stepKey);

    if (!state.payload) {
        return state.steps;
    }

    state.payload.steps = state.payload.steps.filter((step) => step?.key !== stepKey);

    return state.steps;
}

function updateHighlight(target) {
    const highlight = getElement(elementIds.highlight);

    if (!(highlight instanceof HTMLElement) || !(target instanceof HTMLElement)) {
        return;
    }

    const rect = target.getBoundingClientRect();
    const padding = 10;

    highlight.style.top = `${Math.max(rect.top - padding, 8)}px`;
    highlight.style.left = `${Math.max(rect.left - padding, 8)}px`;
    highlight.style.width = `${rect.width + padding * 2}px`;
    highlight.style.height = `${rect.height + padding * 2}px`;
    highlight.classList.remove('hidden');
}

function refreshHighlight() {
    if (!(state.target instanceof HTMLElement)) {
        return;
    }

    updateHighlight(state.target);
}

function updatePanel(step) {
    const title = getElement(elementIds.title);
    const description = getElement(elementIds.description);
    const counter = getElement(elementIds.counter);
    const progressLabel = getElement(elementIds.progressLabel);
    const progressBar = getElement(elementIds.progressBar);
    const backButton = getElement(elementIds.back);
    const nextButton = getElement(elementIds.next);
    const completeButton = getElement(elementIds.complete);

    if (title) {
        title.textContent = step.title ?? '';
    }

    if (description) {
        description.textContent = step.description ?? '';
    }

    if (counter) {
        counter.textContent = `${state.stepIndex + 1} / ${state.steps.length}`;
    }

    if (progressLabel) {
        progressLabel.textContent = `${state.stepIndex + 1} von ${state.steps.length}`;
    }

    if (progressBar instanceof HTMLElement) {
        progressBar.style.width = `${((state.stepIndex + 1) / state.steps.length) * 100}%`;
    }

    if (backButton instanceof HTMLElement) {
        backButton.toggleAttribute('disabled', state.stepIndex === 0);
    }

    const isLastStep = state.stepIndex >= state.steps.length - 1;

    if (nextButton instanceof HTMLElement) {
        nextButton.classList.toggle('hidden', isLastStep);
    }

    if (completeButton instanceof HTMLElement) {
        completeButton.classList.toggle('hidden', !isLastStep);
    }
}

async function syncProgress(stepKey) {
    if (!state.payload || state.lastSyncedStepKey === stepKey) {
        logTourDebug('progress:skipped', {
            stepKey,
            reason: !state.payload ? 'missing-payload' : 'already-synced',
        });

        return;
    }

    const templates = currentUrlTemplates();

    if (!templates) {
        logTourDebug('progress:skipped', {
            stepKey,
            reason: 'missing-templates',
        });

        return;
    }

    const progressUrl = urlFor(templates.progress, state.payload.assignment_id);

    logTourDebug('progress:request', {
        stepKey,
        url: progressUrl,
    });

    let response;

    try {
        response = await window.axios.post(progressUrl, {
            step_key: stepKey,
        });
    } catch (error) {
        logTourError('progress:error', error, {
            stepKey,
            url: progressUrl,
        });

        throw error;
    }

    state.payload = response.data.tour;
    state.lastSyncedStepKey = stepKey;

    logTourDebug('progress:success', {
        stepKey,
        returnedStepKey: state.payload?.current_step_key ?? null,
    });
}

async function showCurrentStep() {
    if (!state.payload) {
        logTourDebug('show-step:reset', {
            reason: 'missing-payload',
        });

        hideRunner(true);
        return;
    }

    const device = detectTourDevice();
    state.steps = activeSteps(device);

    logTourDebug('show-step:resolved-steps', {
        device,
        reachableStepCount: state.steps.length,
    });

    if (state.steps.length === 0) {
        logTourDebug('show-step:reset', {
            reason: 'no-reachable-steps',
            device,
        });

        hideRunner(false);
        return;
    }

    state.stepIndex = Math.min(resolveCurrentStepIndex(state.steps, state.payload.current_step_key), state.steps.length - 1);
    const step = state.steps[state.stepIndex];

    if (!step) {
        logTourDebug('show-step:reset', {
            reason: 'missing-step',
            device,
        });

        hideRunner(false);
        return;
    }

    logTourDebug('show-step:start', {
        device,
        stepKey: step.key,
    });

    await revealStep(step);

    const target = document.querySelector(selectorForStep(step, device));

    if (!(target instanceof HTMLElement) || !isElementVisible(target)) {
        logTourDebug('show-step:hidden-target', {
            device,
            stepKey: step.key,
            selector: selectorForStep(step, device),
            targetFound: target instanceof HTMLElement,
        });

        const fallbackIndex = state.stepIndex;
        state.steps = skipHiddenStep(step.key);

        if (state.steps.length === 0) {
            hideRunner(false);
            return;
        }

        state.stepIndex = Math.min(fallbackIndex, state.steps.length - 1);
        const fallbackStep = state.steps[state.stepIndex];

        if (fallbackStep && state.payload) {
            state.payload.current_step_key = fallbackStep.key;
            state.lastSyncedStepKey = null;
        }

        await showCurrentStep();
        return;
    }

    target.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'nearest' });
    state.target = target;
    showRunnerChrome();
    updateHighlight(target);
    updatePanel(step);
    logTourDebug('show-step:ready', {
        device,
        stepKey: step.key,
    });
    await syncProgress(step.key);
}

async function activateTour(payload) {
    state.payload = payload;
    state.lastSyncedStepKey = payload.current_step_key ?? null;
    state.skippedStepKeys.clear();

    logTourDebug('tour:activate', {
        assignmentId: payload.assignment_id,
        status: payload.status,
        currentStepKey: payload.current_step_key ?? null,
        totalSteps: Array.isArray(payload.steps) ? payload.steps.length : 0,
    });

    const templates = currentUrlTemplates();

    if (!templates) {
        logTourDebug('tour:activate-skipped', {
            reason: 'missing-templates',
        });

        return;
    }

    if (payload.status === 'pending') {
        const startUrl = urlFor(templates.start, payload.assignment_id);

        logTourDebug('tour:start-request', {
            url: startUrl,
        });

        let response;

        try {
            response = await window.axios.post(startUrl);
        } catch (error) {
            logTourError('tour:start-error', error, {
                url: startUrl,
            });

            throw error;
        }

        state.payload = response.data.tour;
        state.lastSyncedStepKey = state.payload.current_step_key ?? null;

        logTourDebug('tour:start-success', {
            currentStepKey: state.payload.current_step_key ?? null,
        });
    }

    await showCurrentStep();
}

async function fetchCurrentTour() {
    const templates = currentUrlTemplates();

    if (!templates?.current || typeof window.axios === 'undefined') {
        logTourDebug('tour:fetch-skipped', {
            reason: !templates?.current ? 'missing-current-url' : 'missing-axios',
        });

        return;
    }

    logTourDebug('tour:fetch-request', {
        url: templates.current,
    });

    let response;

    try {
        response = await window.axios.get(templates.current);
    } catch (error) {
        logTourError('tour:fetch-error', error, {
            url: templates.current,
        });

        throw error;
    }

    if (!response.data?.tour) {
        logTourDebug('tour:fetch-empty');
        hideRunner(true);
        return;
    }

    await activateTour(response.data.tour);
}

async function skipTour() {
    if (!state.payload) {
        logTourDebug('skip:ignored', {
            reason: 'missing-payload',
        });

        return;
    }

    const templates = currentUrlTemplates();

    if (!templates) {
        logTourDebug('skip:ignored', {
            reason: 'missing-templates',
        });

        return;
    }

    const dismissUrl = urlFor(templates.dismiss, state.payload.assignment_id);

    logTourDebug('skip:request', {
        url: dismissUrl,
    });

    try {
        await window.axios.post(dismissUrl);
    } catch (error) {
        logTourError('skip:error', error, {
            url: dismissUrl,
        });

        throw error;
    }

    hideRunner(true);
    logTourDebug('skip:success');
    toast('info', 'Tour pausiert', 'Wir zeigen dir die Tour nach dem nächsten Login erneut an.');
}

async function completeTour() {
    if (!state.payload) {
        logTourDebug('complete:ignored', {
            reason: 'missing-payload',
        });

        return;
    }

    const templates = currentUrlTemplates();

    if (!templates) {
        logTourDebug('complete:ignored', {
            reason: 'missing-templates',
        });

        return;
    }

    const completeUrl = urlFor(templates.complete, state.payload.assignment_id);

    logTourDebug('complete:request', {
        url: completeUrl,
    });

    try {
        await window.axios.post(completeUrl);
    } catch (error) {
        logTourError('complete:error', error, {
            url: completeUrl,
        });

        throw error;
    }

    hideRunner(true);
    logTourDebug('complete:success');
    toast('success', 'Tour abgeschlossen', 'Du kannst die Tour später im Profil erneut starten.');
}

async function handleRunnerAction(actionId) {
    if (!state.payload) {
        logTourDebug('action:ignored', {
            actionId,
            reason: 'missing-payload',
        });

        return;
    }

    logTourDebug('action:start', {
        actionId,
    });

    if (actionId === elementIds.back) {
        state.stepIndex = Math.max(state.stepIndex - 1, 0);
        const nextStep = state.steps[state.stepIndex];

        if (nextStep) {
            state.payload.current_step_key = nextStep.key;
            state.lastSyncedStepKey = null;
        }

        await showCurrentStep();

        return;
    }

    if (actionId === elementIds.skip) {
        await skipTour();

        return;
    }

    if (actionId === elementIds.next) {
        if (state.stepIndex >= state.steps.length - 1) {
            await completeTour();

            return;
        }

        state.stepIndex += 1;
        const nextStep = state.steps[state.stepIndex];

        if (nextStep) {
            state.payload.current_step_key = nextStep.key;
            state.lastSyncedStepKey = null;
        }

        await showCurrentStep();

        return;
    }

    if (actionId === elementIds.complete) {
        await completeTour();
    }

    logTourDebug('action:done', {
        actionId,
    });
}

function bindButtons() {
    if (!(state.root instanceof HTMLElement)) {
        if (state.boundRoot instanceof HTMLElement && typeof state.handleRootClick === 'function') {
            state.boundRoot.removeEventListener('click', state.handleRootClick);
        }

        state.boundRoot = null;

        return;
    }

    if (!state.handleRootClick) {
        state.handleRootClick = (event) => {
            if (!(event.target instanceof Element)) {
                return;
            }

            const actionButton = event.target.closest([
                `#${elementIds.back}`,
                `#${elementIds.skip}`,
                `#${elementIds.next}`,
                `#${elementIds.complete}`,
            ].join(', '));

            if (!(actionButton instanceof HTMLElement)) {
                return;
            }

            if (actionButton.hasAttribute('disabled')) {
                logTourDebug('action:ignored', {
                    actionId: actionButton.id,
                    reason: 'disabled-button',
                });

                return;
            }

            logTourDebug('action:clicked', {
                actionId: actionButton.id,
                targetTag: event.target.tagName,
            });

            runTourTask(`action:${actionButton.id}`, () => handleRunnerAction(actionButton.id));
        };
    }

    if (state.boundRoot === state.root) {
        return;
    }

    if (state.boundRoot instanceof HTMLElement && typeof state.handleRootClick === 'function') {
        state.boundRoot.removeEventListener('click', state.handleRootClick);
    }

    state.root.addEventListener('click', state.handleRootClick);
    state.boundRoot = state.root;
}

function hydrateRoot() {
    state.root = document.getElementById('tour-runner-root');

    if (state.root instanceof HTMLElement) {
        logTourDebug('root:hydrated');
    }

    bindButtons();
}

async function initTourRunner() {
    hydrateRoot();

    if (!state.root) {
        logTourDebug('init:skipped', {
            reason: 'missing-root',
        });

        return;
    }

    logTourDebug('init:start');

    if (state.payload) {
        await showCurrentStep();
        return;
    }

    await fetchCurrentTour();
}

window.addEventListener('resize', () => {
    if (state.payload) {
        runTourTask('window:resize', () => showCurrentStep());
    }
});

window.addEventListener('scroll', refreshHighlight, true);
document.addEventListener('livewire:navigated', () => runTourTask('livewire:navigated', () => initTourRunner()));

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => runTourTask('dom:content-loaded', () => initTourRunner()), { once: true });
} else {
    runTourTask('boot', () => initTourRunner());
}

export { initTourRunner };