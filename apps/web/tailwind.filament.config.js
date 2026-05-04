// Source: filamentphp.com/docs/3.x/panels/themes — required Tailwind v3 config for theme.
// This is the LEGACY config — NOT used by the public site (Tailwind v4 CSS-first).
// Applies ONLY to the Filament theme bundle compiled by vite.filament.config.ts.
//
// ESM form (apps/web/package.json declares "type": "module"); the Filament preset
// at vendor/filament/support/tailwind.config.preset.js is ESM (`export default`),
// so we import it directly rather than require()-ing it.

import preset from './vendor/filament/support/tailwind.config.preset.js';

export default {
    presets: [preset],
    content: [
        './app/Filament/**/*.php',
        './resources/views/filament/**/*.blade.php',
        './vendor/filament/**/*.blade.php',
    ],
    theme: {
        extend: {
            colors: {
                // Trench-military accent — keeps the Filament panel on-brand
                // (UI-SPEC.md § Filament panel & gating: placeholder accent #A4262C).
                primary: {
                    50: '#FAEAEC',
                    100: '#F4D5D8',
                    200: '#E5A6AC',
                    300: '#D27780',
                    400: '#BF4F58',
                    500: '#A4262C',
                    600: '#8E1E22',
                    700: '#74181C',
                    800: '#5A1216',
                    900: '#3F0C0F',
                    950: '#270608',
                },
            },
        },
    },
};
