import { defineConfig, mergeConfig } from 'vitest/config';
import config from './vitest.config.js';

// Explicit local opt-in; regular runs and CI use vitest.config.js.
export default mergeConfig(config, defineConfig({
    test: { experimental: { traceTimeline: true } },
}));
