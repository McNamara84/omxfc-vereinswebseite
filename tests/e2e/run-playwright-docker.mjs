import { spawn } from 'child_process';
import path from 'path';
import { pathToFileURL } from 'url';
import { cleanupManagedDockerPort } from './utils/docker.js';
import { resolvePlaywrightRunToken } from './utils/playwright-run-token.js';

const playwrightCli = path.resolve('node_modules/playwright/cli.js');
const shouldHideWindowsShell = process.platform === 'win32';

function createChildEnv(env, forwardedScreenshotFlag) {
    return {
        ...env,
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

export async function main({ argv = process.argv.slice(2), env = process.env } = {}) {
    const captureModalScreenshots = argv.includes('--capture-modal-screenshots');
    const playwrightArgs = captureModalScreenshots
        ? argv.filter((arg) => arg !== '--capture-modal-screenshots')
        : argv;
    const forwardedScreenshotFlag = captureModalScreenshots
        ? '1'
        : env.PLAYWRIGHT_CAPTURE_MODAL_SCREENSHOTS ?? null;
    const basePort = Number(env.PLAYWRIGHT_PORT ?? 8001);
    const childEnv = createChildEnv(env, forwardedScreenshotFlag);
    const projectRuns = createProjectRuns({
        args: playwrightArgs,
        env: childEnv,
        basePort,
    });

    for (const run of projectRuns) {
        const projectPort = Number(run.env.PLAYWRIGHT_PORT ?? childEnv.PLAYWRIGHT_PORT ?? basePort);

        cleanupManagedDockerPort(projectPort);

        const code = await runPlaywright(run.args, run.env);

        cleanupManagedDockerPort(projectPort);

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