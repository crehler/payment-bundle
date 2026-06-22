import { defineConfig } from 'vitest/config';

export default defineConfig({
    test: {
        testTimeout: 300000, // 5 minutes - long timeout for interactive tests
        hookTimeout: 60000,
        globals: true,
        reporters: ['verbose'],
        fileParallelism: false, // Run test files sequentially to maintain session
        sequence: {
            shuffle: false,
        },
    },
});
