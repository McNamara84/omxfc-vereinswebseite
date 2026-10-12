import test from 'node:test';
import assert from 'node:assert/strict';
import { createSnapshot, compareCoverage, mergeSnapshots, validateSnapshot } from '../../tools/testing/coverage.mjs';

function snapshot(units = { 10: true, 11: false }, filename = 'app/example.php', hash = 'a'.repeat(64)) {
    const file = { hash, units: { lines: units, functions: { example: true } }, metrics: { lines: { total: Object.keys(units).length, covered: Object.values(units).filter(Boolean).length }, functions: { total: 1, covered: 1 } } };
    return createSnapshot('php', 'fixture', { [filename]: file });
}

test('identical and improved coverage pass', () => {
    assert.deepEqual(compareCoverage(snapshot(), snapshot()), []);
    assert.deepEqual(compareCoverage(snapshot(), snapshot({ 10: true, 11: true })), []);
});

test('a lost covered line cannot be hidden by covering another line', () => {
    const regressions = compareCoverage(snapshot(), snapshot({ 10: false, 11: true }));
    assert.ok(regressions.some(message => message.includes('previously covered lines 10')));
});

test('coverage losses in one file cannot be hidden by gains elsewhere', () => {
    const baseline = mergeSnapshots([snapshot({ 10: true, 11: true }), snapshot({ 10: false, 11: false }, 'app/other.php')]);
    const candidate = mergeSnapshots([snapshot({ 10: true, 11: false }), snapshot({ 10: true, 11: true }, 'app/other.php')]);
    assert.ok(compareCoverage(baseline, candidate).some(message => message.includes('app/example.php: lines coverage decreased')));
});

test('missing reports, missing files and incompatible profiles fail', () => {
    assert.throws(() => validateSnapshot(null), /Invalid/);
    assert.throws(() => createSnapshot('php', 'fixture', {}), /no source files/);
    assert.ok(compareCoverage(snapshot(), snapshot(undefined, 'app/other.php')).some(message => message.includes('missing')));
    const candidate = snapshot(); candidate.profile = 'other';
    assert.throws(() => compareCoverage(snapshot(), candidate), /not comparable/);
});

test('raw fractions detect losses hidden by percentage rounding', () => {
    const units = Object.fromEntries(Array.from({ length: 10001 }, (_, index) => [index, true]));
    const baseline = snapshot(units);
    units[0] = false;
    assert.ok(compareCoverage(baseline, snapshot(units)).some(message => message.includes('Total lines')));
});

test('shards merge the union of coverage without double-counting', () => {
    const merged = mergeSnapshots([snapshot(), snapshot({ 10: false, 11: true })]);
    assert.deepEqual(merged.metrics.lines, { total: 2, covered: 2 });
    assert.deepEqual(compareCoverage(snapshot(), merged), []);
    assert.throws(() => mergeSnapshots([snapshot(), snapshot(undefined, undefined, 'b'.repeat(64))]), /different source/);
    assert.throws(() => mergeSnapshots([snapshot(), snapshot({ 10: true })]), /locations differ/);
});

test('inconsistent counters and invalid source paths are rejected', () => {
    const candidate = snapshot(); candidate.metrics.lines.covered++;
    assert.throws(() => validateSnapshot(candidate), /Inconsistent total/);
    assert.throws(() => snapshot(undefined, '../secret.php'), /Invalid coverage source/);
});

test('source existence is checked only after validating all snapshot paths', () => {
    for (const filename of ['../secret.php', '/secret.php', 'C:/secret.php', 'app\\secret.php', 'app/./secret.php', 'app//secret.php', 'app/secret\0.php', '']) {
        const baseline = snapshot();
        baseline.files[filename] = baseline.files['app/example.php'];
        delete baseline.files['app/example.php'];
        assert.throws(() => compareCoverage(baseline, snapshot(), { sourceExists: () => assert.fail('An invalid path reached the filesystem callback') }), /Invalid coverage source/);
    }
});
