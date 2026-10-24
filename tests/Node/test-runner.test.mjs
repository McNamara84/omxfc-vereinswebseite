import test from 'node:test';
import assert from 'node:assert/strict';
import { EventEmitter } from 'node:events';
import { spawn } from 'node:child_process';
import { mkdirSync, mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import path from 'node:path';
import { commandsFor, runCommands } from '../../tools/testing/run.mjs';

const examples = JSON.parse(readFileSync(new URL('fixtures/test-runner.json', import.meta.url), 'utf8'));

async function runNodeFixture(t, failing = false) {
    const root = mkdtempSync(path.join(tmpdir(), 'omxfc-node-runner with spaces-'));
    t.after(() => {
        if (!root.startsWith(path.resolve(tmpdir()) + path.sep) || !path.basename(root).startsWith('omxfc-node-runner with spaces-')) throw new Error('Unexpected temporary fixture path');
        rmSync(root, { recursive: true, force: true });
    });
    const directory = path.join(root, 'tests/Node');
    mkdirSync(directory, { recursive: true });
    writeFileSync(path.join(directory, 'alpha.test.mjs'), examples.alpha);
    writeFileSync(path.join(directory, 'beta.test.mjs'), failing ? examples.failingBeta : examples.beta);
    writeFileSync(path.join(directory, 'ignored.mjs'), examples.ignored);
    const command = commandsFor('all', { runtime: 'host' }).find(entry => entry.args.includes('tests/Node/*.test.mjs'));
    const env = { ...process.env };
    // The fixture starts its own test runner, outside this test's child context.
    delete env.NODE_TEST_CONTEXT;
    let output = '';
    const spawnFn = (executable, args, options) => {
        assert.equal(options.shell, false);
        assert.deepEqual(args, ['--test', 'tests/Node/*.test.mjs']);
        const child = spawn(executable, args, { ...options, cwd: root, stdio: 'pipe' });
        child.stdout.on('data', data => { output += data; });
        child.stderr.on('data', data => { output += data; });
        return child;
    };
    return { code: await runCommands([command], { spawnFn, env }), output };
}

test('the fast scope selects only isolated PHP Unit and retains all JS tests', () => {
    const commands = commandsFor('fast');
    assert.ok(commands[0].args.includes('--testsuite=Unit'));
    assert.equal(commands[0].env.DB_DATABASE, ':memory:');
    assert.ok(commands.some(command => command.args.includes('node_modules/vitest/vitest.mjs')));
    assert.ok(commands.some(command => command.args.includes('tests/Node/release-checks.test.mjs')));
});

test('the complete scope includes all three MariaDB suites and regular browsers', () => {
    const commands = commandsFor('all');
    for (const configuration of ['phpunit.mariadb.xml', 'phpunit.rpg-mariadb.xml', 'phpunit.veranstaltungs-baxx-mariadb.xml']) {
        const command = commands.find(command => command.args.includes(configuration));
        assert.ok(command.args.includes('--fail-on-skipped'));
    }
    assert.ok(commands.some(command => command.args.includes('node_modules/playwright/cli.js') && command.args.includes('browser')));
    assert.ok(commands.some(command => command.args.includes('tests/Browser/ModalBackdropPreviewTest.php')));
});

test('explicit PHP filters are forwarded as arguments and unknown scopes fail', () => {
    assert.ok(commandsFor('unit', { args: ['--filter', 'a name with spaces'] })[0].args.includes('a name with spaces'));
    assert.throws(() => commandsFor('unknown'), /Scope/);
    assert.throws(() => commandsFor('unit', { runtime: 'unknown' }), /TEST_RUNTIME/);
});

test('each browser project gets a separate setup invocation and a fresh server', () => {
    for (const runtime of ['host', 'docker']) {
        const browsers = commandsFor('all', { runtime, ci: false }).filter(command => command.args.includes('node_modules/playwright/cli.js'));
        assert.deepEqual(browsers.map(command => command.args.at(-1)), ['--project=chromium', '--project=firefox', '--project=webkit']);
        for (const command of browsers) {
            assert.equal(command.env.PLAYWRIGHT_WORKERS, '1');
            assert.equal(command.env.PLAYWRIGHT_REUSE_EXISTING_SERVER, '0');
            if (runtime === 'docker') assert.ok(command.args.includes('--rm'));
        }
    }
});

test('the CI browser selection matches the Playwright configuration', () => {
    const browsers = commandsFor('all', { ci: true }).filter(command => command.args.includes('node_modules/playwright/cli.js'));
    assert.deepEqual(browsers.map(command => command.args.at(-1)), ['--project=chromium', '--project=firefox']);
});

test('a failed command stops the sequence and propagates its exit code', async () => {
    let starts = 0;
    const spawnFn = () => { starts++; const child = new EventEmitter(); queueMicrotask(() => child.emit('exit', 7)); return child; };
    assert.equal(await runCommands([{ command: 'php', args: [] }, { command: 'node', args: [] }], { spawnFn }), 7);
    assert.equal(starts, 1);
});

test('the complete Node command discovers every matching fixture without shell expansion', async t => {
    const result = await runNodeFixture(t);
    assert.equal(result.code, 0, result.output);
    assert.match(result.output, /glob fixture alpha/);
    assert.match(result.output, /glob fixture beta/);
    assert.doesNotMatch(result.output, /nonmatching fixture executed/);
});

test('a failure discovered through the literal Node glob propagates to the complete runner', async t => {
    const result = await runNodeFixture(t, true);
    assert.equal(result.code, 1, result.output);
    assert.match(result.output, /glob fixture alpha/);
    assert.match(result.output, /fixture failure/);
});
