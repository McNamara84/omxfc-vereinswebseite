import test from 'node:test';
import assert from 'node:assert/strict';
import { assessReleaseChecks } from '../../.github/scripts/wait-for-release-checks.mjs';

const workflows = ['phpunit.yml', 'vitest.yml', 'playwright.yml', 'dependency-review.yml', 'mutation.yml', 'service-image-scan.yml'];
const successfulRuns = () => workflows.map(file => ({
    path: `.github/workflows/${file}`, name: file, status: 'completed', conclusion: 'success',
}));

test('release waits for every required workflow, including missing or running checks', () => {
    assert.deepEqual(assessReleaseChecks([]), { ready: false, pending: 6 });
    const runs = successfulRuns();
    runs[0].status = 'in_progress';
    assert.deepEqual(assessReleaseChecks(runs), { ready: false, pending: 1 });
    assert.deepEqual(assessReleaseChecks(successfulRuns()), { ready: true, pending: 0 });
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
        name: 'CodeQL', status: 'completed', conclusion: 'failure',
    }]), /Deployment blocked by CodeQL/);
});
