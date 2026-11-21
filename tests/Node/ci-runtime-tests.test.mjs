import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { waitForRuntimeTests } from '../../.github/scripts/wait-for-runtime-tests.mjs';

const sha = 'a'.repeat(40);
const headSha = 'b'.repeat(40);
const files = ['phpunit.yml', 'vitest.yml', 'playwright.yml'];
const successfulRuns = () => files.map((file, index) => ({
    id: 100 + index, head_sha: headSha, event: 'pull_request',
    path: `.github/workflows/${file}`, display_title: `Test workflow @ ${sha}`,
    run_attempt: 1, created_at: '2026-11-21T08:00:00Z', run_started_at: '2026-11-21T08:00:00Z',
    status: 'completed', conclusion: 'success',
}));
const options = (runs = successfulRuns(), changes = {}) => ({
    repository: 'owner/repository', sha, runId: 42, event: 'pull_request', token: 'fixture-token',
    fetchRun: async endpoint => ({
        ok: true,
        json: async () => endpoint.endsWith('/42')
            ? { id: 42, head_sha: headSha, event: 'pull_request' } : { workflow_runs: runs },
    }),
    ...changes,
});

test('the gate uses the API event and head SHA while requiring the actual tested PR merge SHA', async () => {
    let calls = 0;
    const fixture = options();
    await waitForRuntimeTests({ ...fixture, fetchRun: async (endpoint, request) => {
        assert.equal(request.headers.Authorization, 'Bearer fixture-token');
        assert.ok(request.signal instanceof AbortSignal);
        const url = new URL(endpoint);
        assert.equal(url.origin, 'https://api.github.com');
        if (calls++ === 0) assert.equal(url.pathname, '/repos/owner/repository/actions/runs/42');
        else {
            assert.equal(url.searchParams.get('head_sha'), headSha);
            assert.equal(url.searchParams.get('event'), 'pull_request');
        }
        return fixture.fetchRun(endpoint);
    } });
    assert.equal(calls, 2);
});

test('producer workflows attest their tested revision and retain nightly and manual checks', () => {
    for (const file of files) {
        const workflow = readFileSync(new URL(`../../.github/workflows/${file}`, import.meta.url), 'utf8');
        assert.match(workflow, /^run-name: '\$\{\{ github\.workflow \}\} @ \$\{\{ github\.sha \}\}'$/m, file);
        assert.match(workflow, /^  workflow_dispatch:/m, file);
        assert.match(workflow, /^  schedule:/m, file);
    }
});

test('old merge results, other events, revisions and workflow paths cannot approve the runtime', async () => {
    const unrelated = successfulRuns().flatMap(run => [
        { ...run, display_title: `Test workflow @ ${'c'.repeat(40)}` },
        { ...run, head_sha: 'c'.repeat(40) },
        { ...run, event: 'push' },
        { ...run, path: '.github/workflows/other.yml' },
        { ...run, display_title: undefined },
    ]);
    let clock = 0;
    await assert.rejects(waitForRuntimeTests(options(unrelated, {
        now: () => clock, timeoutMs: 1, sleep: async ms => { clock += ms; },
    })), /Required tests for this PHP runtime revision/);
});

test('missing, queued and running workflows are awaited without repeating their tests', async () => {
    let clock = 0;
    const states = [[], successfulRuns().map(run => ({ ...run, status: 'queued' })),
        successfulRuns().map(run => ({ ...run, status: 'in_progress' })), successfulRuns()];
    const fixture = options();
    await waitForRuntimeTests(options([], {
        now: () => clock, sleep: async ms => { clock += ms; },
        fetchRun: async endpoint => endpoint.endsWith('/42') ? fixture.fetchRun(endpoint)
            : { ok: true, json: async () => ({ workflow_runs: states.shift() }) },
    }));
    assert.equal(clock, 90_000);
    assert.equal(states.length, 0);
});

for (const conclusion of ['failure', 'cancelled', 'timed_out', 'skipped', 'neutral', null]) {
    test(`a ${conclusion} test workflow blocks approval even with an older successful run`, async () => {
        const runs = successfulRuns();
        await assert.rejects(waitForRuntimeTests(options([
            ...runs, { ...runs[0], id: 200, conclusion },
        ])), /PHP runtime approval blocked by/);
    });
}

test('a pending newer run cannot be hidden by an older success or another tested merge SHA', async () => {
    const runs = successfulRuns();
    let clock = 0;
    await assert.rejects(waitForRuntimeTests(options([
        ...runs, { ...runs[0], id: 200, status: 'in_progress', conclusion: null },
        { ...runs[0], id: 300, display_title: `Test workflow @ ${'c'.repeat(40)}` },
    ], { now: () => clock, timeoutMs: 1, sleep: async ms => { clock += ms; } })), /Required tests/);
});

for (const status of ['queued', 'in_progress', 'completed']) {
    test(`an older run rerun after a newer success blocks runtime approval while ${status}`, async () => {
        const runs = successfulRuns();
        let clock = 0;
        await assert.rejects(waitForRuntimeTests(options([
            ...runs, {
                ...runs[0], id: 50, run_attempt: 2,
                created_at: '2026-11-21T07:00:00Z', run_started_at: '2026-11-21T09:00:00Z',
                status, conclusion: status === 'completed' ? 'failure' : null,
            },
        ], { now: () => clock, timeoutMs: 1, sleep: async ms => { clock += ms; } })),
        status === 'completed' ? /PHP runtime approval blocked by/ : /Required tests/);
    });
}

test('a successful later attempt of an older run supersedes a newer-ID failure', async () => {
    const runs = successfulRuns();
    await waitForRuntimeTests(options([
        ...runs.map(run => ({ ...run, conclusion: 'failure' })),
        ...runs.map(run => ({
            ...run, id: run.id - 50, run_attempt: 2,
            created_at: '2026-11-21T07:00:00Z', run_started_at: '2026-11-21T09:00:00Z',
        })),
    ]));
});

test('missing or invalid attempt timing cannot approve a runtime', async () => {
    for (const changes of [
        { run_started_at: undefined }, { run_started_at: 'invalid' },
        { run_started_at: '2026-02-30T09:00:00Z' }, { created_at: null },
        { run_started_at: '2026-11-21T07:00:00Z' }, { run_attempt: 0 },
    ]) {
        const runs = successfulRuns();
        await assert.rejects(waitForRuntimeTests(options([
            ...runs, { ...runs[0], id: 50, ...changes },
        ])), /Invalid test workflow attempt timing/);
    }
});

test('API path references are accepted but malformed run IDs and metadata fail closed', async () => {
    await waitForRuntimeTests(options(successfulRuns().map(run => ({ ...run, path: `${run.path}@main` }))));
    for (const id of [0, -1, 1.5, '123', Number.MAX_SAFE_INTEGER + 1]) {
        const runs = successfulRuns();
        await assert.rejects(waitForRuntimeTests(options([...runs, { ...runs[0], id }])), /Invalid test workflow run ID/);
    }
    for (const data of [null, {}, { id: 42, event: 'push', head_sha: headSha }, { id: 42, event: 'pull_request', head_sha: 'invalid' }]) {
        await assert.rejects(waitForRuntimeTests(options([], {
            fetchRun: async () => ({ ok: true, json: async () => data }),
        })), /Malformed PHP runtime workflow metadata/);
    }
    const fixture = options();
    for (const data of [null, {}, { workflow_runs: null }]) {
        await assert.rejects(waitForRuntimeTests(options([], {
            fetchRun: async endpoint => endpoint.endsWith('/42') ? fixture.fetchRun(endpoint)
                : { ok: true, json: async () => data },
        })), /Malformed PHP runtime test response/);
    }
});

test('HTTP errors and missing configuration cannot approve a runtime', async () => {
    for (const status of [401, 403, 500]) {
        await assert.rejects(waitForRuntimeTests(options([], {
            fetchRun: async () => ({ ok: false, status }),
        })), new RegExp(`HTTP ${status}`));
    }
    for (const changes of [{ token: '' }, { sha: '' }, { runId: 0 }, { event: 'workflow_dispatch' }, { repository: 'owner/repository?other=repo' }]) {
        await assert.rejects(waitForRuntimeTests(options([], {
            ...changes, fetchRun: async () => assert.fail('Invalid configuration must not query GitHub'),
        })), /Incomplete PHP runtime test lookup environment/);
    }
});
