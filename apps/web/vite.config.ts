// Source: 01-RESEARCH.md Pattern 2 + 01-UI-SPEC.md (Tailwind v4 CSS-first).
//
// Plan 12 introduces the dual-Tailwind workaround: a sibling postcss.config.js
// exists for vite.filament.config.ts (Tailwind v3 + autoprefixer) ONLY. Vite would
// normally auto-detect that PostCSS config for THIS build too, which breaks the
// main pipeline (Tailwind v4 directives differ from v3's @tailwind base/etc.).
// `css.postcss: { plugins: [] }` forces Vite to skip the auto-detected file so
// @tailwindcss/vite is the sole CSS pipeline for the public site.

import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import tailwindcss from '@tailwindcss/vite';

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
        tailwindcss(),
    ],
    css: {
        postcss: { plugins: [] },
    },
    // Bundle all deps into the SSR output so `node bootstrap/ssr/ssr.js` runs
    // standalone (the Railway web image drops node_modules; the ssr role needs
    // a self-contained bundle). See docker/web.railway.Dockerfile.
    ssr: {
        noExternal: true,
    },
    resolve: {
        alias: {
            '@': '/resources/js',
        },
    },
});
