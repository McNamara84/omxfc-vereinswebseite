import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const helper = fileURLToPath(new URL('../../docker/prepare-deployment.sh', import.meta.url));
const dockerFixture = fileURLToPath(new URL('./fixtures/deployment-docker.mjs', import.meta.url));
const workflow = fs.readFileSync(new URL('../../.github/workflows/deploy.yml', import.meta.url), 'utf8')
    .replaceAll('\r\n', '\n').split('          script: |\n')[1]
    .split('\n').map(line => line.slice(12)).join('\n')
    .replace('cd /opt/stacks/omxfc', 'cd "$FIXTURE_ROOT"')
    .replaceAll(/printf '%s' '\$\{\{ steps\.helper\.outputs\.[^\n]+\n/g, '')
    .replace('source .deployment/prepare-deployment.sh', 'source "$DEPLOY_HELPER"');
const image = 'ghcr.io/mcnamara84/omxfc-vereinswebseite@sha256:' + 'a'.repeat(64);
const fixture = () => {
    const root = fs.mkdtempSync(path.join(os.tmpdir(), 'omxfc-deploy-'));
    fs.mkdirSync(path.join(root, 'bin'));
    fs.writeFileSync(path.join(root, '.env.production'), 'FAKE_SECRET=fixture-only\n');
    fs.writeFileSync(path.join(root, 'compose.yml'), 'services: {}\n');
    // This executable has no Docker access. It records and answers fixture calls.
    fs.writeFileSync(path.join(root, 'bin', 'docker'), `#!/bin/sh\nexec node "${dockerFixture}" "$@"\n`, { mode: 0o755 });
    fs.writeFileSync(path.join(root, 'bin', 'sleep'), '#!/bin/sh\nexit 0\n', { mode: 0o755 });
    fs.writeFileSync(path.join(root, 'bin', 'curl'), '#!/bin/sh\nexit 0\n', { mode: 0o755 });
    return root;
};
const run = (root, extra = {}, suffix = '') => spawnSync('bash', ['-c', 'source "$DEPLOY_HELPER"' + (suffix === true ? '; backup_deployment_data' : suffix)], {
    cwd: root, encoding: 'utf8', env: {
        ...process.env, PATH: path.join(root, 'bin') + ':' + process.env.PATH,
        DEPLOY_HELPER: helper, FIXTURE_ROOT: root,
        OMXFC_APP_IMAGE: image, OMXFC_TYPESENSE_IMAGE: image, OMXFC_NGINX_IMAGE: image, ...extra,
    },
});

const runWorkflow = (root, extra = {}) => spawnSync('bash', ['-c', workflow
    .replaceAll(/'\$\{\{ needs\.build\.outputs\.[^']+'/g, `'${image}'`)], {
    cwd: root, encoding: 'utf8', timeout: 60_000, env: {
        ...process.env, PATH: path.join(root, 'bin') + ':' + process.env.PATH,
        DEPLOY_HELPER: helper, FIXTURE_ROOT: root, ...extra,
    },
});
const calls = root => fs.readFileSync(root + '/calls', 'utf8').trim().split('\n').map(line => JSON.parse(line));
const rollbackSet = (root, days, oldVolume, newVolume = oldVolume) => {
    const time = new Date(Date.now() - days * 86_400_000);
    const id = time.toISOString().replaceAll(/[-:]/g, '').replace(/\.\d{3}/, '');
    const directory = root + '/.deployment/backups/' + id;
    fs.mkdirSync(directory, { recursive: true });
    fs.writeFileSync(directory + '/retention.meta', `fixture\n${oldVolume}\n${newVolume}\n${Math.floor(time.getTime() / 1000)}\n`);
    fs.writeFileSync(directory + '/database.sql', 'private fixture dump');
    fs.writeFileSync(directory + '/typesense-data.tar.gz', 'fixture archive');
    return { id, directory };
};

test('invalid mutable images fail before any Docker operation or environment change', () => {
    const root = fixture();
    try {
        assert.notEqual(run(root, { OMXFC_APP_IMAGE: 'image:latest' }).status, 0);
        assert.equal(fs.existsSync(root + '/calls'), false);
        assert.equal(fs.readFileSync(root + '/.env.production', 'utf8'), 'FAKE_SECRET=fixture-only\n');
    } finally { fs.rmSync(root, { recursive: true }); }
});

test('foreign Compose files fail before containers or volumes can change', () => {
    const root = fixture();
    try {
        assert.notEqual(run(root, { FOREIGN_FILE: '/another-stack/compose.yml' }).status, 0);
        const calls = fs.readFileSync(root + '/calls', 'utf8');
        assert.doesNotMatch(calls, /"compose"|"run"|"tag"/);
    } finally { fs.rmSync(root, { recursive: true }); }
});

test('a database major upgrade or downgrade cannot happen implicitly', () => {
    for (const version of ['12.3.4-MariaDB', '13.0.3-MariaDB', '14.0.0-MariaDB', 'unknown']) {
        const root = fixture();
        try {
            const result = run(root, { DATABASE_VERSION: version });
            assert.notEqual(result.status, 0);
            assert.match(result.stderr, /separate major-version migration/);
            assert.equal(fs.existsSync(root + '/.deployment/images.compose.yml'), false);
        } finally { fs.rmSync(root, { recursive: true }); }
    }
});

test('storage inside the code volume blocks replacement before environment changes', () => {
    const root = fixture();
    try {
        const result = run(root, { MISSING_STORAGE: '1' });
        assert.notEqual(result.status, 0);
        assert.match(result.stderr, /independent writable.*storage mount/);
        assert.equal(fs.readFileSync(root + '/.env.production', 'utf8'), 'FAKE_SECRET=fixture-only\n');
        assert.doesNotMatch(fs.readFileSync(root + '/calls', 'utf8'), /"stop"|"up"|"run"/);
    } finally { fs.rmSync(root, { recursive: true }); }
});

test('valid preparation preserves project, old code volume, image tags and private backups', () => {
    const root = fixture();
    try {
        const result = run(root, {}, true);
        assert.equal(result.status, 0, result.stderr);
        const dir = root + '/.deployment/backups/' + fs.readdirSync(root + '/.deployment/backups')[0];
        assert.match(fs.readFileSync(dir + '/images.yml', 'utf8'), /name: fixture_app_data_old/);
        assert.equal(fs.statSync(dir + '/environment').mode & 0o777, 0o600);
        assert.ok(fs.statSync(dir + '/database.sql').size > 0);
        assert.ok(fs.statSync(dir + '/typesense-data.tar.gz').size > 0);
        const env = fs.readFileSync(root + '/.env.production', 'utf8');
        assert.match(env, /COMPOSE_PROJECT_NAME=fixture/);
        assert.match(env, /OMXFC_APP_VOLUME=fixture_app_data_a{64}/);
        const calls = fs.readFileSync(root + '/calls', 'utf8');
        assert.doesNotMatch(calls, /"rm"|"prune"|"down"/);
        assert.match(calls, /container-typesense:ro/);
        assert.doesNotMatch(result.stdout, /FAKE_SECRET/);
    } finally { fs.rmSync(root, { recursive: true }); }
});

test('successful workflow releases maintenance only after all health checks', () => {
    const root = fixture();
    try {
        const result = runWorkflow(root);
        assert.equal(result.status, 0, result.stderr);
        const state = JSON.parse(fs.readFileSync(root + '/state.json', 'utf8'));
        assert.equal(state.maintenance, false);
        assert.equal(state.paused, false);
        assert.ok(Object.values(state.running).every(Boolean));
        const recorded = calls(root);
        const health = recorded.findIndex(args => args.includes('wget'));
        const up = recorded.findIndex(args => args.join(' ').includes('php artisan up'));
        assert.ok(health >= 0 && up > health);
        assert.ok(recorded[health].includes('http://127.0.0.1/up'));
    } finally { fs.rmSync(root, { recursive: true }); }
});

for (const operation of ['pull', 'queue-stop', 'maintenance', 'app-stop', 'database-backup', 'typesense-backup', 'infrastructure', 'app-start', 'nginx-start', 'database-ready', 'migration', 'cache', 'index', 'workers', 'schedule', 'health']) {
    test(`failure during ${operation} restores service availability and retains failure status`, () => {
        const root = fixture();
        try {
            const result = runWorkflow(root, { FAIL_OPERATION: operation });
            assert.notEqual(result.status, 0);
            assert.match(result.stderr, /Previous application restored/, result.stderr);
            const state = JSON.parse(fs.readFileSync(root + '/state.json', 'utf8'));
            assert.equal(state.maintenance, false);
            assert.equal(state.paused, false);
            assert.ok(Object.values(state.running).every(Boolean));
            const restored = fs.readFileSync(root + '/.env.production', 'utf8');
            assert.match(restored, /FAKE_SECRET=fixture-only/);
            assert.doesNotMatch(result.stdout + result.stderr, /FAKE_SECRET/);
            const recorded = calls(root);
            assert.equal(recorded.some(args => args.includes('prune') || args.includes('rm')), false);
            if (['infrastructure', 'app-start', 'nginx-start', 'database-ready', 'migration', 'cache', 'index', 'workers', 'schedule', 'health'].includes(operation)) {
                assert.equal(fs.readFileSync(root + '/restored-database.sql', 'utf8'), '-- fixture database backup\n');
                assert.match(restored, /COMPOSE_FILE=.*recovered\.compose\.yml/);
                assert.ok(recorded.some(args => args.includes('-xzf')));
                const recovery = recorded.filter(args => args.includes('up') && args.some(arg => arg.endsWith('/recovered.images.yml')));
                assert.ok(recovery.length >= 3);
                assert.ok(recovery.every(args => args.some(arg => arg.endsWith('/recovered.volumes.yml'))));
                assert.ok(recovery.every(args => args.every(arg => !arg.includes('/backups/'))));
            } else {
                assert.equal(restored, 'FAKE_SECRET=fixture-only\n');
                assert.equal(fs.existsSync(root + '/restored-database.sql'), false);
            }
        } finally { fs.rmSync(root, { recursive: true }); }
    });
}

test('expired tags, orphan code volumes and private dumps are removed; recent sets and shared volumes survive', () => {
    const root = fixture();
    try {
        const expired = rollbackSet(root, 9, 'fixture_app_data_expired', 'fixture_app_data_orphan');
        const shared = rollbackSet(root, 8, 'fixture_app_data_shared');
        const recent = rollbackSet(root, 2, 'fixture_app_data_shared');
        const result = run(root, {}, '; cleanup_deployment_retention');
        assert.equal(result.status, 0, result.stderr);
        assert.equal(fs.existsSync(expired.directory), false);
        assert.equal(fs.existsSync(shared.directory), false);
        assert.equal(fs.existsSync(recent.directory), true);
        const recorded = calls(root);
        const removedVolumes = recorded.filter(args => args[0] === 'volume' && args[1] === 'rm').map(args => args[2]);
        assert.deepEqual(removedVolumes, ['fixture_app_data_expired', 'fixture_app_data_orphan']);
        const removedTags = recorded.filter(args => args[0] === 'image' && args[1] === 'rm').map(args => args[2]);
        assert.equal(removedTags.length, 12);
        assert.ok(removedTags.every(tag => tag.startsWith('omxfc-rollback:' + expired.id) || tag.startsWith('omxfc-rollback:' + shared.id)));
        assert.equal(recorded.some(args => args.includes('prune') || args.includes('--force')), false);
    } finally { fs.rmSync(root, { recursive: true }); }
});

test('retention preserves a code volume used by a stopped container and an active rollback image tag', () => {
    const root = fixture();
    try {
        const expired = rollbackSet(root, 8, 'fixture_app_data_used');
        const activeTag = `omxfc-rollback:${expired.id}-app`;
        const result = run(root, { IN_USE_VOLUME: 'fixture_app_data_used', ACTIVE_ROLLBACK_TAG: activeTag }, '; cleanup_deployment_retention');
        assert.equal(result.status, 0, result.stderr);
        const recorded = calls(root);
        assert.equal(recorded.some(args => args[0] === 'volume' && args[1] === 'rm'), false);
        assert.equal(recorded.some(args => args[0] === 'image' && args[1] === 'rm' && args[2] === activeTag), false);
    } finally { fs.rmSync(root, { recursive: true }); }
});

test('retention keeps an expired backup used as an active Compose input', () => {
    const root = fixture();
    try {
        const active = rollbackSet(root, 8, 'fixture_app_data_manual_rollback');
        fs.writeFileSync(active.directory + '/compose.yml', 'services: {}');
        const result = run(root, { FOREIGN_FILE: active.directory + '/compose.yml' }, '; cleanup_deployment_retention');
        assert.equal(result.status, 0, result.stderr);
        assert.equal(fs.existsSync(active.directory + '/database.sql'), true);
        assert.equal(calls(root).some(args => args[0] === 'image' && args[1] === 'rm'), false);
    } finally { fs.rmSync(root, { recursive: true }); }
});

test('retention rejects foreign volumes and keeps failed cleanup sets for retry', () => {
    for (const extra of [{ FOREIGN_VOLUME: 'fixture_app_data_expired' }, { FAIL_OPERATION: 'retention' }]) {
        const root = fixture();
        try {
            const expired = rollbackSet(root, 8, 'fixture_app_data_expired');
            const result = run(root, extra, '; cleanup_deployment_retention');
            assert.notEqual(result.status, 0);
            assert.equal(fs.existsSync(expired.directory + '/database.sql'), true);
        } finally { fs.rmSync(root, { recursive: true }); }
    }
});

test('retention ignores symlinks and directories not created by this deployment helper', () => {
    const root = fixture();
    const foreign = fs.mkdtempSync(path.join(os.tmpdir(), 'omxfc-foreign-'));
    try {
        const expired = rollbackSet(root, 8, 'fixture_app_data_expired');
        fs.rmSync(expired.directory, { recursive: true });
        fs.writeFileSync(foreign + '/important', 'keep');
        fs.symlinkSync(foreign, expired.directory);
        const unknown = root + '/.deployment/backups/personal-notes';
        fs.mkdirSync(unknown);
        const result = run(root, {}, '; cleanup_deployment_retention');
        assert.equal(result.status, 0, result.stderr);
        assert.equal(fs.readFileSync(foreign + '/important', 'utf8'), 'keep');
        assert.equal(fs.existsSync(unknown), true);
        assert.equal(fs.lstatSync(expired.directory).isSymbolicLink(), true);
    } finally {
        fs.rmSync(root, { recursive: true });
        fs.rmSync(foreign, { recursive: true });
    }
});

test('recognized legacy rollback backups are expired without deleting manual backups', () => {
    const root = fixture();
    try {
        const legacy = rollbackSet(root, 8, 'fixture_app_data_legacy');
        fs.unlinkSync(legacy.directory + '/retention.meta');
        fs.writeFileSync(legacy.directory + '/compose.yml', 'services: {}');
        fs.writeFileSync(legacy.directory + '/environment', 'FIXTURE_ONLY=1');
        fs.writeFileSync(legacy.directory + '/images.yml', 'services:\n' + ['app', 'queue', 'scheduler', 'db', 'typesense', 'nginx']
            .map(service => `  ${service}:\n    image: omxfc-rollback:${legacy.id}-${service}\n`).join('')
            + 'volumes:\n  app_data:\n    name: fixture_app_data_legacy\n');
        const result = run(root, {}, '; cleanup_deployment_retention');
        assert.equal(result.status, 0, result.stderr);
        assert.equal(fs.existsSync(legacy.directory), false);
        assert.ok(calls(root).some(args => args[0] === 'image' && args[1] === 'rm' && args[2] === `omxfc-rollback:${legacy.id}-app`));
    } finally { fs.rmSync(root, { recursive: true }); }
});

test('failed recovery keeps maintenance enabled, reports its backup and preserves the original exit code', () => {
    const root = fixture();
    try {
        const result = runWorkflow(root, { FAIL_OPERATION: 'typesense-backup', FAIL_RECOVERY: '1' });
        assert.equal(result.status, 19);
        assert.match(result.stderr, /AUTOMATIC RECOVERY FAILED.*backups/);
        assert.equal(JSON.parse(fs.readFileSync(root + '/state.json', 'utf8')).maintenance, true);
        assert.equal(calls(root).some(args => args.join(' ').includes('php artisan up')), false);
    } finally { fs.rmSync(root, { recursive: true }); }
});

test('termination during maintenance restores the original services and returns the signal exit status', () => {
    const root = fixture();
    try {
        const result = run(root, {}, '; DEPLOYMENT_SERVICES_TOUCHED=1; $COMPOSE exec -T app php artisan down; $COMPOSE stop queue scheduler; kill -TERM $$');
        assert.equal(result.status, 143, result.stderr);
        assert.match(result.stderr, /Previous application restored/);
        const state = JSON.parse(fs.readFileSync(root + '/state.json', 'utf8'));
        assert.equal(state.maintenance, false);
        assert.ok(Object.values(state.running).every(Boolean));
    } finally { fs.rmSync(root, { recursive: true }); }
});
