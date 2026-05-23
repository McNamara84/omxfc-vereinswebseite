import { spawn } from 'child_process';
import { createPhpProcess } from './php.js';

export async function runArtisan(args, options = {}) {
    const env = {
        ...process.env,
        APP_ENV: process.env.APP_ENV ?? 'testing',
    };

    if (!Array.isArray(args) || args.length === 0) {
        throw new Error(
            'runArtisan() erwartet ein Array von Argumenten, z.B. runArtisan(["migrate"]) oder runArtisan(["db:seed", "--class=Database\\\\Seeders\\\\FooSeeder"]).',
        );
    }

    const phpProcess = createPhpProcess(['artisan', ...args], { env });

    await new Promise((resolve, reject) => {
        const child = spawn(phpProcess.command, phpProcess.args, {
            env,
            shell: phpProcess.shell,
            stdio: options.stdio ?? 'inherit',
            ...options,
        });

        child.on('error', reject);
        child.on('exit', (code) => {
            if (code === 0) {
                resolve();

                return;
            }

            reject(new Error(`Command failed: ${phpProcess.command} ${phpProcess.args.join(' ')}`));
        });
    });

    return '';
}
