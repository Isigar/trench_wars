// Source: 01-RESEARCH.md Pitfall 1 + Code Examples vite.filament.config.ts.
// Builds ONLY the Filament admin theme using Tailwind v3 (via PostCSS) into a
// SEPARATE build directory so it does not collide with the main Tailwind v4 bundle
// produced by vite.config.ts.
//
// Vite auto-detects postcss.config.js (sibling file) for CSS compilation; that
// PostCSS config wires `tailwindcss-v3` (the npm alias for tailwindcss@^3.4) +
// autoprefixer. The main vite.config.ts uses @tailwindcss/vite (Tailwind v4)
// instead, so the two pipelines never share a Tailwind version.

import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/filament/admin/theme.css'],
            buildDirectory: 'build/filament',
            refresh: true,
        }),
    ],
});
