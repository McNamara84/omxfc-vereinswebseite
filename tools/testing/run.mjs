import { spawn } from 'node:child_process';
import path from 'node:path';
import { pathToFileURL } from 'node:url';

const sqliteEnvironment = { APP_ENV: 'testing', DB_CONNECTION: 'sqlite', DB_DATABASE: ':memory:', CACHE_STORE: 'array', SESSION_DRIVER: 'array', QUEUE_CONNECTION: 'sync', SCOUT_DRIVER: 'null', MADDRAXIKON_IDENTITY_HMAC_PEPPERS: 'test-v1:0123456789abcdef0123456789abcdef' };
const mariaConfigurations = ['phpunit.mariadb.xml', 'phpunit.rpg-mariadb.xml', 'phpunit.veranstaltungs-baxx-mariadb.xml'];

export function commandsFor(scope, { runtime = 'docker', args = [], node = process.execPath } = {}) {
    if (!['fast', 'all', 'php', 'unit', 'mariadb'].includes(scope)) throw new Error('Scope must be fast, all, php, unit, or mariadb');
    if (!['docker', 'host'].includes(runtime)) throw new Error('TEST_RUNTIME must be docker or host');
    const compose = ['compose', '-f', 'docker-compose.tests.yml'];
    const php = phpArgs => runtime === 'docker'
        ? { command: 'docker', args: [...compose, 'run', '--rm', '-T', '--no-deps', 'php', 'php', ...phpArgs] }
        : { command: process.env.PHP_BINARY ?? 'php', args: phpArgs };
    const commands = [];
    if (runtime === 'docker' && ['all', 'php', 'mariadb'].includes(scope)) commands.push({ command: 'docker', args: [...compose, 'run', '--rm', '-T', '--no-deps', 'assets'] });
    if (['fast', 'all', 'php', 'unit'].includes(scope)) commands.push({ ...php(['vendor/bin/pest', ...(['fast', 'unit'].includes(scope) ? ['--testsuite=Unit'] : []), '--compact', '--timeout=1800', ...args]), env: sqliteEnvironment });
    if (['fast', 'all'].includes(scope)) {
        commands.push({ command: node, args: ['node_modules/vitest/vitest.mjs', 'run', '--config', 'vitest.config.js'] });
        commands.push(scope === 'fast'
            ? { command: node, args: ['--test', 'tests/Node/coverage.test.mjs', 'tests/Node/coverage-baseline.test.mjs', 'tests/Node/test-runner.test.mjs', 'tests/Node/test-inventory.test.mjs', 'tests/Node/release-checks.test.mjs'] }
            : runtime === 'docker'
            ? { command: 'docker', args: [...compose, 'run', '--rm', '-T', '--no-deps', 'node', 'node', '--test', 'tests/Node/*.test.mjs'] }
            : { command: node, args: ['--test', 'tests/Node/*.test.mjs'] });
    }
    if (['all', 'mariadb'].includes(scope)) {
        if (runtime === 'docker') commands.push({ command: 'docker', args: [...compose, 'up', '-d', '--wait', 'db'] });
        for (const configuration of mariaConfigurations) commands.push(php(['vendor/bin/pest', '--configuration', configuration, '--fail-on-skipped', '--compact', ...args]));
    }
    if (scope === 'all') {
        commands.push(php(['vendor/bin/pest', 'tests/Arch.php', '--compact']));
        commands.push(runtime === 'docker'
            ? { command: 'docker', args: [...compose, 'run', '--rm', '-T', '--no-deps', 'browser', 'node', 'node_modules/playwright/cli.js', 'test'], env: { PLAYWRIGHT_WORKERS: '1', PLAYWRIGHT_REUSE_EXISTING_SERVER: '0' } }
            : { command: node, args: ['node_modules/playwright/cli.js', 'test'] });
        commands.push(runtime === 'docker'
            ? { command: 'docker', args: [...compose, 'run', '--rm', '-T', '--no-deps', 'browser', 'php', 'vendor/bin/pest', 'tests/Browser/ModalBackdropPreviewTest.php', '--compact'], env: sqliteEnvironment }
            : php(['vendor/bin/pest', 'tests/Browser/ModalBackdropPreviewTest.php', '--compact']));
    }
    return commands;
}

export async function runCommands(commands, { spawnFn = spawn, env = process.env } = {}) {
    for (const command of commands) {
        console.log(`\n> ${command.command} ${command.args.join(' ')}`);
        const code = await new Promise((resolve, reject) => {
            const child = spawnFn(command.command, command.args, { stdio: 'inherit', windowsHide: true, shell: /\.(bat|cmd)$/i.test(command.command), env: { ...env, ...command.env } });
            child.once('error', reject);
            child.once('exit', (status, signal) => resolve(signal ? 1 : status ?? 1));
        });
        if (code !== 0) return code;
    }
    return 0;
}

if (process.argv[1] && import.meta.url === pathToFileURL(path.resolve(process.argv[1])).href) {
    try {
        const [scope, ...args] = process.argv.slice(2);
        console.log(scope === 'fast' ? 'Development subset: PHP Unit, complete Vitest, portable Node checks. Full deployment, database and browser checks: npm run test:all.' : `Test scope: ${scope}. Required services must be available.`);
        process.exitCode = await runCommands(commandsFor(scope, { runtime: process.env.TEST_RUNTIME ?? 'docker', args }));
    } catch (error) { console.error(error.message); process.exitCode = 1; }
}
