import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

test('merge protection requires every PHP, JavaScript and browser job independently of the aggregate gate', () => {
    const policy = JSON.parse(readFileSync(new URL('../../.github/required-status-checks.json', import.meta.url), 'utf8'));
    const expected = new Set(['CI erforderlich']);
    for (const filename of ['phpunit.yml', 'vitest.yml', 'playwright.yml']) {
        const workflow = readFileSync(new URL(`../../.github/workflows/${filename}`, import.meta.url), 'utf8');
        const jobs = workflow.split(/^jobs:\r?$/m)[1].split(/(?=^  [\w-]+:\r?$)/m);
        for (const job of jobs) {
            const name = job.match(/^    name: (.+)\r?$/m)?.[1].trim();
            if (!name) continue;
            // Badge import and publication run only after merging to main.
            if (/^    if: github.event_name == 'push' && github.ref == 'refs\/heads\/main'\r?$/m.test(job)) continue;
            const axes = [...job.matchAll(/^        (\w+): \[([^\]]+)\]\r?$/gm)];
            let names = [name];
            for (const [, axis, values] of axes) {
                names = names.flatMap(value => values.split(',').map(item => value.replaceAll(`\${{ matrix.${axis} }}`, item.trim())));
            }
            for (const value of names) {
                assert.doesNotMatch(value, /\$\{\{/, 'Every matrix context must be resolved');
                assert.ok(!expected.has(value), `Duplicate required context: ${value}`);
                expected.add(value);
            }
        }
    }
    assert.deepEqual(new Set(policy.map(check => check.context)), expected);
    assert.equal(policy.length, expected.size);
    assert.ok(policy.every(check => check.integration_id === 15368), 'Required contexts must come from GitHub Actions');
});
