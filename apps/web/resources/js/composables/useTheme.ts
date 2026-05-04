// Source: 01-UI-SPEC.md § Theme switching (data-theme attr on <html>; dark default; localStorage persist).

import { onMounted, ref, watchEffect } from 'vue';

type Theme = 'dark' | 'light';

const STORAGE_KEY = 'trenchwars.theme';

const theme = ref<Theme>('dark');

function applyTheme(next: Theme): void {
    if (typeof document !== 'undefined') {
        document.documentElement.setAttribute('data-theme', next);
    }
    if (typeof localStorage !== 'undefined') {
        localStorage.setItem(STORAGE_KEY, next);
    }
}

export function useTheme() {
    onMounted(() => {
        if (typeof localStorage !== 'undefined') {
            const stored = localStorage.getItem(STORAGE_KEY) as Theme | null;
            if (stored === 'dark' || stored === 'light') {
                theme.value = stored;
            }
        }
        applyTheme(theme.value);
    });

    watchEffect(() => applyTheme(theme.value));

    return {
        theme,
        toggle: () => {
            theme.value = theme.value === 'dark' ? 'light' : 'dark';
        },
        setTheme: (next: Theme) => {
            theme.value = next;
        },
    };
}
