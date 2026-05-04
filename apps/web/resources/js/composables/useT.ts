// Source: 01-CONTEXT.md "Vue helper t(key, params)" + UI-SPEC.md ":?param" interpolation.
//
// We resolve from Inertia's shared `translations` prop directly — no separate JSON bundle,
// no build-step glue. This avoids the RESEARCH Pitfall 8 SSR async-glob trap entirely
// (laravel-vue-i18n's php-translations Vite plugin compiles `lang/en/*.php` to JSON at
// build time which breaks SSR; our path is build-free and SSR-safe).
//
// `laravel-vue-i18n` is installed but NOT wired in P1 — we keep it as a forward option
// for client-side validation message rendering in Phase 2+.

import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

type Params = Record<string, string | number>;

export function useT() {
    const page = usePage();

    const translations = computed(
        () => (page.props.translations ?? {}) as Record<string, string>,
    );

    function t(key: string, params: Params = {}): string {
        const raw = translations.value[key];
        if (raw === undefined) {
            // Surface missing keys loudly in dev; silent fallback in production builds.
            if (import.meta.env.DEV) {
                // eslint-disable-next-line no-console
                console.warn(`[i18n] missing key: ${key}`);
            }
            return key;
        }
        return Object.entries(params).reduce(
            (out, [k, v]) => out.replaceAll(`:${k}`, String(v)),
            raw,
        );
    }

    return { t };
}

// Re-export a bare `t` for convenient template imports inside <script setup>.
export function t(key: string, params: Params = {}): string {
    return useT().t(key, params);
}
