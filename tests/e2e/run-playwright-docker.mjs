import { spawn, spawnSync } from 'child_process';
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
const basePort = Number(process.env.PLAYWRIGHT_PORT ?? 8001);
const childEnv = {
    ...process.env,
    PLAYWRIGHT_USE_DOCKER: '1',
    ...(forwardedScreenshotFlag === null
        ? {}
        : { PLAYWRIGHT_CAPTURE_MODAL_SCREENSHOTS: forwardedScreenshotFlag }),
};
const shouldHideWindowsShell = process.platform === 'win32';
const hasExplicitProjectSelection = playwrightArgs.some((arg) => arg === '--project' || arg.startsWith('--project='));
const defaultProjects = process.env.PLAYWRIGHT_DOCKER_PROJECTS
    ? process.env.PLAYWRIGHT_DOCKER_PROJECTS.split(',').map((project) => project.trim()).filter(Boolean)
    : process.env.CI
        ? ['chromium', 'firefox']
        : ['chromium', 'firefox', 'webkit'];
const projectRuns = hasExplicitProjectSelection
    ? [{ args: playwrightArgs, env: {} }]
    : defaultProjects.map((project, index) => ({
        args: [...playwrightArgs, '--project', project],
        env: { PLAYWRIGHT_PORT: String(basePort + index) },
    }));

const cleanupDockerPort = (port) => {
    const listing = spawnSync('docker', ['ps', '--filter', `publish=${port}`, '--format', '{{.ID}}'], {
        encoding: 'utf8',
        windowsHide: shouldHideWindowsShell,
    });

    if (listing.status !== 0) {
        return;
    }

    const containerIds = listing.stdout
        .split(/\r?\n/)
        .map((line) => line.trim())
        .filter(Boolean);

    for (const containerId of containerIds) {
        spawnSync('docker', ['rm', '-f', containerId], {
            stdio: 'ignore',
            windowsHide: shouldHideWindowsShell,
        });
    }
};

const runPlaywright = (args, envOverrides = {}) => new Promise((resolve, reject) => {
    const child = spawn(process.execPath, [playwrightCli, 'test', ...args], {
        stdio: 'inherit',
        windowsHide: shouldHideWindowsShell,
        env: {
            ...childEnv,
            ...envOverrides,
        },
    });

    child.on('exit', (code) => {
        resolve(code ?? 1);
    });

    child.on('error', (error) => {
        reject(error);
    });
});

try {
    for (const run of projectRuns) {
        const projectPort = Number(run.env.PLAYWRIGHT_PORT ?? childEnv.PLAYWRIGHT_PORT ?? basePort);

        cleanupDockerPort(projectPort);

        const code = await runPlaywright(run.args, run.env);

        cleanupDockerPort(projectPort);

        if (code !== 0) {
            process.exit(code);
        }
    }

    process.exit(0);
} catch (error) {
    console.error(error);
    process.exit(1);
}