// Source: 01-RESEARCH.md Pattern 2 (createInertiaApp + ZiggyVue).
//
// Plan 08 wires i18n via the in-house `useT()` composable that reads from Inertia's
// shared `translations` prop — no Vue plugin install needed. `laravel-vue-i18n` is
// installed for forward use (Phase 2+ client-side validation rendering) but not wired
// here, which sidesteps the RESEARCH Pitfall 8 SSR async-glob trap.

import './bootstrap';
import '../css/app.css';

import { createInertiaApp } from '@inertiajs/vue3';
import { createApp, h, type DefineComponent } from 'vue';
import { ZiggyVue } from 'ziggy-js';

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
            .mount(el);
    },
    progress: {
        color: '#A4262C', // accent — placeholder, per UI-SPEC.md
    },
});
