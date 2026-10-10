import { appendFileSync } from 'node:fs';
import { setTimeout as delay } from 'node:timers/promises';
import { pathToFileURL } from 'node:url';

const repository = process.env.GITHUB_REPOSITORY;
const sha = process.env.GITHUB_SHA;
const required = [
    'phpunit.yml', 'vitest.yml', 'playwright.yml',
    'dependency-review.yml', 'mutation.yml', 'service-image-scan.yml',
];
const deadline = Date.now() + 90 * 60_000;
const api = async path => {
    const response = await fetch(`https://api.github.com/repos/${repository}/${path}`, {
        headers: {
            Authorization: `Bearer ${process.env.GH_TOKEN}`,
            Accept: 'application/vnd.github+json',
            'X-GitHub-Api-Version': '2022-11-28',
        },
        signal: AbortSignal.timeout(30_000),
    });
    if (!response.ok) throw new Error(`GitHub check lookup failed: HTTP ${response.status}`);
    return response.json();
};

export function selectReleaseChecks(runs) {
    const selected = required.map(file => runs.find(run => run.path === `.github/workflows/${file}`));
    const codeql = runs.find(run => run.name === 'CodeQL');
    if (codeql) selected.push(codeql);
    return selected;
}

export function assessReleaseChecks(runs) {
    const selected = selectReleaseChecks(runs);
    const failed = selected.find(run => run?.status === 'completed' && run.conclusion !== 'success');
    if (failed) throw new Error(`Deployment blocked by ${failed.name}: ${failed.conclusion} (${failed.html_url})`);
    return {
        ready: selected.every(run => run?.status === 'completed' && run.conclusion === 'success'),
        pending: selected.filter(run => !run || run.status !== 'completed').length,
    };
}

export async function waitForReleaseChecks() {
    if (!repository || !sha || !process.env.GH_TOKEN || !process.env.GITHUB_OUTPUT) throw new Error('Release check environment is incomplete.');

    while (Date.now() < deadline) {
        const head = await api('git/ref/heads/main');
        if (head.object.sha !== sha) {
            appendFileSync(process.env.GITHUB_OUTPUT, 'deploy=false\n');
            console.log('A newer main revision supersedes this deployment.');
            return;
        }
        const { workflow_runs: runs } = await api(`actions/runs?event=push&head_sha=${sha}&per_page=100`);
        const result = assessReleaseChecks(runs);

        if (result.ready) {
            const head = await api('git/ref/heads/main');
            const current = head.object.sha === sha;
            appendFileSync(process.env.GITHUB_OUTPUT, `deploy=${current}\n`);
            console.log(current ? `All release checks passed for ${sha}.` : 'A newer main revision supersedes this deployment.');
            return;
        }

        console.log(`Waiting for ${result.pending} release workflows for ${sha}.`);
        await delay(30_000);
    }

    throw new Error('Release checks did not finish within 90 minutes.');
}

if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) await waitForReleaseChecks();
