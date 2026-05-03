// Source: 01-RESEARCH.md Pattern 2.
// Tailwind v4 plugin import is commented out — plan 07 enables it.

import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
// import tailwindcss from '@tailwindcss/vite'; // ← uncommented in plan 07

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.ts'],
            ssr: 'resources/js/ssr.ts',
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        // tailwindcss(), // ← added in plan 07
    ],
    resolve: {
        alias: {
            '@': '/resources/js',
        },
    },
});
