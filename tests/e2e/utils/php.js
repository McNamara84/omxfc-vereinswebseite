import path from 'path';

const dockerComposeFile = path.resolve(process.env.PLAYWRIGHT_DOCKER_COMPOSE_FILE ?? 'docker-compose.dev.yml');
const dockerPhpService = process.env.PLAYWRIGHT_DOCKER_PHP_SERVICE ?? 'playwright-php';
const workspaceRoot = path.resolve('.');
const dockerWorkspaceRoot = '/var/www/html';
const explicitForwardedEnvironmentKeys = [
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
    'PLAYWRIGHT_USE_DOCKER',
    'PLAYWRIGHT_PORT',
    'PLAYWRIGHT_RUN_TOKEN',
];
const forwardedEnvironmentPrefixes = ['E2E_', 'TEST_'];

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
    const forwardedEnvironmentKeys = new Set(explicitForwardedEnvironmentKeys);

    Object.keys(environment)
        .filter((key) => forwardedEnvironmentPrefixes.some((prefix) => key.startsWith(prefix)))
        .forEach((key) => forwardedEnvironmentKeys.add(key));

    return [...forwardedEnvironmentKeys].flatMap((key) => {
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
                '-T',
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

export function createDockerServiceProcess(args = [], options = {}) {
    if (!shouldUseDockerPhp()) {
        throw new Error('createDockerServiceProcess() requires PLAYWRIGHT_USE_DOCKER=1.');
    }

    return {
        command: 'docker',
        args: [
            'compose',
            '-f',
            dockerComposeFile,
            'run',
            '-T',
            '--rm',
            ...(options.servicePorts ? ['--service-ports'] : []),
            ...createDockerEnvironmentArgs(options.env),
            dockerPhpService,
            ...args,
        ],
        shell: false,
    };
}

export function formatPhpCommand(args = [], options = {}) {
    const processDefinition = createPhpProcess(args, options);

    return [processDefinition.command, ...processDefinition.args]
        .map(quoteCommandPart)
        .join(' ');
}

export function formatDockerServiceCommand(args = [], options = {}) {
    const processDefinition = createDockerServiceProcess(args, options);

    return [processDefinition.command, ...processDefinition.args]
        .map(quoteCommandPart)
        .join(' ');
}