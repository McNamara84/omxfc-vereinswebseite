import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const workflows = [
    ['phpunit.yml', 7],
    ['playwright.yml', 1],
    ['mutation.yml', 1],
    ['tia-baseline.yml', 1],
    ['performance-benchmark.yml', 1],
    ['dependency-review.yml', 1],
    ['vitest.yml', 1],
    ['php86-compatibility.yml', 1],
];

for (const [filename, expectedCount] of workflows) {
    test(`${filename} updates and verifies every PHP 8.6 runtime before dependent steps`, () => {
        const workflow = readFileSync(new URL(`../../.github/workflows/${filename}`, import.meta.url), 'utf8').replaceAll('\r\n', '\n');
        const steps = workflow.split(/(?=^ {6}- )/m);
        const setups = steps.flatMap((step, index) => /uses: shivammathur\/setup-php@/.test(step)
            && /^ {10}php-version: ['"]8\.6(?:\.\d+)?['"]$/m.test(step) ? [index] : []);

        assert.equal(setups.length, expectedCount, 'All expected PHP 8.6 setup steps must be covered');
        for (const index of setups) {
            const setup = steps[index];
            const environment = setup.match(/^ {8}env:\n((?: {10}[^\n]*\n)+)/m)?.[1] ?? '';
            assert.match(environment, /^ {10}update: ['"]true['"]$/m, 'An installed old PHP patch must be updated');
            assert.match(setup, /^ {10}php-version: ['"]8\.6['"]$/m, 'setup-php selects a release branch, not an exact patch');
            assert.doesNotMatch(setup, /^ {8}(?:if|continue-on-error):/m, 'PHP provisioning must not be optional');

            const verification = steps[index + 1] ?? '';
            assert.match(verification, /^ {8}run: php tools\/ci\/verify-php86\.php$/m, 'The actual PHP runtime must be checked immediately after setup');
            assert.doesNotMatch(verification, /^ {8}(?:if|continue-on-error):/m, 'A failed runtime check must stop dependent steps');
        }
    });
}

test('scheduled and manual runtime checks cannot cancel required push checks', () => {
    const workflow = readFileSync(new URL('../../.github/workflows/php86-compatibility.yml', import.meta.url), 'utf8').replaceAll('\r\n', '\n');
    const concurrency = workflow.match(/^concurrency:\n {2}group: (.+)\n {2}cancel-in-progress: (.+)$/m);
    assert.ok(concurrency);
    assert.equal(concurrency[2], 'true');
    const groupFor = (eventName, ref) => concurrency[1].replace(/\$\{\{\s*github\.(event_name|ref)\s*\}\}/g, (_, property) => (
        property === 'event_name' ? eventName : ref
    ));
    const push = groupFor('push', 'refs/heads/main');
    assert.doesNotMatch(push, /\$\{\{/);
    for (const eventName of ['schedule', 'workflow_dispatch']) {
        assert.notEqual(push, groupFor(eventName, 'refs/heads/main'), eventName);
    }
    assert.equal(push, groupFor('push', 'refs/heads/main'));
    assert.notEqual(groupFor('workflow_dispatch', 'refs/heads/main'), groupFor('workflow_dispatch', 'refs/heads/review'));
});
