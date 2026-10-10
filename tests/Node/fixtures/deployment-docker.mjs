// No Docker socket or network access: only fixture files and recorded calls.
import fs from 'node:fs';
import { spawnSync } from 'node:child_process';

const args = process.argv.slice(2);
const root = process.env.FIXTURE_ROOT;
const services = ['app', 'queue', 'scheduler', 'db', 'typesense', 'nginx'];
const statePath = root + '/state.json';
const state = fs.existsSync(statePath)
    ? JSON.parse(fs.readFileSync(statePath, 'utf8'))
    : { running: Object.fromEntries(services.map(service => [service, true])), maintenance: false, paused: false };
fs.appendFileSync(root + '/calls', JSON.stringify(args) + '\n');
const save = () => fs.writeFileSync(statePath, JSON.stringify(state));
const fail = (operation) => {
    if (process.env.FAIL_OPERATION === operation && !fs.existsSync(root + '/failure')) {
        fs.writeFileSync(root + '/failure', operation);
        save();
        process.exit(19);
    }
};

if (args[0] === 'inspect') {
    const format = args.at(-1);
    if (format.includes('config_files')) console.log(process.env.FOREIGN_FILE || root + '/compose.yml');
    else if (format.includes('project')) console.log('fixture');
    else if (format.includes('.Config.Image')) console.log(process.env.ACTIVE_ROLLBACK_TAG || process.env.OMXFC_APP_IMAGE);
    else if (format.includes('.Image')) console.log('sha256:' + 'b'.repeat(64));
    else if (format.includes('/var/www/html/storage')) {
        const missing = process.env.MISSING_STORAGE
            || (process.env.SPLIT_STORAGE && format.includes('eq .Destination "/var/www/html/storage"'))
            || (process.env.MISSING_STORAGE_PART && format.includes('/storage/framework'));
        console.log(missing ? '' : 'volume:/var/lib/docker/volumes/fixture_storage/_data');
    }
    else if (format.includes('/var/www/html')) console.log('fixture_app_data_old');
    else if (format.includes('.State.ExitCode')) console.log(process.env.UNCLEAN_DATABASE_STOP ? 'false|137|true' : 'false|0|false');
    else if (format.includes('/var/lib/mysql')) console.log(process.env.MISSING_DATABASE_VOLUME ? '' : (process.env.CURRENT_DATABASE_VOLUME || 'fixture_db_data_old'));
    else if (format.includes('println .Destination')) console.log(process.env.NESTED_DATABASE_MOUNT ? '/var/lib/mysql/nested' : '/var/lib/mysql');
    else if (format.includes('/data')) console.log(process.env.MISSING_TYPESENSE_MOUNT ? '' : (process.env.TYPESENSE_DATA_TARGET || '/data'));
} else if (args[0] === 'volume') {
    if (args[1] === 'inspect') {
        if (/^fixture_database_\d{8}T/.test(args[2]) && !state.createdVolumes?.includes(args[2]) && !process.env.EXISTING_DATABASE_TARGET) process.exit(1);
        if (args.at(-1).includes('compose.volume')) console.log(/database_|db_data_/.test(args[2]) ? 'db_data' : 'app_data');
        else console.log(process.env.FOREIGN_VOLUME === args[2] ? 'another-project' : 'fixture');
    } else if (args[1] === 'rm') {
        fail('retention');
    } else if (args[1] === 'create') {
        (state.createdVolumes ??= []).push(args.at(-1));
        console.log(args.at(-1));
    }
} else if (args[0] === 'compose') {
    const commandIndex = args.findIndex(arg => ['ps', 'config', 'exec', 'stop', 'up', 'start', 'pull', 'logs'].includes(arg));
    const command = args[commandIndex];
    const tail = args.slice(commandIndex + 1);
    const recovery = args.some(arg => arg.endsWith('/images.yml') || arg.endsWith('/recovered.images.yml'));
    if (command === 'ps') {
        const service = tail.at(-1);
        if (tail.includes('--quiet')) console.log(services.includes(service) ? 'container-' + service : 'container-app');
        else if (services.includes(service) && state.running[service]) console.log(service);
    } else if (command === 'config') {
        const images = {
            app: process.env.OMXFC_APP_IMAGE, queue: process.env.OMXFC_APP_IMAGE,
            scheduler: process.env.OMXFC_APP_IMAGE, db: process.env.OMXFC_DATABASE_IMAGE,
            typesense: process.env.OMXFC_TYPESENSE_IMAGE, nginx: process.env.OMXFC_NGINX_IMAGE,
            'init-app-data': 'fixture:legacy-init',
        };
        if (tail.includes('--images')) {
            // Real Compose includes dependencies when a service is selected.
            const service = tail.at(-1);
            console.log([images[service], ...(['app', 'queue', 'scheduler', 'nginx'].includes(service)
                ? [images.db, images.typesense, images['init-app-data']] : [])].join('\n'));
        }
        else if (tail.includes('json')) {
            if (process.env.INVALID_COMPOSE_JSON) console.log('{FAKE_SECRET=fixture-only');
            else {
                if (process.env.CONFIG_IMAGE_MISMATCH) images[process.env.CONFIG_IMAGE_MISMATCH] = 'fixture:unscanned';
                console.log(JSON.stringify({ services: Object.fromEntries(Object.entries(images)
                    .map(([service, image]) => [service, { image, environment: { FAKE_SECRET: 'fixture-only' } }])) }));
            }
        }
        else if (!tail.includes('--quiet')) console.log('services: {}');
    } else if (command === 'pull') fail('pull');
    else if (['stop', 'up', 'start'].includes(command)) {
        for (const service of tail.filter(arg => services.includes(arg))) state.running[service] = command !== 'stop';
        if (command === 'up' && tail.includes('db')) state.databaseUpgraded = !recovery;
        if (!recovery) {
            if (command === 'stop' && tail.includes('queue')) fail('queue-stop');
            if (command === 'stop' && tail.includes('app')) fail('app-stop');
            if (command === 'stop' && tail.includes('db')) fail('database-stop');
            if (command === 'up' && tail.includes('db')) fail('infrastructure');
            if (command === 'up' && tail.includes('app')) fail('app-start');
            if (command === 'up' && tail.includes('nginx')) fail('nginx-start');
            if (command === 'up' && tail.includes('scheduler')) fail('workers');
        } else if (process.env.FAIL_RECOVERY) {
            save();
            process.exit(23);
        }
    } else if (command === 'exec') {
        const joined = tail.join(' ');
        if (joined.includes('SELECT VERSION()')) {
            if (state.databaseUpgraded && process.env.FAIL_OPERATION === 'database-ready') process.exit(19);
            console.log(state.databaseUpgraded ? (process.env.DEPLOYED_DATABASE_VERSION || '13.0.2-MariaDB') : (process.env.DATABASE_VERSION || '13.0.1-MariaDB'));
        }
        else if (joined.includes('SELECT @@datadir')) console.log(process.env.CUSTOM_DATABASE_DIRECTORY || '/var/lib/mysql/');
        else if (joined.includes('innodb_fast_shutdown')) fail('database-flush');
        else if (joined.includes('mariadb-dump')) {
            fail('database-backup');
            console.log('-- fixture database backup');
        } else if (joined.includes('mariadb -uroot') && !joined.includes('SELECT 1')) {
            fs.writeFileSync(root + '/restored-database.sql', fs.readFileSync(0));
        } else if (joined.includes('php artisan')) artisan(joined);
    }
} else if (args[0] === 'run') {
    const mounts = args.filter((arg, index) => args[index - 1] === '--mount');
    const backup = mounts.find(mount => mount.includes('target=/backup'));
    const directory = backup?.split(',').find(part => part.startsWith('source=')).slice(7);
    if (mounts.some(mount => mount.includes('target=/source'))) fail('database-copy');
    if (args.includes('-czf')) {
        fail('typesense-backup');
        fs.writeFileSync(directory + '/typesense-data.tar.gz', 'fixture archive');
    }
} else if (args[0] === 'exec') {
    const joined = args.join(' ');
    const phpIndex = args.indexOf('php');
    if (args[phpIndex + 1] === '-r') {
        // Execute the real validator in PHP, with fixture JSON only. This
        // avoids a test double that silently accepts broken image checks.
        const result = spawnSync('php', args.slice(phpIndex + 1), {
            input: fs.readFileSync(0), encoding: 'utf8',
        });
        process.stdout.write(result.stdout || '');
        process.stderr.write(result.stderr || result.error?.message || '');
        save();
        process.exit(result.status ?? 1);
    }
    else if (joined.includes('SELECT 1')) {
        if (process.env.FAIL_OPERATION === 'database-ready') process.exit(19);
    } else if (joined.includes('php artisan')) artisan(joined);
    else if (args[1] === 'maddrax-nginx' && joined.includes('wget')) fail('health');
} else if (args[0] === 'ps') {
    const filter = args.find(arg => arg.startsWith('volume='));
    if (filter && filter.slice(7) === process.env.IN_USE_VOLUME) console.log('unrelated-stopped-container');
}
save();

function artisan(joined) {
    if (joined.includes('--help')) {
        console.log('--all');
        return;
    }
    if (joined.includes('queue:pause')) state.paused = true;
    if (joined.includes('queue:resume')) state.paused = false;
    if (/artisan down\b/.test(joined)) { state.maintenance = true; fail('maintenance'); }
    if (/artisan up\b/.test(joined)) state.maintenance = false;
    if (joined.includes('migrate --force')) fail('migration');
    if (joined.includes('config:cache')) fail('cache');
    if (joined.includes('kompendium:rebuild-index')) fail('index');
    if (joined.includes('app:verify-schedule')) fail('schedule');
}
