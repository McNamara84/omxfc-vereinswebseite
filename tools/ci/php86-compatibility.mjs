import { spawn } from 'node:child_process';
import { randomBytes, randomUUID } from 'node:crypto';
import { appendFileSync, closeSync, mkdirSync, openSync, readFileSync, writeFileSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';
import { setTimeout as delay } from 'node:timers/promises';

export const labels = { compatible: 'kompatibel', incompatible: 'nicht kompatibel', unavailable: 'nicht prüfbar' };

export class ProbeFailure extends Error {
    constructor(status, message) { super(message); this.status = status; }
}

export function classifyFailure(kind, result) {
    if (result.signal || result.code === 124 || result.code === null) return 'unavailable';
    // Network and provisioning errors must never masquerade as PHP incompatibility.
    if (/ENOTFOUND|ECONNRESET|ETIMEDOUT|EAI_AGAIN|curl error|Could not resolve|timed? out|rate limit|Too Many Requests|certificate|Could not open input file|No such file|command not found|Cannot connect to the Docker daemon/i.test(result.output)) return 'unavailable';
    if (kind === 'composer' && /requires php|requires ext-|not compatible with your PHP|not supported by your PHP|does not satisfy that requirement|platform requirements/i.test(result.output)) return 'incompatible';
    if (['test', 'application', 'build'].includes(kind) && /FAIL(?:ED|URES)?|[1-9]\d*\s+failed|[1-9]\d*\s+errors?|Fatal error|Parse error|TypeError|ErrorException|Exception|Assertion|not supported|requires PHP|ERROR|Error:/i.test(result.output)) return 'incompatible';
    return 'unavailable';
}

export async function runStages(stages) {
    const results = [];
    let blocker;
    for (const stage of stages) {
        if (blocker) {
            results.push({ id: stage.id, name: stage.name, outcome: 'skipped', reason: `Voraussetzung ${blocker.name} fehlgeschlagen.` });
            continue;
        }
        const started = Date.now();
        try {
            await stage.run();
            results.push({ id: stage.id, name: stage.name, outcome: 'success', seconds: Math.round((Date.now() - started) / 1000) });
        } catch (error) {
            blocker = { id: stage.id, name: stage.name, status: error instanceof ProbeFailure ? error.status : 'unavailable', message: String(error.message).slice(0, 2500) };
            results.push({ id: stage.id, name: stage.name, outcome: 'failure', seconds: Math.round((Date.now() - started) / 1000), reason: blocker.message });
        }
    }
    return { status: blocker?.status ?? (results.length ? 'compatible' : 'unavailable'), blocker, stages: results };
}

export function verifyResult(result, metadata) {
    if (result.status !== 'compatible') return result;
    const valid = metadata?.packagesUnchanged === true
        && Number.isSafeInteger(metadata.packageCount) && metadata.packageCount > 0
        && /^8\.6\./.test(metadata.php ?? '')
        && /^[a-f0-9]{64}$/.test(metadata.originalLockSha256 ?? '')
        && /^[a-f0-9]{64}$/.test(metadata.packagesSha256 ?? '')
        && typeof metadata.originalConstraint === 'string' && typeof metadata.probeConstraint === 'string';
    return valid ? result : { ...result, status: 'unavailable', blocker: { id: 'integrity', name: 'Paketintegrität', message: 'Vollständige und gültige Nachweise zum unveränderten Paketstand fehlen.' } };
}

export function commandRunner(directory, reports, env) {
    let counter = 0;
    return async (bin, args, { kind = 'setup', timeout = 10 * 60_000, input } = {}) => {
        const log = path.join(reports, `${String(++counter).padStart(2, '0')}-${path.basename(bin)}.log`);
        const descriptor = openSync(log, 'w');
        let output = '';
        console.log(`\n> ${bin} ${args.join(' ')}`);
        try {
            const result = await new Promise(resolve => {
                const child = spawn(bin, args, { cwd: directory, env, shell: false, detached: process.platform !== 'win32', stdio: ['pipe', 'pipe', 'pipe'], windowsHide: true });
                const timer = setTimeout(() => {
                    if (process.platform === 'win32') child.kill('SIGKILL');
                    else { try { process.kill(-child.pid, 'SIGKILL'); } catch {} }
                }, timeout);
                const capture = chunk => {
                    appendFileSync(descriptor, chunk);
                    // Retain enough diagnostics for classification without unbounded memory usage.
                    output = (output + chunk.toString()).slice(-100_000);
                    process.stdout.write(chunk);
                };
                child.stdout.on('data', capture);
                child.stderr.on('data', capture);
                child.once('error', error => { clearTimeout(timer); resolve({ code: null, output: error.message }); });
                child.once('close', (code, signal) => { clearTimeout(timer); resolve({ code, signal, output }); });
                child.stdin.on('error', () => {});
                child.stdin.end(input);
            });
            if (result.code !== 0) throw new ProbeFailure(classifyFailure(kind, result), `${bin}: Exit ${result.code ?? result.signal ?? 'unbekannt'}\n${result.output.slice(-2500)}`);
            return result.output;
        } finally { closeSync(descriptor); }
    };
}

export function createStages(directory, reports, execute, env, container) {
    const php = (...args) => execute('php', args, { kind: 'test', timeout: 35 * 60_000 });
    const playwright = (...args) => execute('node', ['node_modules/playwright/cli.js', ...args], { kind: 'test', timeout: 45 * 60_000 });
    return [
        { id: 'runtime', name: 'PHP 8.6 und Ausgangsintegrität', run: async () => {
            if (process.env.PHP_SETUP_OUTCOME !== 'success') throw new ProbeFailure('unavailable', 'PHP 8.6 konnte nicht bereitgestellt werden.');
            await execute('php', ['-r', 'echo PHP_VERSION, PHP_EOL; if (PHP_MAJOR_VERSION !== 8 || PHP_MINOR_VERSION !== 6) { exit(42); }']);
            await execute('composer', ['validate', '--strict', '--no-check-publish']);
            await execute('php', ['tools/ci/prepare-php86.php', process.env.COMPOSER_BINARY ?? '', path.join(reports, 'metadata.json')]);
        } },
        { id: 'dependencies', name: 'Festgeschriebene Composer-Abhängigkeiten', run: async () => {
            await execute('composer', ['install', '--no-interaction', '--prefer-dist', '--no-progress'], { kind: 'composer', timeout: 15 * 60_000 });
            await execute('composer', ['check-platform-reqs'], { kind: 'composer' });
            await execute('php', ['tools/ci/prepare-php86.php', process.env.COMPOSER_BINARY ?? '', path.join(reports, 'metadata.json'), '--verify']);
        } },
        { id: 'application', name: 'Anwendungsstart und Assets', run: async () => {
            for (const folder of ['storage/framework/cache/data', 'storage/framework/sessions', 'storage/framework/views', 'storage/framework/testing', 'bootstrap/cache']) mkdirSync(path.join(directory, folder), { recursive: true });
            writeFileSync(path.join(directory, '.env'), readFileSync(path.join(directory, '.env.example')));
            writeFileSync(env.DB_DATABASE, '');
            try { await execute('sqlite3', ['--version']); } catch (error) {
                if (!error.message.includes('ENOENT')) throw error;
                await execute('sudo', ['apt-get', 'update']);
                await execute('sudo', ['apt-get', 'install', '-y', '--no-install-recommends', 'sqlite3']);
            }
            await execute('php', ['artisan', 'about', '--only=environment'], { kind: 'application' });
            await execute('php', ['artisan', 'migrate:fresh', '--force'], { kind: 'application' });
            await execute('npm', ['install', '--global', 'npm@12.2.1', '--ignore-scripts']);
            await execute('sh', ['docker/patch-npm-security.sh']);
            await execute('npm', ['ci']);
            await execute('npm', ['run', 'build'], { kind: 'build' });
        } },
        { id: 'php', name: 'PHP-Suiten und Qualitätsregeln', run: async () => {
            const database = env.DB_DATABASE;
            env.DB_DATABASE = ':memory:';
            try {
                await php('vendor/bin/pest', '--compact', '--timeout=1800', `--log-junit=${path.join(reports, 'php-tests.xml')}`);
                for (const script of ['test:types', 'test:rector', 'test:arch', 'test:profanity']) await execute('composer', [script], { kind: 'test', timeout: 15 * 60_000 });
            } finally { env.DB_DATABASE = database; }
        } },
        { id: 'mariadb', name: 'MariaDB und Migrationen', run: async () => {
            const image = readFileSync(path.join(directory, 'docker/mariadb.Dockerfile'), 'utf8').match(/^FROM (mariadb:\S+@sha256:[a-f0-9]{64}) /m)?.[1];
            if (!image) throw new Error('Kein eindeutig gepinntes MariaDB-Image gefunden.');
            await execute('docker', ['run', '-d', '--name', container, '-p', '127.0.0.1::3306', '-e', 'MARIADB_ROOT_PASSWORD=omxfc_root_test_password', '-e', 'MARIADB_USER=omxfc_test', '-e', 'MARIADB_PASSWORD=omxfc_test_password', '-e', 'MARIADB_DATABASE=omxfc_maddraxikon_test', image]);
            let ready = false;
            for (let attempt = 0; attempt < 45; attempt++) {
                try { await execute('docker', ['exec', container, 'mariadb-admin', 'ping', '-h', '127.0.0.1', '-uroot', '-pomxfc_root_test_password'], { timeout: 5000 }); ready = true; break; } catch {}
                await delay(2000);
            }
            if (!ready) throw new ProbeFailure('unavailable', 'MariaDB wurde nicht rechtzeitig bereit.');
            await execute('docker', ['exec', '-i', container, 'mariadb', '-uroot', '-pomxfc_root_test_password'], { input: readFileSync(path.join(directory, 'docker/tests/databases.sql')) });
            const port = (await execute('docker', ['port', container, '3306'])).trim().match(/:(\d+)$/)?.[1];
            if (!port) throw new Error('MariaDB-Testport nicht ermittelbar.');
            const original = { ...env };
            Object.assign(env, { DB_CONNECTION: 'mysql', DB_HOST: '127.0.0.1', DB_PORT: port, DB_DATABASE: 'omxfc_maddraxikon_test', DB_USERNAME: 'omxfc_test', DB_PASSWORD: 'omxfc_test_password' });
            try {
                await execute('php', ['artisan', 'migrate:fresh', '--force'], { kind: 'application' });
                for (const [configuration, database] of [
                    ['phpunit.mariadb.xml', 'omxfc_maddraxikon_test'],
                    ['phpunit.rpg-mariadb.xml', 'omxfc_rpg_test'],
                    ['phpunit.veranstaltungs-baxx-mariadb.xml', 'omxfc_veranstaltungen_test'],
                ]) {
                    env.DB_DATABASE = database;
                    await php('vendor/bin/pest', '--configuration', configuration, '--fail-on-skipped', '--compact');
                }
            } finally {
                for (const key of Object.keys(env)) if (!(key in original)) delete env[key];
                Object.assign(env, original);
            }
        } },
        { id: 'pest_browser', name: 'Pest-Browser-Regression', run: async () => {
            await execute('node', ['node_modules/playwright/cli.js', 'install', '--with-deps', 'chromium'], { timeout: 15 * 60_000 });
            for (const folder of ['tests/Browser/Screenshots/Sliders', 'tests/Browser/Screenshots/ImageDiffView', 'tests/Browser/Traces']) mkdirSync(path.join(directory, folder), { recursive: true });
            await php('vendor/bin/pest', 'tests/Browser/ModalBackdropPreviewTest.php', '--order-by=random', '--random-order-seed=1', '--display-all-issues');
        } },
        { id: 'chromium', name: 'Playwright Chromium', run: () => playwright('test', '--project=chromium') },
        { id: 'firefox', name: 'Playwright Firefox', run: async () => {
            await execute('node', ['node_modules/playwright/cli.js', 'install', '--with-deps', 'firefox'], { timeout: 15 * 60_000 });
            await playwright('test', '--project=firefox');
            await execute('php', ['tools/ci/prepare-php86.php', process.env.COMPOSER_BINARY ?? '', path.join(reports, 'metadata.json'), '--verify']);
        } },
    ];
}

const safe = value => String(value ?? '').replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;').replaceAll('|', '&#124;').replaceAll('`', '&#96;');

export function summarize(result, metadata = {}) {
    const lines = [
        `## PHP 8.6: ${labels[result.status] ?? labels.unavailable}`, '',
        `Commit: ${safe(process.env.GITHUB_SHA ?? 'lokal')} · Ereignis: ${safe(process.env.GITHUB_EVENT_NAME ?? 'lokal')} · Prüfung: ${safe(result.checkedAt)}`, '',
        `PHP: ${safe(metadata.php ?? 'nicht verfügbar')} · Composer: ${safe(metadata.composer ?? 'nicht verfügbar')}`, '',
        `Produktive PHP-Vorgabe: ${safe(metadata.originalConstraint ?? '~8.5.0')} · Isolierte Probe: ${safe(metadata.probeConstraint ?? 'nicht vorbereitet')}`, '',
        `Original-Lockfile SHA-256: ${safe(metadata.originalLockSha256 ?? 'nicht erfasst')}`, '',
        `Unveränderte Paketmetadaten: ${metadata.packagesUnchanged === true ? `bestätigt (${metadata.packageCount} Pakete)` : 'nicht bestätigt'}`, '',
        '| Stufe | Ergebnis |', '| --- | --- |',
        ...result.stages.map(stage => `| ${safe(stage.name)} | ${stage.outcome === 'success' ? 'erfolgreich' : stage.outcome === 'failure' ? 'fehlgeschlagen' : `übersprungen: ${safe(stage.reason)}`} |`), '',
        '„Kompatibel“ bezeichnet ausschließlich den technischen Probelauf mit den gesperrten Paketversionen und isoliert erweiterter Root-PHP-Vorgabe. Die produktive PHP-8.5-Freigabe bleibt bestehen.', '',
    ];
    if (result.blocker) lines.push(`### Erster Blocker: ${safe(result.blocker.name)}`, '', `<pre>${safe(result.blocker.message)}</pre>`, '');
    if (process.env.GITHUB_SERVER_URL && process.env.GITHUB_REPOSITORY && process.env.GITHUB_RUN_ID) lines.push(`[Logs und Diagnoseartefakte](${process.env.GITHUB_SERVER_URL}/${process.env.GITHUB_REPOSITORY}/actions/runs/${process.env.GITHUB_RUN_ID})`, '');
    return lines.join('\n');
}

async function main() {
    const [directoryArg, reportsArg] = process.argv.slice(2);
    if (!directoryArg || !reportsArg) throw new Error('Usage: php86-compatibility.mjs <isolated checkout> <report directory>');
    const directory = path.resolve(directoryArg);
    const reports = path.resolve(reportsArg);
    const sourceRoot = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
    if (directory === sourceRoot) throw new Error('An isolated working copy is required.');
    mkdirSync(reports, { recursive: true });
    const container = `omxfc-php86-${randomBytes(8).toString('hex')}`;
    const env = { ...process.env, CI: 'true', APP_ENV: 'testing', APP_DEBUG: 'false', APP_KEY: `base64:${randomBytes(32).toString('base64')}`, DB_CONNECTION: 'sqlite', DB_DATABASE: path.join(directory, 'database/php86.sqlite'), CACHE_STORE: 'array', SESSION_DRIVER: 'array', QUEUE_CONNECTION: 'sync', SCOUT_DRIVER: 'null', MADDRAXIKON_IDENTITY_HMAC_PEPPERS: 'test-v1:0123456789abcdef0123456789abcdef', PLAYWRIGHT_USE_DOCKER: '0', PLAYWRIGHT_REUSE_EXISTING_SERVER: '0', PLAYWRIGHT_WORKERS: '1' };
    const execute = commandRunner(directory, reports, env);
    let result = await runStages(createStages(directory, reports, execute, env, container));
    result.checkedAt = new Date().toISOString();
    // Cleanup is needed only if the database stage actually started its container.
    if (result.stages.find(stage => stage.id === 'mariadb')?.outcome !== 'skipped') {
        try { await execute('docker', ['rm', '-f', container], { timeout: 30_000 }); } catch {}
    }
    let metadata = {};
    try { metadata = JSON.parse(readFileSync(path.join(reports, 'metadata.json'), 'utf8')); } catch {}
    result = verifyResult(result, metadata);
    const summary = summarize(result, metadata);
    writeFileSync(path.join(reports, 'result.json'), JSON.stringify({ ...result, metadata }, null, 2) + '\n');
    writeFileSync(path.join(reports, 'summary.md'), summary);
    if (process.env.GITHUB_STEP_SUMMARY) appendFileSync(process.env.GITHUB_STEP_SUMMARY, summary);
    if (process.env.GITHUB_OUTPUT) {
        const delimiter = randomUUID();
        appendFileSync(process.env.GITHUB_OUTPUT, `status=${result.status}\nlabel=${labels[result.status]}\nsummary<<${delimiter}\n${summary}\n${delimiter}\n`);
    }
    console.log(`PHP 8.6: ${labels[result.status]}`);
    // Reporting an incompatibility is a successful execution of this advisory check.
}

if (process.argv[1] && import.meta.url === pathToFileURL(path.resolve(process.argv[1])).href) {
    main().catch(error => { console.error(error); process.exitCode = 1; });
}
