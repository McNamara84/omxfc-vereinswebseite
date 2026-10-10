import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const helper = fileURLToPath(new URL('../../docker/prepare-deployment.sh', import.meta.url));
const image = 'ghcr.io/mcnamara84/omxfc-vereinswebseite@sha256:' + 'a'.repeat(64);
const fixture = () => {
    const root = fs.mkdtempSync(path.join(os.tmpdir(), 'omxfc-deploy-'));
    fs.mkdirSync(path.join(root, 'bin'));
    fs.writeFileSync(path.join(root, '.env.production'), 'FAKE_SECRET=fixture-only\n');
    fs.writeFileSync(path.join(root, 'compose.yml'), 'services: {}\n');
    // This executable has no Docker access. It records and answers fixture calls.
    fs.writeFileSync(path.join(root, 'bin', 'docker'), `#!/usr/bin/env node
import fs from 'node:fs';
const a=process.argv.slice(2), root=process.env.FIXTURE_ROOT;
fs.appendFileSync(root+'/calls',JSON.stringify(a)+'\\n');
if(a[0]==='inspect') {
 const format=a.at(-1);
 if(format.includes('config_files')) console.log(process.env.FOREIGN_FILE || root+'/compose.yml');
 else if(format.includes('project')) console.log('fixture');
 else if(format.includes('.Image')) console.log('sha256:'+'b'.repeat(64));
 else if(format.includes('/var/www/html')) console.log('fixture_app_data_old');
 else if(format.includes('/data')) console.log('/data');
} else if(a[0]==='volume') console.log(a.at(-1).includes('compose.volume') ? 'app_data' : 'fixture');
else if(a[0]==='compose') {
 if(a.includes('--quiet') && a.includes('ps')) console.log('container-'+a.at(-1));
 else if(a.includes('--images')) console.log(process.env.OMXFC_APP_IMAGE);
 else if(a.includes('config') && !a.includes('--quiet')) console.log('services: {}');
 else if(a.includes('exec')) console.log('-- fixture database backup');
} else if(a[0]==='run') {
 const mount=a[a.indexOf('--mount')+1];
 const dir=mount.split(',')[1].slice('source='.length);
 fs.writeFileSync(dir+'/typesense-data.tar.gz','fixture archive');
}
`, { mode: 0o755 });
    return root;
};
const run = (root, extra = {}, backup = false) => spawnSync('bash', ['-c', 'source "$DEPLOY_HELPER"' + (backup ? '; backup_deployment_data' : '')], {
    cwd: root, encoding: 'utf8', env: {
        ...process.env, PATH: path.join(root, 'bin') + ':' + process.env.PATH,
        DEPLOY_HELPER: helper, FIXTURE_ROOT: root,
        OMXFC_APP_IMAGE: image, OMXFC_TYPESENSE_IMAGE: image, OMXFC_NGINX_IMAGE: image, ...extra,
    },
});

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
