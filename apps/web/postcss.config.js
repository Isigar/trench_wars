// Source: 01-RESEARCH.md Pitfall 1 step-by-step + Code Examples vite.filament.config.ts.
// This config is INTENTIONALLY NOT registered by the main vite.config.ts —
// the main bundle uses @tailwindcss/vite (Tailwind v4) for the public site.
// vite.filament.config.ts uses this PostCSS config to compile the Filament theme
// against Tailwind v3 (aliased to tailwindcss-v3 in package.json).
//
// ESM form because apps/web/package.json declares "type": "module".

export default {
    plugins: {
        'tailwindcss-v3/nesting': {},
        'tailwindcss-v3': {
            config: './tailwind.filament.config.js',
        },
        autoprefixer: {},
    },
};
