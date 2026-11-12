import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vitest/config';

// Vitest covers the pure-TS calculator logic (the client half of evaluator parity). Scope
// it to our own source so it never wanders into vendor example specs (Playwright, etc.).
export default defineConfig({
    resolve: {
        // Local package overlays must share React with the host renderer, as in vite.config.ts.
        dedupe: ['react', 'react-dom'],
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
        },
    },
    test: {
        include: ['resources/js/**/*.test.ts'],
        exclude: ['node_modules', 'vendor'],
    },
});
