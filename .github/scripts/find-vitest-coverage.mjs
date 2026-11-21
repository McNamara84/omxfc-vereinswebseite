import { appendFileSync } from 'node:fs';
import { setTimeout as delay } from 'node:timers/promises';
import { pathToFileURL } from 'node:url';
import { latestWorkflowRun } from './latest-workflow-run.mjs';

// Never reuse another revision or hide a failed newer run behind an old success.
export async function findVitestCoverageRun({
    repository, sha, token,
    fetchRun = globalThis.fetch,
    now = Date.now,
    sleep = delay,
    timeoutMs = 20 * 60_000,
}) {
    if (!/^[\w.-]+\/[\w.-]+$/.test(repository ?? '') || !/^[a-f0-9]{40}$/.test(sha ?? '') || !token) {
        throw new Error('Incomplete GitHub coverage lookup environment.');
    }
    const deadline = now() + timeoutMs;
    const endpoint = `https://api.github.com/repos/${repository}/actions/workflows/vitest.yml/runs?event=push&branch=main&head_sha=${sha}&per_page=100`;
    while (now() < deadline) {
        const response = await fetchRun(endpoint, {
            headers: {
                Authorization: `Bearer ${token}`,
                Accept: 'application/vnd.github+json',
                'X-GitHub-Api-Version': '2026-03-10',
            },
            signal: AbortSignal.timeout(30_000),
        });
        if (!response.ok) throw new Error(`Vitest coverage lookup failed: HTTP ${response.status}`);
        const data = await response.json();
        if (!Array.isArray(data.workflow_runs)) throw new Error('Malformed Vitest workflow response.');
        const matching = data.workflow_runs.filter(run => run?.head_sha === sha
            && run.event === 'push' && run.head_branch === 'main'
            && typeof run.path === 'string' && run.path.split('@')[0] === '.github/workflows/vitest.yml');
        const latest = latestWorkflowRun(matching, 'Vitest');
        if (latest?.status === 'completed') {
            if (latest.conclusion !== 'success') throw new Error(`Vitest coverage is unavailable: ${latest.conclusion}`);
            return latest.id;
        }
        await sleep(15_000);
    }
    throw new Error('No successful Vitest push completed for this revision within 20 minutes.');
}

if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
    try {
        if (!process.env.GITHUB_OUTPUT) throw new Error('GITHUB_OUTPUT is required.');
        const id = await findVitestCoverageRun({
            repository: process.env.GITHUB_REPOSITORY,
            sha: process.env.GITHUB_SHA,
            token: process.env.GH_TOKEN,
        });
        appendFileSync(process.env.GITHUB_OUTPUT, `run_id=${id}\n`);
    } catch (error) {
        console.error(error.message);
        process.exitCode = 1;
    }
}
