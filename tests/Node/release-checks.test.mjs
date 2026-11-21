import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { assessReleaseChecks } from '../../.github/scripts/wait-for-release-checks.mjs';

const workflows = ['phpunit.yml', 'vitest.yml', 'playwright.yml', 'dependency-review.yml', 'mutation.yml', 'service-image-scan.yml', 'ci.yml'];
const successfulRuns = () => workflows.map((file, index) => ({
    id: 100 + index, run_attempt: 1,
    created_at: '2026-11-21T08:00:00Z', run_started_at: '2026-11-21T08:00:00Z',
    path: `.github/workflows/${file}`, name: file, status: 'completed', conclusion: 'success',
}));

test('merge queues retain every release check after removing the duplicate compatibility test run', () => {
    for (const filename of workflows) {
        const workflow = readFileSync(new URL(`../../.github/workflows/${filename}`, import.meta.url), 'utf8');
        assert.match(workflow, /^  merge_group:/m, filename);
    }
});

test('release waits for every required workflow, including missing or running checks', () => {
    assert.deepEqual(assessReleaseChecks([]), { ready: false, pending: 7 });
    const runs = successfulRuns();
    runs[0].status = 'in_progress';
    assert.deepEqual(assessReleaseChecks(runs), { ready: false, pending: 1 });
    assert.deepEqual(assessReleaseChecks(successfulRuns()), { ready: true, pending: 0 });
});

test('CI verification must complete successfully before deployment', () => {
    const runs = successfulRuns();
    assert.equal(assessReleaseChecks(runs.filter(run => run.path !== '.github/workflows/ci.yml')).ready, false);
    const ci = runs.find(run => run.path === '.github/workflows/ci.yml');
    for (const conclusion of ['failure', 'cancelled', 'skipped', 'timed_out', 'neutral', null]) {
        ci.conclusion = conclusion;
        assert.throws(() => assessReleaseChecks(runs), /Deployment blocked by ci/);
    }
});

test('GitHub API workflow path references retain every required release gate', () => {
    const runs = successfulRuns().map(run => ({ ...run, path: `${run.path}@main` }));
    assert.deepEqual(assessReleaseChecks(runs), { ready: true, pending: 0 });
    runs[0].conclusion = 'failure';
    assert.throws(() => assessReleaseChecks(runs), /Deployment blocked by phpunit/);
});

test('failed, cancelled, skipped and timed out release checks cannot deploy', () => {
    for (const conclusion of ['failure', 'cancelled', 'skipped', 'timed_out', 'neutral', null]) {
        const runs = successfulRuns();
        runs[4].conclusion = conclusion;
        assert.throws(() => assessReleaseChecks(runs), /Deployment blocked by mutation/);
    }
});

test('latest attempt is authoritative and managed CodeQL failures block deployment', () => {
    const runs = successfulRuns();
    const retry = { ...runs[0], status: 'in_progress', conclusion: null };
    assert.equal(assessReleaseChecks([retry, ...runs]).ready, false);
    assert.throws(() => assessReleaseChecks([...runs, {
        ...runs[0], id: 200, path: '.github/workflows/codeql.yml',
        name: 'CodeQL', status: 'completed', conclusion: 'failure',
    }]), /Deployment blocked by CodeQL/);
});

for (const status of ['queued', 'in_progress', 'completed']) {
    test(`deployment considers an older run rerun after a newer success while ${status}`, () => {
        const runs = successfulRuns();
        const retry = {
            ...runs[0], id: 50, run_attempt: 2,
            created_at: '2026-11-21T07:00:00Z', run_started_at: '2026-11-21T09:00:00Z',
            status, conclusion: status === 'completed' ? 'failure' : null,
        };
        if (status === 'completed') assert.throws(() => assessReleaseChecks([...runs, retry]), /Deployment blocked by phpunit/);
        else assert.deepEqual(assessReleaseChecks([...runs, retry]), { ready: false, pending: 1 });
    });
}

test('deployment accepts a successful latest attempt independently of API order and run ID', () => {
    const runs = successfulRuns();
    const retry = {
        ...runs[0], id: 50, run_attempt: 2,
        created_at: '2026-11-21T07:00:00Z', run_started_at: '2026-11-21T09:00:00Z',
    };
    runs[0].conclusion = 'failure';
    for (const candidates of [[...runs, retry], [retry, ...runs]]) {
        assert.deepEqual(assessReleaseChecks(candidates), { ready: true, pending: 0 });
    }
});
