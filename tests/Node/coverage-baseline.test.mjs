import test from 'node:test';
import assert from 'node:assert/strict';
import { mkdtempSync, mkdirSync, writeFileSync, rmSync, renameSync } from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { execFileSync, spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { createSnapshot } from '../../tools/testing/coverage.mjs';

const checker = fileURLToPath(new URL('../../tools/testing/check-coverage.mjs', import.meta.url));
const comparator = fileURLToPath(new URL('../../tools/testing/coverage.mjs', import.meta.url));
const floorPath = 'tests/coverage-baselines/php.json';
function fixture() {
    const root = mkdtempSync(path.join(os.tmpdir(), 'omxfc-coverage-'));
    mkdirSync(path.join(root, 'tests/coverage-baselines'), { recursive: true });
    mkdirSync(path.join(root, 'empty-hooks'));
    mkdirSync(path.join(root, 'app'));
    writeFileSync(path.join(root, 'app/example.php'), '<?php // removable source');
    writeFileSync(path.join(root, 'app/keeper.php'), '<?php // retained source');
    const git = args => execFileSync('git', ['-c', 'user.name=Coverage fixture', '-c', 'user.email=coverage-fixture@example.com', '-c', 'commit.gpgsign=false', '-c', `core.hooksPath=${path.join(root, 'empty-hooks')}`, ...args], { cwd: root, encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'] });
    const snapshot = covered => createSnapshot('php', 'fixture', {
        'app/example.php': { hash: 'a'.repeat(64), units: { lines: { 1: true, 2: covered }, functions: {} }, metrics: { lines: { total: 2, covered: covered ? 2 : 1 }, functions: { total: 0, covered: 0 } } },
        'app/keeper.php': { hash: 'b'.repeat(64), units: { lines: { 1: true, 2: false }, functions: {} }, metrics: { lines: { total: 2, covered: 1 }, functions: { total: 0, covered: 0 } } },
    });
    writeFileSync(path.join(root, floorPath), JSON.stringify(snapshot(true)));
    git(['init', '--quiet']);
    git(['add', floorPath, 'app']);
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
    const compare = () => spawnSync(process.execPath, [comparator, 'compare', floorPath, 'candidate.json'], { cwd: root, encoding: 'utf8' });
    return { root, snapshot, run, compare, cleanup };
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

test('deleted sources pass local and protected PR comparisons with an unchanged or updated floor', () => {
    const data = fixture();
    try {
        rmSync(path.join(data.root, 'app/example.php'));
        const retained = createSnapshot('php', 'fixture', { 'app/keeper.php': data.snapshot(true).files['app/keeper.php'] });
        writeFileSync(path.join(data.root, 'candidate.json'), JSON.stringify(retained));
        // Removing the fully covered source legitimately changes 75% to 50%.
        for (const updateFloor of [false, true]) {
            if (updateFloor) writeFileSync(path.join(data.root, floorPath), JSON.stringify(retained));
            assert.equal(data.compare().status, 0);
            assert.equal(data.run(undefined, '').status, 0);
            const guarded = data.run();
            assert.equal(guarded.status, 0, guarded.stderr);
        }
        const lost = structuredClone(retained.files);
        lost['app/keeper.php'].units.lines[1] = false;
        lost['app/keeper.php'].metrics.lines.covered = 0;
        writeFileSync(path.join(data.root, 'candidate.json'), JSON.stringify(createSnapshot('php', 'fixture', lost)));
        const guarded = data.run();
        assert.equal(guarded.status, 1);
        assert.match(guarded.stderr, /app\/keeper.php: previously covered lines 1/);
    } finally { data.cleanup(); }
});

test('existing sources omitted from instrumentation fail even if the PR removes them from its floor', () => {
    const data = fixture();
    try {
        const omitted = createSnapshot('php', 'fixture', { 'app/keeper.php': data.snapshot(true).files['app/keeper.php'] });
        writeFileSync(path.join(data.root, 'candidate.json'), JSON.stringify(omitted));
        const local = data.run(undefined, '');
        assert.equal(data.compare().status, 1);
        assert.equal(local.status, 1);
        assert.match(local.stderr, /Source file missing from coverage: app\/example.php/);
        writeFileSync(path.join(data.root, floorPath), JSON.stringify(omitted));
        const guarded = data.run();
        assert.equal(guarded.status, 1);
        assert.match(guarded.stderr, /Baseline lowered: Source file missing from coverage: app\/example.php/);
    } finally { data.cleanup(); }
});

test('unchanged source renames pass but retain their protected coverage contract', () => {
    const data = fixture();
    try {
        renameSync(path.join(data.root, 'app/example.php'), path.join(data.root, 'app/renamed.php'));
        const files = data.snapshot(true).files;
        files['app/renamed.php'] = files['app/example.php'];
        delete files['app/example.php'];
        const renamed = createSnapshot('php', 'fixture', files);
        writeFileSync(path.join(data.root, 'candidate.json'), JSON.stringify(renamed));
        const guarded = data.run();
        assert.equal(guarded.status, 0, guarded.stderr);
        writeFileSync(path.join(data.root, floorPath), JSON.stringify(renamed));
        assert.equal(data.run().status, 0);
        files['app/renamed.php'].units.lines[2] = false;
        files['app/renamed.php'].metrics.lines.covered = 1;
        writeFileSync(path.join(data.root, 'candidate.json'), JSON.stringify(createSnapshot('php', 'fixture', files)));
        const lost = data.run();
        assert.equal(lost.status, 1);
        assert.match(lost.stderr, /app\/example.php -> app\/renamed.php: previously covered lines 2/);
    } finally { data.cleanup(); }
});
