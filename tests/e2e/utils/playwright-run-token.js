import * as crypto from 'node:crypto';

function defaultUuidFactory() {
    if (typeof crypto.randomUUIDv7 === 'function') {
        return crypto.randomUUIDv7();
    }

    return crypto.randomUUID();
}

export function createPlaywrightRunToken(prefix = 'local', { uuidFactory = defaultUuidFactory } = {}) {
    const normalizedPrefix = prefix.trim() === '' ? 'local' : prefix;

    return `${normalizedPrefix}-${uuidFactory()}`;
}

export function resolvePlaywrightRunToken(currentValue, options = {}) {
    return currentValue ?? createPlaywrightRunToken(options.prefix ?? 'local', options);
}