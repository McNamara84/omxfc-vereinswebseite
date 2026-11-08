import test from 'node:test';
import assert from 'node:assert/strict';
import { mkdtempSync, mkdirSync, writeFileSync, rmSync, readFileSync } from 'node:fs';
import path from 'node:path';
import { findDeprecations, runChecked } from '../../tools/testing/check-deprecations.mjs';

const examples = JSON.parse(readFileSync(new URL('fixtures/deprecations.json', import.meta.url), 'utf8'));

function fixture(t) {
    const directory = path.resolve('.tmp');
    mkdirSync(directory, { recursive: true });
    const root = mkdtempSync(path.join(directory, 'deprecation check '));
    t.after(() => {
        assert.equal(path.dirname(root), directory);
        rmSync(root, { recursive: true, force: true });
    });
    return root;
}

async function check(args, options = {}) {
    let stdout = '', stderr = '';
    const env = { ...process.env, ...options.env };
    delete env.NODE_TEST_CONTEXT;
    const code = await runChecked(args, {
        ...options, env,
        stdout: { write: value => { stdout += value; } },
        stderr: { write: value => { stderr += value; } },
    });
    return { code, stdout, stderr };
}

test('recognizes npm, Node, Vite and browser deprecation warning formats with ANSI and CRLF', () => {
    for (const warning of examples.warnings) assert.equal(findDeprecations(`\u001b[33m${warning}\u001b[0m\r\n at stack:1\r\n`).length, 1, warning);
    assert.deepEqual(findDeprecations('DEPRECATED old option\nDEPRECATED old option'), ['DEPRECATED old option']);
});

test('permits ordinary warnings and test titles or data mentioning deprecated APIs', () => {
    assert.deepEqual(findDeprecations(examples.normal.join('\n')), []);
});

test('checked processes forward both streams, preserve Node options and allow normal warnings', async t => {
    const root = fixture(t);
    const filename = path.join(root, 'ordinary.mjs');
    writeFileSync(filename, examples.ordinary);
    const result = await check([filename], { env: { NODE_OPTIONS: '--conditions=deprecation-check' } });
    assert.equal(result.code, 0, result.stderr);
    assert.match(result.stdout, /ordinary stdout/);
    assert.match(result.stderr, /ordinary warning/);
    assert.match(result.stdout, /--conditions=deprecation-check/);
});

test('runtime deprecations in the main process fail with their source', async t => {
    const root = fixture(t);
    const filename = path.join(root, 'runtime.mjs');
    writeFileSync(filename, examples.runtime);
    const result = await check([filename]);
    assert.equal(result.code, 1);
    assert.match(result.stderr, /DeprecationWarning: fixture runtime/);
    assert.match(result.stderr, /runtime\.mjs/);
});

test('a split warning without a final newline fails even when the child exits successfully', async t => {
    const root = fixture(t);
    const filename = path.join(root, 'split.mjs');
    writeFileSync(filename, examples.split);
    const result = await check([filename]);
    assert.equal(result.code, 1);
    assert.match(result.stderr, /Deprecation check failed/);
    assert.match(result.stdout, /DEPRECATED split warning/);
});

test('ordinary child failures keep their exit code and missing scripts fail', async t => {
    const root = fixture(t);
    const filename = path.join(root, 'failure.mjs');
    writeFileSync(filename, 'process.exitCode = 7;');
    assert.equal((await check([filename])).code, 7);
    assert.equal((await check([path.join(root, 'missing.mjs')])).code, 1);
    await assert.rejects(() => runChecked([]), /Missing Node script/);
});

test('deprecations during Vitest configuration and inside a worker reach the check', async t => {
    const root = fixture(t);
    const config = path.join(root, 'vitest.config.mjs');
    const vitest = path.resolve('node_modules/vitest/vitest.mjs');
    writeFileSync(path.join(root, 'worker.test.js'), examples.worker);
    writeFileSync(config, `export default { test: { root: ${JSON.stringify(root)}, environment: 'node', maxWorkers: 1 } };`);
    const worker = await check([vitest, 'run', '--config', config]);
    assert.equal(worker.code, 1);
    assert.match(worker.stderr, /fixture worker/);
    writeFileSync(config, examples.config);
    const startup = await check([vitest, 'run', '--config', config]);
    assert.equal(startup.code, 1);
    assert.match(startup.stderr, /fixture config/);
});
