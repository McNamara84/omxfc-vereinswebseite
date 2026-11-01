import test from 'node:test';
import assert from 'node:assert/strict';
import { mkdtempSync, mkdirSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import path from 'node:path';
import { spawn } from 'node:child_process';
import { classifyFailure, createStages, ProbeFailure, runStages, summarize, verifyResult } from '../../tools/ci/php86-compatibility.mjs';

const stageNames = ['runtime', 'dependencies', 'application', 'php', 'mariadb', 'pest_browser', 'chromium', 'firefox'];

function temporaryDirectory(t) {
    const directory = mkdtempSync(path.join(tmpdir(), 'omxfc-php86-test-'));
    t.after(() => {
        const target = path.resolve(directory);
        if (!target.startsWith(path.resolve(tmpdir()) + path.sep) || !path.basename(target).startsWith('omxfc-php86-test-')) throw new Error('Unexpected fixture path');
        rmSync(target, { recursive: true, force: true });
    });
    return directory;
}

for (const failingIndex of [1, 3, 4, 5, 6]) {
    test(`a failure in ${stageNames[failingIndex]} prevents every dependent stage from executing`, async () => {
        const calls = [];
        const result = await runStages(stageNames.map((id, index) => ({ id, name: id, run: async () => {
            calls.push(id);
            if (index === failingIndex) throw new ProbeFailure('incompatible', 'Concrete compatibility failure');
        } })));
        assert.deepEqual(calls, stageNames.slice(0, failingIndex + 1));
        assert.equal(result.status, 'incompatible');
        assert.equal(result.blocker.id, stageNames[failingIndex]);
        assert.ok(result.stages.slice(failingIndex + 1).every(stage => stage.outcome === 'skipped'));
    });
}

test('only successful execution of every stage reports compatibility', async () => {
    const result = await runStages(stageNames.map(id => ({ id, name: id, run: async () => {} })));
    assert.equal(result.status, 'compatible');
    assert.ok(result.stages.every(stage => stage.outcome === 'success'));
    assert.equal((await runStages([])).status, 'unavailable');
});

test('unknown exceptions and interrupted commands are not compatibility diagnoses', async () => {
    const result = await runStages([{ id: 'runtime', name: 'runtime', run: async () => { throw new Error('Unavailable runner'); } }]);
    assert.equal(result.status, 'unavailable');
    assert.equal(classifyFailure('test', { code: null, signal: 'SIGKILL', output: 'FAIL' }), 'unavailable');
    assert.equal(classifyFailure('test', { code: 124, output: 'FAIL' }), 'unavailable');
    assert.equal(classifyFailure('test', { code: 1, output: '' }), 'unavailable');
});

test('successful stages cannot hide missing or damaged lockfile integrity evidence', () => {
    const result = { status: 'compatible', stages: [] };
    const metadata = { packagesUnchanged: true, packageCount: 201, php: '8.6.0RC4', originalLockSha256: 'a'.repeat(64), packagesSha256: 'b'.repeat(64), originalConstraint: '~8.5.0', probeConstraint: '~8.5.0 || ~8.6.0' };
    assert.equal(verifyResult(result, metadata).status, 'compatible');
    for (const candidate of [undefined, {}, { ...metadata, packagesUnchanged: false }, { ...metadata, php: '8.5.12' }, { ...metadata, packagesSha256: '' }]) {
        assert.equal(verifyResult(result, candidate).status, 'unavailable');
    }
});

test('actual PHP dependency and test errors are distinguished from download failures', () => {
    assert.equal(classifyFailure('composer', { code: 2, output: 'vendor/package requires php ~8.5.0 -> your php version (8.6.0) does not satisfy that requirement.' }), 'incompatible');
    assert.equal(classifyFailure('composer', { code: 2, output: 'curl error 6: Could not resolve host: repo.packagist.org' }), 'unavailable');
    assert.equal(classifyFailure('test', { code: 1, output: 'Tests: 1 failed, 15 passed' }), 'incompatible');
    assert.equal(classifyFailure('application', { code: 255, output: 'PHP Fatal error: Uncaught TypeError: incompatible signature' }), 'incompatible');
    assert.equal(classifyFailure('test', { code: 1, output: 'Error: browser download timed out' }), 'unavailable');
});

test('the real stage definitions defer MariaDB and browser provisioning until their stage', () => {
    const calls = [];
    const stages = createStages('/unused', '/unused', (...args) => { calls.push(args); }, {}, 'unused');
    assert.deepEqual(stages.map(stage => stage.id), stageNames);
    assert.deepEqual(calls, []);
});

test('PHP workers receive the in-memory database and later browser stages regain their file database', async () => {
    const env = { DB_DATABASE: '/probe/browser.sqlite' };
    const databases = [];
    const stages = createStages('/unused', '/unused', async () => { databases.push(env.DB_DATABASE); }, env, 'unused');
    await stages.find(stage => stage.id === 'php').run();
    assert.ok(databases.length > 0);
    assert.ok(databases.every(database => database === ':memory:'));
    assert.equal(env.DB_DATABASE, '/probe/browser.sqlite');
});

test('each real MariaDB stage invocation exports its own database to independent PHP workers', async () => {
    const env = { DB_CONNECTION: 'sqlite', DB_DATABASE: '/probe/browser.sqlite' };
    const databases = [];
    const execute = async (bin, args) => {
        if (bin === 'docker' && args[0] === 'port') return '127.0.0.1:45678\n';
        if (bin === 'php' && args.includes('--configuration')) databases.push([args[args.indexOf('--configuration') + 1], env.DB_DATABASE, env.DB_CONNECTION]);
        return '';
    };
    const stages = createStages(process.cwd(), '/unused', execute, env, 'unused');
    await stages.find(stage => stage.id === 'mariadb').run();
    assert.deepEqual(databases, [
        ['phpunit.mariadb.xml', 'omxfc_maddraxikon_test', 'mysql'],
        ['phpunit.rpg-mariadb.xml', 'omxfc_rpg_test', 'mysql'],
        ['phpunit.veranstaltungs-baxx-mariadb.xml', 'omxfc_veranstaltungen_test', 'mysql'],
    ]);
    assert.deepEqual(env, { DB_CONNECTION: 'sqlite', DB_DATABASE: '/probe/browser.sqlite' });
});

test('summary escapes diagnostics and distinguishes technical compatibility from production approval', () => {
    const summary = summarize({ status: 'incompatible', checkedAt: '2026-11-01', stages: [{ name: 'PHP | Tests', outcome: 'failure' }], blocker: { name: 'PHP', message: '<script>alert(1)</script> | `unsafe`' } }, { originalConstraint: '~8.5.0', probeConstraint: '~8.5.0 || ~8.6.0', packagesUnchanged: true, packageCount: 200 });
    assert.match(summary, /nicht kompatibel/);
    assert.match(summary, /bestätigt \(200 Pakete\)/);
    assert.match(summary, /produktive PHP-8.5-Freigabe bleibt bestehen/);
    assert.ok(!summary.includes('<script>'));
    assert.match(summary, /&lt;script&gt;/);
    assert.match(summary, /PHP &#124; Tests/);
});

test('the executable probe reports failed PHP provisioning with exit zero and skips all expensive commands', async t => {
    const directory = temporaryDirectory(t);
    const checkout = path.join(directory, 'checkout');
    const reports = path.join(directory, 'reports');
    const output = path.join(directory, 'outputs');
    const summary = path.join(directory, 'summary');
    mkdirSync(checkout);
    writeFileSync(output, '');
    writeFileSync(summary, '');
    const env = { ...process.env, PHP_SETUP_OUTCOME: 'failure', GITHUB_OUTPUT: output, GITHUB_STEP_SUMMARY: summary };
    delete env.NODE_TEST_CONTEXT;
    let diagnostic = '';
    const code = await new Promise((resolve, reject) => {
        const child = spawn(process.execPath, ['tools/ci/php86-compatibility.mjs', checkout, reports], { env, windowsHide: true, stdio: ['ignore', 'pipe', 'pipe'] });
        child.stdout.on('data', data => { diagnostic += data; });
        child.stderr.on('data', data => { diagnostic += data; });
        child.once('error', reject);
        child.once('exit', resolve);
    });
    assert.equal(code, 0, diagnostic);
    const result = JSON.parse(readFileSync(path.join(reports, 'result.json'), 'utf8'));
    assert.equal(result.status, 'unavailable');
    assert.equal(result.stages[0].outcome, 'failure');
    assert.ok(result.stages.slice(1).every(stage => stage.outcome === 'skipped'));
    assert.match(readFileSync(output, 'utf8'), /label=nicht prüfbar/);
    assert.match(readFileSync(summary, 'utf8'), /PHP 8.6: nicht prüfbar/);
    assert.ok(!diagnostic.includes('> docker'));
    assert.ok(!diagnostic.includes('> npm'));
});
