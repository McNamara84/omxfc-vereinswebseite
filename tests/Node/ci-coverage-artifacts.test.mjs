import test from 'node:test';
import assert from 'node:assert/strict';
import { findVitestCoverageRun } from '../../.github/scripts/find-vitest-coverage.mjs';

const sha = 'a'.repeat(40);
const run = (changes = {}) => ({
    id: 123, head_sha: sha, head_branch: 'main', event: 'push',
    run_attempt: 1, created_at: '2026-11-21T08:00:00Z', run_started_at: '2026-11-21T08:00:00Z',
    path: '.github/workflows/vitest.yml', status: 'completed', conclusion: 'success', ...changes,
});
const options = (runs, changes = {}) => ({
    repository: 'owner/repository', sha, token: 'fixture-token',
    fetchRun: async () => ({ ok: true, json: async () => ({ workflow_runs: runs }) }),
    ...changes,
});

test('coverage lookup queries only main pushes for the requested workflow and revision', async () => {
    const result = await findVitestCoverageRun(options([run()], {
        fetchRun: async (endpoint, request) => {
            const url = new URL(endpoint);
            assert.equal(url.origin, 'https://api.github.com');
            assert.equal(url.pathname, '/repos/owner/repository/actions/workflows/vitest.yml/runs');
            assert.equal(url.searchParams.get('head_sha'), sha);
            assert.equal(url.searchParams.get('branch'), 'main');
            assert.equal(url.searchParams.get('event'), 'push');
            assert.equal(request.headers.Authorization, 'Bearer fixture-token');
            assert.ok(request.signal instanceof AbortSignal);
            return { ok: true, json: async () => ({ workflow_runs: [run()] }) };
        },
    }));
    assert.equal(result, 123);
});

test('coverage from other revisions branches events and workflows is never reused', async () => {
    const unrelated = [
        run({ id: 900, head_sha: 'b'.repeat(40) }),
        run({ id: 901, head_branch: 'feature' }),
        run({ id: 902, event: 'workflow_dispatch' }),
        run({ id: 903, path: '.github/workflows/phpunit.yml' }),
    ];
    assert.equal(await findVitestCoverageRun(options([...unrelated, run()])), 123);
    let clock = 0;
    await assert.rejects(findVitestCoverageRun(options(unrelated, {
        timeoutMs: 1, now: () => clock, sleep: async ms => { clock += ms; },
    })), /No successful Vitest push/);
});

test('missing queued and running producers are awaited before returning their artifacts', async () => {
    let clock = 0;
    const states = [[], [run({ status: 'queued', conclusion: null })], [run({ status: 'in_progress', conclusion: null })], [run()]];
    const id = await findVitestCoverageRun(options([], {
        now: () => clock,
        sleep: async ms => { clock += ms; },
        fetchRun: async () => ({ ok: true, json: async () => ({ workflow_runs: states.shift() }) }),
    }));
    assert.equal(id, 123);
    assert.equal(states.length, 0);
    assert.equal(clock, 45_000);
});

for (const conclusion of ['failure', 'cancelled', 'timed_out', 'skipped', 'neutral', null]) {
    test(`a ${conclusion} producer cannot be hidden behind an older successful run`, async () => {
        await assert.rejects(findVitestCoverageRun(options([
            run({ id: 122 }), run({ conclusion }),
        ])), /Vitest coverage is unavailable/);
    });
}

test('a pending newer producer cannot reuse an older successful artifact', async () => {
    let clock = 0;
    await assert.rejects(findVitestCoverageRun(options([
        run({ id: 122 }), run({ status: 'in_progress', conclusion: null }),
    ], { timeoutMs: 1, now: () => clock, sleep: async ms => { clock += ms; } })), /No successful Vitest push/);
});

test('unordered responses still select the newest successful producer', async () => {
    assert.equal(await findVitestCoverageRun(options([run({ id: 124 }), run({ id: 121 })])), 124);
});

for (const status of ['queued', 'in_progress', 'completed']) {
    test(`an older producer rerun after a newer success cannot supply coverage while ${status}`, async () => {
        let clock = 0;
        await assert.rejects(findVitestCoverageRun(options([
            run(), run({
                id: 50, run_attempt: 2,
                created_at: '2026-11-21T07:00:00Z', run_started_at: '2026-11-21T09:00:00Z',
                status, conclusion: status === 'completed' ? 'failure' : null,
            }),
        ], { now: () => clock, timeoutMs: 1, sleep: async ms => { clock += ms; } })),
        status === 'completed' ? /Vitest coverage is unavailable/ : /No successful Vitest push/);
    });
}

test('coverage uses the successful later attempt even when its run ID is lower', async () => {
    assert.equal(await findVitestCoverageRun(options([
        run({ conclusion: 'failure' }), run({
            id: 50, run_attempt: 2,
            created_at: '2026-11-21T07:00:00Z', run_started_at: '2026-11-21T09:00:00Z',
        }),
    ])), 50);
});

test('missing or invalid attempt timing cannot select coverage from an older success', async () => {
    for (const changes of [
        { run_started_at: undefined }, { run_started_at: 'invalid' },
        { run_started_at: '2026-02-30T09:00:00Z' }, { created_at: null },
        { run_started_at: '2026-11-21T07:00:00Z' }, { run_attempt: 0 },
    ]) {
        await assert.rejects(findVitestCoverageRun(options([
            run(), run({ id: 50, ...changes }),
        ])), /Invalid Vitest workflow attempt timing/);
    }
});

test('GitHub API workflow path references still identify the same Vitest workflow', async () => {
    assert.equal(await findVitestCoverageRun(options([run({ path: '.github/workflows/vitest.yml@main' })])), 123);
});

test('lookup rejects unreadable or malformed API results', async () => {
    for (const status of [401, 403, 500]) {
        await assert.rejects(findVitestCoverageRun(options([], {
            fetchRun: async () => ({ ok: false, status }),
        })), new RegExp(`HTTP ${status}`));
    }
    for (const data of [{}, { workflow_runs: null }, { workflow_runs: {} }]) {
        await assert.rejects(findVitestCoverageRun(options([], {
            fetchRun: async () => ({ ok: true, json: async () => data }),
        })), /Malformed Vitest workflow response/);
    }
    await assert.rejects(findVitestCoverageRun(options([], {
        fetchRun: async () => { throw new Error('Connection unavailable'); },
    })), /Connection unavailable/);
});

test('invalid run IDs cannot become download selectors or hide behind an older result', async () => {
    for (const id of ['123', 0, -1, NaN, Infinity, 1.5, Number.MAX_SAFE_INTEGER + 1]) {
        await assert.rejects(findVitestCoverageRun(options([run({ id: 122 }), run({ id })])), /Invalid Vitest workflow run ID/);
    }
});

test('invalid lookup configuration fails before making a request', async () => {
    for (const changes of [{ token: '' }, { sha: '' }, { sha: 'x'.repeat(40) }, { repository: 'other/owner/repository' }, { repository: 'owner/repo?query=other' }]) {
        await assert.rejects(findVitestCoverageRun(options([], {
            ...changes, fetchRun: async () => assert.fail('Invalid configuration must not request artifacts'),
        })), /Incomplete GitHub coverage lookup environment/);
    }
});
