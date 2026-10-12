import test from 'node:test';
import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import { copyFileSync, mkdirSync, mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import path from 'node:path';
import { snapshotJavaScript } from '../../tools/testing/coverage.mjs';

const packageJson = JSON.parse(readFileSync(new URL('../../package.json', import.meta.url), 'utf8'));

function fixture(t) {
    const root = mkdtempSync(path.join(tmpdir(), 'omxfc-coverage-script with spaces-'));
    t.after(() => {
        if (!root.startsWith(path.resolve(tmpdir()) + path.sep) || !path.basename(root).startsWith('omxfc-coverage-script with spaces-')) throw new Error('Unexpected temporary fixture path');
        rmSync(root, { recursive: true, force: true });
    });
    for (const directory of ['tools/testing', 'tests/coverage-baselines', 'resources/js', 'coverage']) mkdirSync(path.join(root, directory), { recursive: true });
    for (const filename of ['coverage.mjs', 'check-coverage.mjs']) copyFileSync(new URL(`../../tools/testing/${filename}`, import.meta.url), path.join(root, 'tools/testing', filename));
    writeFileSync(path.join(root, 'package.json'), JSON.stringify({ private: true, type: 'module', scripts: { 'test:coverage:check': packageJson.scripts['test:coverage:check'] } }));
    const source = path.join(root, 'resources/js/example.js');
    writeFileSync(source, 'const first = 1;\nconst second = 2;\n');
    const report = {
        [source]: {
            statementMap: { 0: { start: { line: 1, column: 0 }, end: { line: 1, column: 16 } }, 1: { start: { line: 2, column: 0 }, end: { line: 2, column: 17 } } },
            s: { 0: 1, 1: 1 }, fnMap: {}, f: {}, branchMap: {}, b: {},
        },
    };
    const baseline = snapshotJavaScript(report, { root });
    const write = (filename, value) => writeFileSync(path.join(root, filename), JSON.stringify(value));
    write('tests/coverage-baselines/javascript.json', baseline);
    write('coverage/coverage-final.json', report);
    const executable = process.platform === 'win32' ? process.env.ComSpec ?? 'cmd.exe' : 'npm';
    const args = process.platform === 'win32' ? ['/d', '/s', '/c', 'npm.cmd run test:coverage:check'] : ['run', 'test:coverage:check'];
    const run = () => spawnSync(executable, args, {
        cwd: root, encoding: 'utf8', windowsHide: true, timeout: 10000,
        env: { ...process.env, GITHUB_EVENT_NAME: '' },
    });
    return { root, report, baseline, write, run };
}

test('the npm coverage check generates a fresh snapshot and rejects lost coverage', t => {
    const data = fixture(t);
    const passing = data.run();
    assert.equal(passing.status, 0, passing.stderr);
    const candidate = JSON.parse(readFileSync(path.join(data.root, 'coverage/js-snapshot.json'), 'utf8'));
    assert.deepEqual(candidate.metrics.lines, { total: 2, covered: 2 });
    data.report[path.join(data.root, 'resources/js/example.js')].s[0] = 0;
    data.write('coverage/coverage-final.json', data.report);
    // An old passing snapshot must not hide a newly measured regression.
    data.write('coverage/js-snapshot.json', data.baseline);
    const failing = data.run();
    assert.equal(failing.status, 1, failing.stderr);
    assert.match(failing.stderr, /previously covered lines 1 is uncovered/);
});

test('the npm coverage check rejects missing measurements despite a stale passing snapshot', t => {
    const data = fixture(t);
    data.write('coverage/js-snapshot.json', data.baseline);
    rmSync(path.join(data.root, 'coverage/coverage-final.json'));
    const failing = data.run();
    assert.equal(failing.status, 1, failing.stderr);
    assert.match(failing.stderr, /ENOENT.*coverage[\\/]coverage-final\.json/);
});
