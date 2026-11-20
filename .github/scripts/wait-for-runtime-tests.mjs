import { setTimeout as delay } from 'node:timers/promises';
import { pathToFileURL } from 'node:url';

const required = ['phpunit.yml', 'vitest.yml', 'playwright.yml'];
const events = ['push', 'pull_request', 'merge_group'];

// PR API head_sha can differ from github.sha (the tested merge commit).
// The explicit run title records the latter, including changes to the PR base.
export async function waitForRuntimeTests({
    repository, sha, runId, event, token,
    fetchRun = globalThis.fetch, now = Date.now, sleep = delay,
    timeoutMs = 110 * 60_000,
}) {
    if (!/^[\w.-]+\/[\w.-]+$/.test(repository ?? '') || !/^[a-f0-9]{40}$/.test(sha ?? '')
        || !Number.isSafeInteger(runId) || runId <= 0 || !events.includes(event) || !token) {
        throw new Error('Incomplete PHP runtime test lookup environment.');
    }
    const api = async path => {
        const response = await fetchRun(`https://api.github.com/repos/${repository}/actions/${path}`, {
            headers: {
                Authorization: `Bearer ${token}`,
                Accept: 'application/vnd.github+json',
                'X-GitHub-Api-Version': '2026-03-10',
            },
            signal: AbortSignal.timeout(30_000),
        });
        if (!response.ok) throw new Error(`PHP runtime test lookup failed: HTTP ${response.status}`);
        return response.json();
    };
    const ownRun = await api(`runs/${runId}`);
    if (ownRun?.id !== runId || ownRun.event !== event || !/^[a-f0-9]{40}$/.test(ownRun.head_sha ?? '')) {
        throw new Error('Malformed PHP runtime workflow metadata.');
    }
    const deadline = now() + timeoutMs;
    while (now() < deadline) {
        const data = await api(`runs?event=${event}&head_sha=${ownRun.head_sha}&per_page=100`);
        if (!Array.isArray(data?.workflow_runs)) throw new Error('Malformed PHP runtime test response.');
        const matching = data.workflow_runs.filter(run => run?.head_sha === ownRun.head_sha
            && run.event === event && typeof run.display_title === 'string' && run.display_title.endsWith(` @ ${sha}`));
        const selected = required.map(file => {
            const runs = matching.filter(run => typeof run.path === 'string' && run.path.split('@')[0] === `.github/workflows/${file}`);
            if (runs.some(run => !Number.isSafeInteger(run.id) || run.id <= 0)) throw new Error('Invalid test workflow run ID.');
            return runs.sort((first, second) => second.id - first.id)[0];
        });
        const failed = selected.find(run => run?.status === 'completed' && run.conclusion !== 'success');
        if (failed) throw new Error(`PHP runtime approval blocked by ${failed.path}: ${failed.conclusion}`);
        if (selected.every(run => run?.status === 'completed' && run.conclusion === 'success')) {
            console.log(`PHP, JavaScript and browser tests passed for ${sha}.`);
            return;
        }
        await sleep(30_000);
    }
    throw new Error('Required tests for this PHP runtime revision did not finish within 110 minutes.');
}

if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
    try {
        await waitForRuntimeTests({
            repository: process.env.GITHUB_REPOSITORY,
            sha: process.env.GITHUB_SHA,
            runId: Number(process.env.GITHUB_RUN_ID),
            event: process.env.GITHUB_EVENT_NAME,
            token: process.env.GH_TOKEN,
        });
    } catch (error) {
        console.error(error.message);
        process.exitCode = 1;
    }
}
