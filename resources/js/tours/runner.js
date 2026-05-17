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

const state = {
    root: null,
    payload: null,
    steps: [],
    stepIndex: 0,
    target: null,
    lastSyncedStepKey: null,
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
    }
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
        return;
    }

    const templates = currentUrlTemplates();

    if (!templates) {
        return;
    }

    const response = await window.axios.post(urlFor(templates.progress, state.payload.assignment_id), {
        step_key: stepKey,
    });

    state.payload = response.data.tour;
    state.lastSyncedStepKey = stepKey;
}

async function showCurrentStep() {
    if (!state.payload) {
        hideRunner(true);
        return;
    }

    const device = detectTourDevice();
    state.steps = filterReachableSteps(state.payload.steps, device, document);

    if (state.steps.length === 0) {
        hideRunner(false);
        return;
    }

    state.stepIndex = Math.min(resolveCurrentStepIndex(state.steps, state.payload.current_step_key), state.steps.length - 1);
    const step = state.steps[state.stepIndex];

    if (!step) {
        hideRunner(false);
        return;
    }

    await revealStep(step);

    const target = document.querySelector(selectorForStep(step, device));

    if (!(target instanceof HTMLElement) || !isElementVisible(target)) {
        state.steps = state.steps.filter((candidate) => candidate.key !== step.key);

        if (state.steps.length === 0) {
            hideRunner(false);
            return;
        }

        state.stepIndex = Math.min(state.stepIndex, state.steps.length - 1);
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
    await syncProgress(step.key);
}

async function activateTour(payload) {
    state.payload = payload;
    state.lastSyncedStepKey = payload.current_step_key ?? null;

    const templates = currentUrlTemplates();

    if (!templates) {
        return;
    }

    if (payload.status === 'pending') {
        const response = await window.axios.post(urlFor(templates.start, payload.assignment_id));
        state.payload = response.data.tour;
        state.lastSyncedStepKey = state.payload.current_step_key ?? null;
    }

    await showCurrentStep();
}

async function fetchCurrentTour() {
    const templates = currentUrlTemplates();

    if (!templates?.current || typeof window.axios === 'undefined') {
        return;
    }

    const response = await window.axios.get(templates.current);

    if (!response.data?.tour) {
        hideRunner(true);
        return;
    }

    await activateTour(response.data.tour);
}

async function skipTour() {
    if (!state.payload) {
        return;
    }

    const templates = currentUrlTemplates();

    if (!templates) {
        return;
    }

    await window.axios.post(urlFor(templates.dismiss, state.payload.assignment_id));
    hideRunner(true);
    toast('info', 'Tour pausiert', 'Wir zeigen dir die Tour nach dem nächsten Login erneut an.');
}

async function completeTour() {
    if (!state.payload) {
        return;
    }

    const templates = currentUrlTemplates();

    if (!templates) {
        return;
    }

    await window.axios.post(urlFor(templates.complete, state.payload.assignment_id));
    hideRunner(true);
    toast('success', 'Tour abgeschlossen', 'Du kannst die Tour später im Profil erneut starten.');
}

function bindButtons() {
    const backButton = getElement(elementIds.back);
    const skipButton = getElement(elementIds.skip);
    const nextButton = getElement(elementIds.next);
    const completeButton = getElement(elementIds.complete);

    if (backButton instanceof HTMLElement) {
        backButton.onclick = async () => {
            state.stepIndex = Math.max(state.stepIndex - 1, 0);
            const nextStep = state.steps[state.stepIndex];
            if (nextStep) {
                state.payload.current_step_key = nextStep.key;
                state.lastSyncedStepKey = null;
            }
            await showCurrentStep();
        };
    }

    if (skipButton instanceof HTMLElement) {
        skipButton.onclick = () => skipTour();
    }

    if (nextButton instanceof HTMLElement) {
        nextButton.onclick = async () => {
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
        };
    }

    if (completeButton instanceof HTMLElement) {
        completeButton.onclick = () => completeTour();
    }
}

function hydrateRoot() {
    state.root = document.getElementById('tour-runner-root');
    bindButtons();
}

async function initTourRunner() {
    hydrateRoot();

    if (!state.root) {
        return;
    }

    if (state.payload) {
        await showCurrentStep();
        return;
    }

    await fetchCurrentTour();
}

window.addEventListener('resize', () => {
    if (state.payload) {
        void showCurrentStep();
    }
});

window.addEventListener('scroll', refreshHighlight, true);
document.addEventListener('livewire:navigated', () => void initTourRunner());

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => void initTourRunner(), { once: true });
} else {
    void initTourRunner();
}

export { initTourRunner };