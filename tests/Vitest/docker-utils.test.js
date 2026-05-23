import { createManagedDockerCleanupArgs } from '../e2e/utils/docker.js';

describe('docker utils', () => {
    it('grenzt den Port-Cleanup auf den eigenen Compose-Runner ein', () => {
        expect(createManagedDockerCleanupArgs(8001, {
            PLAYWRIGHT_DOCKER_PROJECT_NAME: 'custom-project',
            PLAYWRIGHT_DOCKER_PHP_SERVICE: 'custom-service',
        })).toEqual([
            'ps',
            '--filter',
            'publish=8001',
            '--filter',
            'label=com.docker.compose.project=custom-project',
            '--filter',
            'label=com.docker.compose.service=custom-service',
            '--format',
            '{{.ID}}',
        ]);
    });

    it('nutzt sichere Standardwerte fuer Projekt und Service', () => {
        expect(createManagedDockerCleanupArgs(8002)).toEqual([
            'ps',
            '--filter',
            'publish=8002',
            '--filter',
            'label=com.docker.compose.project=omxfc-dev',
            '--filter',
            'label=com.docker.compose.service=playwright-php',
            '--format',
            '{{.ID}}',
        ]);
    });
});