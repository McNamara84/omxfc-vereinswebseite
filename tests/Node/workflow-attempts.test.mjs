import test from 'node:test';
import assert from 'node:assert/strict';
import { latestWorkflowRun } from '../../.github/scripts/latest-workflow-run.mjs';

const run = (changes = {}) => ({
    id: 100, run_attempt: 1, created_at: '2026-11-21T08:00:00Z', run_started_at: '2026-11-21T08:00:00Z',
    status: 'completed', conclusion: 'success', ...changes,
});

test('attempt start time takes precedence over run ID, initial creation, completion and attempt count', () => {
    const latest = run({ id: 50, run_attempt: 2, created_at: '2026-11-21T07:00:00Z', run_started_at: '2026-11-21T09:00:00Z' });
    const older = run({ run_attempt: 20, updated_at: '2026-11-21T10:00:00Z' });
    for (const candidates of [[older, latest], [latest, older]]) {
        assert.equal(latestWorkflowRun(candidates), latest);
        assert.equal(candidates.length, 2);
    }
    assert.equal(latestWorkflowRun([]), undefined);
});

test('failures and pending attempts take precedence when start times are indistinguishable', () => {
    const success = run({ id: 200 });
    const pending = run({ status: 'queued', conclusion: null });
    const failed = run({ id: 50, conclusion: 'failure' });
    for (const candidates of [[success, pending], [pending, success]]) assert.equal(latestWorkflowRun(candidates), pending);
    for (const candidates of [[success, pending, failed], [failed, pending, success]]) assert.equal(latestWorkflowRun(candidates), failed);
});

test('invalid attempt numbers and malformed, impossible or inverted timestamps fail closed', () => {
    for (const run_attempt of [undefined, null, '2', 0, -1, 1.5, Number.MAX_SAFE_INTEGER + 1]) {
        assert.throws(() => latestWorkflowRun([run(), run({ id: 50, run_attempt })]), /Invalid release workflow attempt timing/);
    }
    for (const field of ['created_at', 'run_started_at']) {
        for (const value of [undefined, null, 0, '', 'invalid', '2026-02-30T08:00:00Z', '2026-11-21', '2026-11-21T25:00:00Z']) {
            assert.throws(() => latestWorkflowRun([run(), run({ id: 50, [field]: value })]), /Invalid release workflow attempt timing/);
        }
    }
    assert.throws(() => latestWorkflowRun([run({ run_started_at: '2026-11-21T07:00:00Z' })]), /Invalid release workflow attempt timing/);
    assert.equal(latestWorkflowRun([run({ run_started_at: '2026-11-21T08:00:00.123Z' })]).id, 100);
});
