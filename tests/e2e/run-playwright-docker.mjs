import { spawn } from 'child_process';
import path from 'path';
import { pathToFileURL } from 'url';
import { cleanupManagedDockerPort } from './utils/docker.js';
import { resolvePlaywrightRunToken } from './utils/playwright-run-token.js';

const playwrightCli = path.resolve('node_modules/playwright/cli.js');
const viteCli = path.resolve('node_modules/vite/bin/vite.js');
const shouldHideWindowsShell = process.platform === 'win32';
const installableBrowserProjects = new Set(['chromium', 'firefox', 'webkit']);
const defaultBrowserCachePath = path.resolve('.playwright-browsers');

function createChildEnv(env, forwardedScreenshotFlag) {
    return {
        ...env,
        PLAYWRIGHT_BROWSERS_PATH: env.PLAYWRIGHT_BROWSERS_PATH ?? defaultBrowserCachePath,
        PLAYWRIGHT_USE_DOCKER: '1',
        ...(forwardedScreenshotFlag === null
            ? {}
            : { PLAYWRIGHT_CAPTURE_MODAL_SCREENSHOTS: forwardedScreenshotFlag }),
    };
}

export function createProjectRuns({ args, env, basePort }) {
    const hasExplicitProjectSelection = args.some((arg) => arg === '--project' || arg.startsWith('--project='));
    const defaultProjects = env.PLAYWRIGHT_DOCKER_PROJECTS
        ? env.PLAYWRIGHT_DOCKER_PROJECTS.split(',').map((project) => project.trim()).filter(Boolean)
        : env.CI
            ? ['chromium', 'firefox']
            : ['chromium', 'firefox', 'webkit'];
    const providedRunToken = env.PLAYWRIGHT_RUN_TOKEN ?? null;

    if (hasExplicitProjectSelection) {
        return [{
            args,
            env: {
                PLAYWRIGHT_RUN_TOKEN: resolvePlaywrightRunToken(providedRunToken, { prefix: 'docker' }),
            },
        }];
    }

    return defaultProjects.map((project, index) => ({
        args: [...args, '--project', project],
        env: {
            PLAYWRIGHT_PORT: String(basePort + index),
            PLAYWRIGHT_RUN_TOKEN: resolvePlaywrightRunToken(providedRunToken, { prefix: `docker-${project}` }),
        },
    }));
}

function extractProjectNames(args) {
    const projects = [];

    for (let index = 0; index < args.length; index += 1) {
        const arg = args[index];

        if (arg === '--project' && args[index + 1]) {
            projects.push(args[index + 1]);
            index += 1;
            continue;
        }

        if (arg.startsWith('--project=')) {
            projects.push(arg.slice('--project='.length));
        }
    }

    return projects;
}

export function collectBrowserInstallProjects(projectRuns) {
    return [
        ...new Set(projectRuns
            .flatMap((run) => extractProjectNames(run.args))
            .filter((project) => installableBrowserProjects.has(project))),
    ];
}

const runPlaywrightInstall = (projects, baseEnv, { spawnFn = spawn } = {}) => new Promise((resolve, reject) => {
    if (projects.length === 0) {
        resolve(0);
        return;
    }

    const child = spawnFn(process.execPath, [playwrightCli, 'install', ...projects], {
        stdio: 'inherit',
        windowsHide: shouldHideWindowsShell,
        env: baseEnv,
    });

    child.on('exit', (code) => {
        resolve(code ?? 1);
    });

    child.on('error', (error) => {
        reject(error);
    });
});

const runPlaywright = (args, baseEnv, envOverrides = {}, { spawnFn = spawn } = {}) => new Promise((resolve, reject) => {
    const child = spawnFn(process.execPath, [playwrightCli, 'test', ...args], {
        stdio: 'inherit',
        windowsHide: shouldHideWindowsShell,
        env: {
            ...baseEnv,
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

const runViteBuild = (baseEnv, { spawnFn = spawn } = {}) => new Promise((resolve, reject) => {
    const child = spawnFn(process.execPath, [viteCli, 'build'], {
        stdio: 'inherit',
        windowsHide: shouldHideWindowsShell,
        env: baseEnv,
    });

    child.on('exit', (code) => {
        resolve(code ?? 1);
    });

    child.on('error', (error) => {
        reject(error);
    });
});

export async function main({
    argv = process.argv.slice(2),
    env = process.env,
    spawnFn = spawn,
    cleanupManagedDockerPortFn = cleanupManagedDockerPort,
} = {}) {
    const captureModalScreenshots = argv.includes('--capture-modal-screenshots');
    const playwrightArgs = captureModalScreenshots
        ? argv.filter((arg) => arg !== '--capture-modal-screenshots')
        : argv;
    const forwardedScreenshotFlag = captureModalScreenshots
        ? '1'
        : env.PLAYWRIGHT_CAPTURE_MODAL_SCREENSHOTS ?? null;
    const basePort = Number(env.PLAYWRIGHT_PORT ?? 8001);
    const childEnv = createChildEnv(env, forwardedScreenshotFlag);
    const shouldUseViteHot = childEnv.PLAYWRIGHT_USE_VITE_HOT === '1';

    const projectRuns = createProjectRuns({
        args: playwrightArgs,
        env: childEnv,
        basePort,
    });
    const shouldInstallBrowsers = childEnv.PLAYWRIGHT_SKIP_BROWSER_INSTALL !== '1';

    if (shouldInstallBrowsers) {
        const installCode = await runPlaywrightInstall(
            collectBrowserInstallProjects(projectRuns),
            childEnv,
            { spawnFn },
        );

        if (installCode !== 0) {
            return installCode;
        }
    }

    if (!shouldUseViteHot) {
        const buildCode = await runViteBuild(childEnv, { spawnFn });

        if (buildCode !== 0) {
            return buildCode;
        }
    }

    for (const run of projectRuns) {
        const projectPort = Number(run.env.PLAYWRIGHT_PORT ?? childEnv.PLAYWRIGHT_PORT ?? basePort);

        cleanupManagedDockerPortFn(projectPort);

        const code = await runPlaywright(run.args, childEnv, run.env, { spawnFn });

        cleanupManagedDockerPortFn(projectPort);

        if (code !== 0) {
            return code;
        }
    }

    return 0;
}

export function isDirectExecution(scriptPath = process.argv[1]) {
    if (!scriptPath) {
        return false;
    }

    return import.meta.url === pathToFileURL(path.resolve(scriptPath)).href;
}

if (isDirectExecution()) {
    try {
        process.exit(await main());
    } catch (error) {
        console.error(error);
        process.exit(1);
    }
}