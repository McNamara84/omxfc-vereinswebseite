import { execFile as execFileCallback } from 'child_process';
import { promisify } from 'util';

const execFile = promisify(execFileCallback);

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

    const phpArgs = ['artisan', ...args];

    try {
        const result = await execFile('php', phpArgs, {
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
