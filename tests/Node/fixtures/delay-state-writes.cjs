// Widen the partial-file window of an in-place write during a Compose pipe.
// Only fixture state is affected; no Docker socket or network is available.
const fs = require('node:fs');
const { syncBuiltinESMExports } = require('node:module');
const read = fs.readFileSync.bind(fs);
const write = fs.writeFileSync.bind(fs);
const pause = milliseconds => Atomics.wait(new Int32Array(new SharedArrayBuffer(4)), 0, 0, milliseconds);
fs.writeFileSync = (file, data, ...options) => {
    if (process.argv.includes('config') && process.argv.includes('json') && String(file).endsWith('/state.json')) {
        write(file, '');
        pause(250);
    }
    return write(file, data, ...options);
};
fs.readFileSync = (file, ...options) => {
    if (process.argv.includes('exec') && process.argv.includes('-r') && String(file).endsWith('/state.json')) pause(100);
    return read(file, ...options);
};
syncBuiltinESMExports();
