function timestamp(value) {
    if (typeof value !== 'string' || !/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{3})?Z$/.test(value)) return NaN;
    const parsed = Date.parse(value);
    const normalized = value.length === 20 ? `${value.slice(0, -1)}.000Z` : value;
    return Number.isFinite(parsed) && new Date(parsed).toISOString() === normalized ? parsed : NaN;
}

const blockingPriority = run => run.status === 'completed' ? (run.conclusion === 'success' ? 0 : 2) : 1;

// Run IDs and created_at survive reruns. run_started_at identifies the latest attempt.
export function latestWorkflowRun(runs, label = 'release') {
    const attempts = runs.map(run => {
        if (!Number.isSafeInteger(run.id) || run.id <= 0) throw new Error(`Invalid ${label} workflow run ID.`);
        const created = timestamp(run.created_at);
        const started = timestamp(run.run_started_at);
        if (!Number.isSafeInteger(run.run_attempt) || run.run_attempt <= 0
            || !Number.isFinite(created) || !Number.isFinite(started) || started < created) {
            throw new Error(`Invalid ${label} workflow attempt timing.`);
        }
        return { run, started };
    });
    attempts.sort((first, second) => second.started - first.started
        // GitHub timestamps have second precision: an ambiguous success cannot hide a blocker.
        || blockingPriority(second.run) - blockingPriority(first.run)
        || second.run.id - first.run.id);
    return attempts[0]?.run;
}
