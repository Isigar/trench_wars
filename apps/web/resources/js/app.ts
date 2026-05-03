// Source: 01-RESEARCH.md Pattern 2 (createInertiaApp + ZiggyVue).
// laravel-vue-i18n plugin is wired in plan 08.
// Tailwind import is wired in plan 07.

import './bootstrap';
import '../css/app.css';

import { createInertiaApp } from '@inertiajs/vue3';
import { createApp, h, type DefineComponent } from 'vue';
import { ZiggyVue } from 'ziggy-js';
// import { i18nVue } from 'laravel-vue-i18n'; // ← plan 08

const appName = (import.meta.env.VITE_APP_NAME as string | undefined) ?? 'Trenchwars';

createInertiaApp({
    title: (title) => (title ? `${title} — ${appName}` : appName),
    resolve: (name) => {
        const pages = import.meta.glob<DefineComponent>('./pages/**/*.vue');
        const page = pages[`./pages/${name}.vue`];
        if (!page) {
            throw new Error(`Inertia page not found: ./pages/${name}.vue`);
        }
        return page();
    },
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            // .use(i18nVue, { ... }) // ← plan 08
            .mount(el);
    },
    progress: {
        color: '#A4262C', // accent — placeholder, per UI-SPEC.md
    },
});
