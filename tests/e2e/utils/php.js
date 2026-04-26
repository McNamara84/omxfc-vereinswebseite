export function resolvePhpBinary() {
    return process.env.PHP_BINARY ?? (process.platform === 'win32' ? 'php.bat' : 'php');
}

export function isBatchPhpBinary(binary = resolvePhpBinary()) {
    return /\.(bat|cmd)$/i.test(binary);
}

export function formatPhpCommand(binary = resolvePhpBinary()) {
    return /\s/.test(binary) ? `"${binary}"` : binary;
}