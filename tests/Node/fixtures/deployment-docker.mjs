// No Docker socket or network access: only fixture files and recorded calls.
import fs from 'node:fs';

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
    else if (format.includes('/var/www/html/storage')) console.log(process.env.MISSING_STORAGE ? '' : 'volume:/var/lib/docker/volumes/fixture_storage/_data');
    else if (format.includes('/var/www/html')) console.log('fixture_app_data_old');
    else if (format.includes('/data')) console.log('/data');
} else if (args[0] === 'volume') {
    if (args[1] === 'inspect') {
        if (args.at(-1).includes('compose.volume')) console.log('app_data');
        else console.log(process.env.FOREIGN_VOLUME === args[2] ? 'another-project' : 'fixture');
    } else if (args[1] === 'rm') {
        fail('retention');
    } else if (args[1] === 'create') console.log(args.at(-1));
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
        if (tail.includes('--images')) console.log(tail.at(-1) === 'db' ? process.env.OMXFC_DATABASE_IMAGE : process.env.OMXFC_APP_IMAGE);
        else if (!tail.includes('--quiet')) console.log('services: {}');
    } else if (command === 'pull') fail('pull');
    else if (['stop', 'up', 'start'].includes(command)) {
        for (const service of tail.filter(arg => services.includes(arg))) state.running[service] = command !== 'stop';
        if (!recovery) {
            if (command === 'stop' && tail.includes('queue')) fail('queue-stop');
            if (command === 'stop' && tail.includes('app')) fail('app-stop');
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
        if (joined.includes('SELECT VERSION()')) console.log(process.env.DATABASE_VERSION || '13.0.1-MariaDB');
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
    const directory = backup.split(',').find(part => part.startsWith('source=')).slice(7);
    if (args.includes('-czf')) {
        fail('typesense-backup');
        fs.writeFileSync(directory + '/typesense-data.tar.gz', 'fixture archive');
    }
} else if (args[0] === 'exec') {
    const joined = args.join(' ');
    if (joined.includes('SELECT 1')) {
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
