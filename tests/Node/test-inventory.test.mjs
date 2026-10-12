import test from 'node:test';
import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { mkdirSync, mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import path from 'node:path';
import { inventory } from '../../tools/testing/inventory.mjs';

// Keep example declarations outside this test file so the inventory counts only real cases.
const examples = JSON.parse(readFileSync(new URL('fixtures/test-inventory.json', import.meta.url), 'utf8'));

function fixture(t, files) {
    const root = mkdtempSync(path.join(tmpdir(), 'omxfc-test-inventory-'));
    t.after(() => rmSync(root, { recursive: true, force: true }));
    for (const [filename, source] of Object.entries(files)) {
        const target = path.join(root, filename);
        mkdirSync(path.dirname(target), { recursive: true });
        writeFileSync(target, source);
    }
    return root;
}

test('PHP inventory recognizes named tests and adjacent short and qualified Test attributes', t => {
    const root = fixture(t, { 'tests/Unit/ExampleTest.php': examples.attributes });
    assert.deepEqual(inventory(root)[0].methods, ['test_named_case', 'attributed_case', 'qualified_case']);
});

test('inventory retains Pest and browser cases, manual classifications and seeder metadata', t => {
    const root = fixture(t, examples.classifications);
    const entries = Object.fromEntries(inventory(root).map(entry => [entry.file, entry]));
    assert.equal(entries['tests/Feature/ExampleTest.php'].laravel, true);
    assert.equal(entries['tests/Feature/ExampleTest.php'].database, true);
    assert.deepEqual(entries['tests/Feature/ExampleTest.php'].seeders, ['ExampleSeeder']);
    assert.deepEqual(entries['tests/e2e/example.spec.js'].methods, ['a browser case']);
    assert.equal(entries['tests/e2e/homepage-performance.benchmark.js'].runner, 'benchmark');
    assert.equal(entries['tests/Integration/ExampleMariaDbTest.php'].runner, 'mariadb');
});

test('inventory includes test.each and it.each title templates with nested datasets', t => {
    const root = fixture(t, { 'tests/Vitest/Parameterized.test.js': examples.parameterized });
    const [entry] = inventory(root);
    assert.equal(entry.runner, 'vitest');
    assert.deepEqual(entry.methods, ['nested case %s', 'multiline case %s', 'spaced case $value', "an 'ordinary' case"]);
});

test('the CodeQL repeated-attribute input finishes within a bounded subprocess', t => {
    const repeated = (String.raw`#[\\\]` + ' ').repeat(10000);
    const root = fixture(t, { 'tests/Unit/RepeatedTest.php': `${repeated}\n${examples.backtrackingSuffix}` });
    const moduleUrl = new URL('../../tools/testing/inventory.mjs', import.meta.url).href;
    const script = `import { inventory } from ${JSON.stringify(moduleUrl)}; console.log(JSON.stringify(inventory(process.argv[1])));`;
    const result = JSON.parse(execFileSync(process.execPath, ['--input-type=module', '-e', script, root], { encoding: 'utf8', timeout: 5000, windowsHide: true }));
    assert.deepEqual(result[0].methods, ['test_valid_case']);
});
