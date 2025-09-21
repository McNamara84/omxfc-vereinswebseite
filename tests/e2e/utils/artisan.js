import { exec as execCallback } from 'child_process';
import { promisify } from 'util';

const exec = promisify(execCallback);

export async function runArtisan(command, options = {}) {
    const env = {
        ...process.env,
        APP_ENV: process.env.APP_ENV ?? 'testing',
    };

    try {
        const result = await exec(`php artisan ${command}`, {
            env,
            ...options,
        });

        return result.stdout.trim();
    } catch (error) {
        if (error.stdout) {
            console.error(error.stdout);
        }

        if (error.stderr) {
            console.error(error.stderr);
        }

        throw error;
    }
}
