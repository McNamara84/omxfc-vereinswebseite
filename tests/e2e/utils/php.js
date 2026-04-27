import path from 'path';

const dockerComposeFile = path.resolve('docker-compose.playwright.yml');
const dockerPhpService = 'playwright-php';
const workspaceRoot = path.resolve('.');
const dockerWorkspaceRoot = '/workspace';
const forwardedEnvironmentKeys = [
    'APP_ENV',
    'APP_DEBUG',
    'APP_KEY',
    'DB_CONNECTION',
    'DB_DATABASE',
    'SESSION_DRIVER',
    'CACHE_DRIVER',
    'QUEUE_CONNECTION',
    'MAIL_MAILER',
    'FORTIFY_DISABLE_LOGIN_RATE_LIMIT',
    'FANTREFFEN_TSHIRT_DEADLINE',
    'FANTREFFEN_MIN_FORM_TIME',
    'FANTREFFEN_DISABLE_RATE_LIMIT',
    'PLAYWRIGHT_PORT',
];

export function shouldUseDockerPhp() {
    return process.env.PLAYWRIGHT_USE_DOCKER === '1';
}

export function resolvePhpBinary() {
    return process.env.PHP_BINARY ?? 'php';
}

export function toPhpRuntimePath(filePath) {
    if (typeof filePath !== 'string' || filePath === '') {
        return filePath;
    }

    if (!shouldUseDockerPhp()) {
        return filePath;
    }

    if (filePath === dockerWorkspaceRoot || filePath.startsWith(`${dockerWorkspaceRoot}/`)) {
        return filePath;
    }

    const resolvedPath = path.resolve(filePath);
    const relativePath = path.relative(workspaceRoot, resolvedPath);

    if (relativePath.startsWith('..') || path.isAbsolute(relativePath)) {
        return resolvedPath.replaceAll('\\', '/');
    }

    const normalizedRelativePath = relativePath.split(path.sep).join('/');

    return `${dockerWorkspaceRoot}/${normalizedRelativePath}`;
}

export function isBatchPhpBinary(binary = resolvePhpBinary()) {
    return /\.(bat|cmd)$/i.test(binary);
}

function createDockerEnvironmentArgs(environment = {}) {
    return forwardedEnvironmentKeys.flatMap((key) => {
        const value = environment[key];

        if (value === undefined) {
            return [];
        }

        return ['-e', `${key}=${value}`];
    });
}

function quoteCommandPart(part) {
    const normalized = String(part);

    return /\s/.test(normalized) ? `"${normalized.replaceAll('"', '\\"')}"` : normalized;
}

export function createPhpProcess(args = [], options = {}) {
    if (shouldUseDockerPhp()) {
        return {
            command: 'docker',
            args: [
                'compose',
                '-f',
                dockerComposeFile,
                'run',
                '--rm',
                ...(options.servicePorts ? ['--service-ports'] : []),
                ...createDockerEnvironmentArgs(options.env),
                dockerPhpService,
                'php',
                ...args,
            ],
            shell: false,
        };
    }

    const phpBinary = resolvePhpBinary();

    return {
        command: phpBinary,
        args,
        shell: isBatchPhpBinary(phpBinary),
    };
}

export function formatPhpCommand(args = [], options = {}) {
    const processDefinition = createPhpProcess(args, options);

    return [processDefinition.command, ...processDefinition.args]
        .map(quoteCommandPart)
        .join(' ');
}