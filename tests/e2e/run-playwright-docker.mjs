import { spawn } from 'child_process';
import path from 'path';

const playwrightCli = path.resolve('node_modules/playwright/cli.js');
const extraArgs = process.argv.slice(2);
const captureModalScreenshots = extraArgs.includes('--capture-modal-screenshots');
const playwrightArgs = captureModalScreenshots
    ? extraArgs.filter((arg) => arg !== '--capture-modal-screenshots')
    : extraArgs;
const forwardedScreenshotFlag = captureModalScreenshots
    ? '1'
    : process.env.PLAYWRIGHT_CAPTURE_MODAL_SCREENSHOTS ?? null;
const childEnv = {
    ...process.env,
    PLAYWRIGHT_USE_DOCKER: '1',
    ...(forwardedScreenshotFlag === null
        ? {}
        : { PLAYWRIGHT_CAPTURE_MODAL_SCREENSHOTS: forwardedScreenshotFlag }),
};

const child = spawn(process.execPath, [playwrightCli, 'test', ...playwrightArgs], {
    stdio: 'inherit',
    env: childEnv,
});

child.on('exit', (code) => {
    process.exit(code ?? 1);
});

child.on('error', (error) => {
    console.error(error);
    process.exit(1);
});