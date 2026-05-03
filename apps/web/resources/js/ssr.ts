// Source: 01-RESEARCH.md Pattern 2 (SSR scaffold). Disabled by default in
// `apps/web/config/inertia.php`; production enables via `php artisan inertia:start-ssr`.

import { createInertiaApp } from '@inertiajs/vue3';
import createServer from '@inertiajs/vue3/server';
import { renderToString } from '@vue/server-renderer';
import { createSSRApp, h, type DefineComponent } from 'vue';
import { ZiggyVue } from 'ziggy-js';

const appName = process.env.VITE_APP_NAME ?? 'Trenchwars';

createServer((page) =>
    createInertiaApp({
        page,
        render: renderToString,
        title: (title) => (title ? `${title} — ${appName}` : appName),
        resolve: (name) => {
            const pages = import.meta.glob<DefineComponent>('./pages/**/*.vue', { eager: true });
            const found = pages[`./pages/${name}.vue`];
            if (!found) throw new Error(`SSR page not found: ./pages/${name}.vue`);
            return found;
        },
        setup({ App, props, plugin }) {
            return createSSRApp({ render: () => h(App, props) }).use(plugin).use(ZiggyVue);
        },
    }),
);
