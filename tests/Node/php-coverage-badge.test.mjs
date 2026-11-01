import test from 'node:test';
import assert from 'node:assert/strict';
import { createSnapshot } from '../../tools/testing/coverage.mjs';
import { phpCoverageBadge } from '../../tools/testing/php-coverage-badge.mjs';

function snapshot(covered, total) {
    return createSnapshot('php', 'fixture', { 'app/example.php': {
        hash: 'a'.repeat(64),
        units: { lines: Object.fromEntries(Array.from({ length: total }, (_, index) => [index + 1, index < covered])), functions: {} },
        metrics: { lines: { covered, total }, functions: { covered: 0, total: 0 } },
    } });
}

test('the badge displays measured line coverage and an accessible label', () => {
    const badge = phpCoverageBadge(snapshot(2, 3));
    assert.match(badge, /aria-label="PHP Coverage: 66\.7%"/);
    assert.match(badge, /<title>PHP Coverage: 66\.7%<\/title>/);
    assert.match(badge, /fill="#dfb317"/);
    assert.match(phpCoverageBadge(snapshot(10, 10)), /PHP Coverage: 100\.0%/);
    assert.match(phpCoverageBadge(snapshot(0, 3)), /PHP Coverage: 0\.0%/);
});

test('empty coverage is unavailable and invalid snapshots cannot produce a success badge', () => {
    assert.match(phpCoverageBadge(snapshot(0, 0)), /PHP Coverage: n\/a/);
    assert.throws(() => phpCoverageBadge(null), /Invalid/);
    const broken = snapshot(1, 2);
    broken.metrics.lines.covered = 2;
    assert.throws(() => phpCoverageBadge(broken), /Inconsistent/);
});
