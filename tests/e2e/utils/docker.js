import { spawnSync } from 'child_process';

const shouldHideWindowsShell = process.platform === 'win32';

export function getPlaywrightDockerProjectName(env = process.env) {
    return env.PLAYWRIGHT_DOCKER_PROJECT_NAME ?? 'omxfc-dev';
}

export function getPlaywrightDockerServiceName(env = process.env) {
    return env.PLAYWRIGHT_DOCKER_PHP_SERVICE ?? 'playwright-php';
}

export function createManagedDockerCleanupArgs(port, env = process.env) {
    return [
        'ps',
        '--filter',
        `publish=${port}`,
        '--filter',
        `label=com.docker.compose.project=${getPlaywrightDockerProjectName(env)}`,
        '--filter',
        `label=com.docker.compose.service=${getPlaywrightDockerServiceName(env)}`,
        '--format',
        '{{.ID}}',
    ];
}

export function cleanupManagedDockerPort(port, env = process.env) {
    const listing = spawnSync('docker', createManagedDockerCleanupArgs(port, env), {
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
}