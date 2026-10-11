import test from 'node:test';
import assert from 'node:assert/strict';
import { mkdtempSync, mkdirSync, writeFileSync, rmSync } from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { execFileSync, spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { createSnapshot } from '../../tools/testing/coverage.mjs';

const checker = fileURLToPath(new URL('../../tools/testing/check-coverage.mjs', import.meta.url));
const floorPath = 'tests/coverage-baselines/php.json';
function fixture() {
    const root = mkdtempSync(path.join(os.tmpdir(), 'omxfc-coverage-'));
    mkdirSync(path.join(root, 'tests/coverage-baselines'), { recursive: true });
    mkdirSync(path.join(root, 'empty-hooks'));
    const git = args => execFileSync('git', ['-c', 'user.name=Coverage fixture', '-c', 'user.email=coverage-fixture@example.com', '-c', 'commit.gpgsign=false', '-c', `core.hooksPath=${path.join(root, 'empty-hooks')}`, ...args], { cwd: root, encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'] });
    const snapshot = covered => createSnapshot('php', 'fixture', {
        'app/example.php': { hash: 'a'.repeat(64), units: { lines: { 1: true, 2: covered }, functions: {} }, metrics: { lines: { total: 2, covered: covered ? 2 : 1 }, functions: { total: 0, covered: 0 } } },
    });
    writeFileSync(path.join(root, floorPath), JSON.stringify(snapshot(true)));
    git(['init', '--quiet']);
    git(['add', floorPath]);
    git(['commit', '--quiet', '-m', 'Coverage fixture']);
    const base = git(['rev-parse', 'HEAD']).trim();
    const run = (sha = base, event = 'pull_request') => {
        writeFileSync(path.join(root, 'event.json'), JSON.stringify({ pull_request: { base: { sha } } }));
        return spawnSync(process.execPath, [checker, floorPath, 'candidate.json'], { cwd: root, encoding: 'utf8', env: { ...process.env, GITHUB_EVENT_NAME: event, GITHUB_EVENT_PATH: path.join(root, 'event.json') } });
    };
    const cleanup = () => {
        if (!root.startsWith(path.resolve(os.tmpdir()) + path.sep) || !path.basename(root).startsWith('omxfc-coverage-')) throw new Error('Unexpected temporary fixture path');
        rmSync(root, { recursive: true, force: true });
    };
    return { root, snapshot, run, cleanup };
}

test('a lowered PR baseline cannot hide lost coverage against the protected base', () => {
    const data = fixture();
    try {
        const lowered = JSON.stringify(data.snapshot(false));
        writeFileSync(path.join(data.root, floorPath), lowered);
        writeFileSync(path.join(data.root, 'candidate.json'), lowered);
        assert.equal(data.run(undefined, '').status, 0);
        const guarded = data.run();
        assert.equal(guarded.status, 1);
        assert.match(guarded.stderr, /Baseline lowered/);
        assert.match(guarded.stderr, /previously covered/);
    } finally { data.cleanup(); }
});

test('a missing base commit fails instead of silently accepting the local floor', () => {
    const data = fixture();
    try {
        writeFileSync(path.join(data.root, 'candidate.json'), JSON.stringify(data.snapshot(true)));
        assert.equal(data.run().status, 0);
        assert.equal(data.run('f'.repeat(40)).status, 1);
    } finally { data.cleanup(); }
});
