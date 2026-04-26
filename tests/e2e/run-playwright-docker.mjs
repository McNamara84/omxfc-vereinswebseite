import { spawn } from 'child_process';
import path from 'path';

const playwrightCli = path.resolve('node_modules/playwright/cli.js');
const child = spawn(process.execPath, [playwrightCli, 'test', ...process.argv.slice(2)], {
    stdio: 'inherit',
    env: {
        ...process.env,
        PLAYWRIGHT_USE_DOCKER: '1',
    },
});

child.on('exit', (code) => {
    process.exit(code ?? 1);
});

child.on('error', (error) => {
    console.error(error);
    process.exit(1);
});