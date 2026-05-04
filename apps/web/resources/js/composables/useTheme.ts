// Source: 01-UI-SPEC.md § Theme switching (data-theme attr on <html>; dark default; localStorage persist).
//
// WR-02 (01-REVIEW.md): the previous implementation kept a module-level ref and
// registered a watchEffect on every useTheme() call. Two problems:
//   1. SSR — vite.config.ts declares ssr: 'resources/js/ssr.ts'. Module-level
//      state in Node is shared across concurrent requests, so request A's
//      theme toggle would leak into request B's HTML.
//   2. N effect handlers — every component calling useTheme() registered a
//      new watchEffect that wrote to localStorage on every change.
//
// Fix: detect a browser environment via `typeof window !== 'undefined'`. Under
// SSR, return a non-reactive shadow that doesn't touch DOM/localStorage and is
// not shared across requests. In the browser, the singleton ref + a single
// watchEffect (registered once at module-import time) drive every consumer.

import { onMounted, ref, watchEffect, type Ref } from 'vue';

type Theme = 'dark' | 'light';

const STORAGE_KEY = 'trenchwars.theme';
const IS_BROWSER = typeof window !== 'undefined' && typeof document !== 'undefined';

function applyTheme(next: Theme): void {
    if (typeof document !== 'undefined') {
        document.documentElement.setAttribute('data-theme', next);
    }
    if (typeof localStorage !== 'undefined') {
        localStorage.setItem(STORAGE_KEY, next);
    }
}

// Browser singleton — a SINGLE ref that every browser-side useTheme() call
// reads from. The watchEffect that writes to DOM/localStorage is registered
// EXACTLY ONCE here, not per consumer.
const browserTheme: Ref<Theme> | null = IS_BROWSER ? ref<Theme>('dark') : null;

if (IS_BROWSER && browserTheme !== null) {
    // Hydrate from localStorage at module import time, then observe.
    const stored = localStorage.getItem(STORAGE_KEY) as Theme | null;
    if (stored === 'dark' || stored === 'light') {
        browserTheme.value = stored;
    }
    watchEffect(() => applyTheme(browserTheme.value));
}

export function useTheme() {
    if (!IS_BROWSER || browserTheme === null) {
        // SSR path — return a per-call ref so concurrent requests can't see
        // each other's mutations. No DOM/localStorage side-effects.
        const ssrTheme = ref<Theme>('dark');
        return {
            theme: ssrTheme,
            toggle: () => {
                ssrTheme.value = ssrTheme.value === 'dark' ? 'light' : 'dark';
            },
            setTheme: (next: Theme) => {
                ssrTheme.value = next;
            },
        };
    }

    // Browser path — re-apply on mount in case the consumer hydrates BEFORE
    // module-level watchEffect picked up the stored theme (cheap belt-and-suspenders).
    onMounted(() => {
        applyTheme(browserTheme.value);
    });

    return {
        theme: browserTheme,
        toggle: () => {
            browserTheme.value = browserTheme.value === 'dark' ? 'light' : 'dark';
        },
        setTheme: (next: Theme) => {
            browserTheme.value = next;
        },
    };
}
