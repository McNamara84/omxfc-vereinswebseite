async function importPhpUtils() {
    vi.resetModules();

    return import('../e2e/utils/php.js');
}

afterEach(() => {
    vi.unstubAllEnvs();
});

describe('php utils', () => {
    it('nutzt lokal standardmaessig php und Batch-Erkennung nur fuer explizite Overrides', async () => {
        vi.stubEnv('PHP_BINARY', 'C:/php/custom-8.5.bat');

        const { resolvePhpBinary, isBatchPhpBinary, createPhpProcess } = await importPhpUtils();

        expect(resolvePhpBinary()).toBe('C:/php/custom-8.5.bat');
        expect(isBatchPhpBinary()).toBe(true);
        expect(createPhpProcess(['artisan', 'about'])).toEqual({
            command: 'C:/php/custom-8.5.bat',
            args: ['artisan', 'about'],
            shell: true,
        });
    });

    it('nutzt ohne Docker den simplen php-Binary-Default', async () => {
        const { createPhpProcess, formatPhpCommand, shouldUseDockerPhp } = await importPhpUtils();

        expect(shouldUseDockerPhp()).toBe(false);
        expect(createPhpProcess(['artisan', 'migrate'])).toEqual({
            command: 'php',
            args: ['artisan', 'migrate'],
            shell: false,
        });
        expect(formatPhpCommand(['-v'])).toBe('php -v');
    });

    it('baut unter Docker den compose-basierten PHP-Prozess', async () => {
        vi.stubEnv('PLAYWRIGHT_USE_DOCKER', '1');

        const { createPhpProcess, shouldUseDockerPhp, toPhpRuntimePath } = await importPhpUtils();

        expect(shouldUseDockerPhp()).toBe(true);
        expect(createPhpProcess(['artisan', 'migrate'], {
            env: { DB_DATABASE: '/workspace/database/playwright.sqlite' },
        })).toEqual({
            command: 'docker',
            args: [
                'compose',
                '-f',
                expect.stringMatching(/docker-compose\.dev\.yml$/),
                'run',
                '-T',
                '--rm',
                '-e',
                'DB_DATABASE=/workspace/database/playwright.sqlite',
                'playwright-php',
                'php',
                'artisan',
                'migrate',
            ],
            shell: false,
        });
        expect(toPhpRuntimePath('database/playwright.sqlite')).toBe('/var/www/html/database/playwright.sqlite');
    });

    it('reicht zusaetzliche Test-Credentials mit E2E- oder TEST-Prefix an Docker weiter', async () => {
        vi.stubEnv('PLAYWRIGHT_USE_DOCKER', '1');

        const { createPhpProcess } = await importPhpUtils();

        expect(createPhpProcess(['artisan', 'about'], {
            env: {
                E2E_SANDBOX_USERNAME: 'demo-user',
                TEST_API_TOKEN: 'top-secret',
            },
        }).args).toEqual(expect.arrayContaining([
            '-e',
            'E2E_SANDBOX_USERNAME=demo-user',
            '-e',
            'TEST_API_TOKEN=top-secret',
        ]));
    });

    it('formatiert unter Docker den service-port-faehigen Server-Command', async () => {
        vi.stubEnv('PLAYWRIGHT_USE_DOCKER', '1');

        const { formatPhpCommand } = await importPhpUtils();

        expect(formatPhpCommand(['-S', '0.0.0.0:8001', 'server.php'], { servicePorts: true })).toContain('docker compose');
        expect(formatPhpCommand(['-S', '0.0.0.0:8001', 'server.php'], { servicePorts: true })).toContain('--service-ports');
        expect(formatPhpCommand(['-S', '0.0.0.0:8001', 'server.php'], { servicePorts: true })).toContain('playwright-php');
    });
});