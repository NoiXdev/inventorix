import { defineConfig } from 'vitest/config';

export default defineConfig({
    test: {
        environment: 'happy-dom',
        include: ['resources/js/**/__tests__/**/*.test.ts'],
        globals: false,
    },
    resolve: {
        alias: {
            '@': new URL('./resources/js/', import.meta.url).pathname,
        },
    },
});
