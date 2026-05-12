<!-- Source: 01-UI-SPEC.md § Layout primitives + § Page chrome diagram.
     Plan 08 routes the skip-link label and footer copy through `t()` per D-013.
     Phase 2 (plan 02-08): nav slot populated with Clans + Players links + UserMenu auth action.
     Nav items hidden on mobile (hidden md:flex) per UI-SPEC § Responsive Breakpoints. -->
<script setup lang="ts">
import LoginButton from '@/components/LoginButton.vue';
import ThemeToggle from '@/components/ThemeToggle.vue';
import UserMenu from '@/components/UserMenu.vue';
import Wordmark from '@/components/Wordmark.vue';
import { useT } from '@/composables/useT';
import type { AuthUser } from '@/types/inertia';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const { t } = useT();
const year = new Date().getFullYear();
const page = usePage();

// Auth state: user from Inertia shared props.
const user = computed<AuthUser | null>(() => page.props.auth ?? null);

// Active-link detection: check if current URL starts with the given path.
function isActive(path: string): boolean {
    return page.url.startsWith(path);
}
</script>

<template>
    <a href="#main" class="skip-link">{{ t('common.actions.skip_to_content') }}</a>

    <div class="min-h-screen flex flex-col">
        <header
            class="border-b border-[var(--color-border)] bg-[var(--color-surface)]"
        >
            <div class="max-w-3xl mx-auto px-4 md:px-6 h-16 flex items-center justify-between gap-4">
                <Wordmark />

                <!-- Center nav — populated in Phase 2 (Clans + Players links).
                     Hidden on mobile (hidden md:flex) per UI-SPEC § Responsive Breakpoints § Mobile nav note. -->
                <nav class="hidden md:flex flex-1 justify-center items-center gap-1">
                    <!-- Clans nav link — active when URL is /clans* -->
                    <Link
                        href="/clans"
                        :class="[
                            'px-3 py-1 text-sm font-semibold rounded-md',
                            'transition-colors duration-[var(--motion-duration-fast)] ease-[var(--ease-default)]',
                            'focus-visible:outline-2 focus-visible:outline-[var(--color-focus-ring)]',
                            isActive('/clans')
                                ? 'text-[var(--color-text)] border-l-[3px] border-[var(--color-accent)] pl-2'
                                : 'text-[var(--color-text-muted)] hover:text-[var(--color-text)]',
                        ]"
                        :aria-current="isActive('/clans') ? 'page' : undefined"
                    >
                        {{ t('common.nav.clans') }}
                    </Link>

                    <!-- Players nav link — /players index does NOT exist in P2.
                         TODO Phase 9: wire to /players index page when it lands. -->
                    <a
                        href="/players"
                        :class="[
                            'px-3 py-1 text-sm font-semibold rounded-md',
                            'transition-colors duration-[var(--motion-duration-fast)] ease-[var(--ease-default)]',
                            'focus-visible:outline-2 focus-visible:outline-[var(--color-focus-ring)]',
                            isActive('/players')
                                ? 'text-[var(--color-text)] border-l-[3px] border-[var(--color-accent)] pl-2'
                                : 'text-[var(--color-text-muted)] hover:text-[var(--color-text)]',
                        ]"
                        :aria-current="isActive('/players') ? 'page' : undefined"
                    >
                        {{ t('common.nav.players') }}
                    </a>
                </nav>

                <div class="flex items-center gap-2">
                    <slot name="locale-switcher" />
                    <ThemeToggle />
                    <!-- Auth action: UserMenu when logged in, LoginButton when logged out. -->
                    <UserMenu v-if="user" :user="user" />
                    <LoginButton v-else />
                </div>
            </div>
        </header>

        <main id="main" class="flex-1">
            <slot />
        </main>

        <footer class="border-t border-[var(--color-border)] bg-[var(--color-surface)]">
            <div class="max-w-3xl mx-auto px-4 md:px-6 py-6 text-sm text-[var(--color-text-muted)]">
                <slot name="footer">
                    {{ `© ${year} ${t('common.brand.name')}` }}
                </slot>
            </div>
        </footer>
    </div>
</template>
