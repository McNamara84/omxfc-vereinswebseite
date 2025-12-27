import { execFile as execFileCallback } from 'child_process';
import { promisify } from 'util';

const execFile = promisify(execFileCallback);

function splitArgs(command) {
    const args = [];
    let current = '';
    let quote = null;

    for (let i = 0; i < command.length; i++) {
        const ch = command[i];

        if (quote) {
            if (ch === '\\' && i + 1 < command.length) {
                const next = command[i + 1];

                // Support escaped quotes/backslashes inside quoted strings, e.g. " or \\.
                if (next === quote || next === '\\') {
                    current += next;
                    i++;
                    continue;
                }
            }

            if (ch === quote) {
                quote = null;
                continue;
            }

            current += ch;
            continue;
        }

        if (ch === '"' || ch === "'") {
            quote = ch;
            continue;
        }

        if (ch === ' ' || ch === '\t' || ch === '\n' || ch === '\r') {
            if (current.length > 0) {
                args.push(current);
                current = '';
            }
            continue;
        }

        current += ch;
    }

    if (current.length > 0) {
        args.push(current);
    }

    return args;
}

export async function runArtisan(command, options = {}) {
    const env = {
        ...process.env,
        APP_ENV: process.env.APP_ENV ?? 'testing',
    };

    const args = ['artisan', ...splitArgs(command)];

    try {
        const result = await execFile('php', args, {
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
